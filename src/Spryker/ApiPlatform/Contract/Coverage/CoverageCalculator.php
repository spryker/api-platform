<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Diffs the generated truth against the collected annotations into a {@see CoverageReport}. Pure:
 * set arithmetic over each entry's `key()`, so it is trivially testable with fabricated inputs.
 *
 * - covered = enforced entry a declaration points at.
 * - uncovered = enforced entry with no declaration (the gap a gate fails on).
 * - stale = declaration pointing at nothing the schema defines anywhere.
 *
 * The enforced truth is the in-scope must-cover set; the existence truth is every operation and
 * constraint the schema defines. Splitting them keeps a declaration on a real but unenforced,
 * non-servable or internal operation out of the stale bucket.
 */
class CoverageCalculator
{
    protected const string PATH_WILDCARD = '[]';

    protected const string PATH_SEPARATOR = '.';

    /**
     * @param array<string> $excludedUnconstrainedAttributes `<resource>.<attribute path>` entries the
     *   unconstrained-attributes dimension does not owe, from the application's
     *   `contract_coverage_excluded_unconstrained_attributes`: which typed attributes a project
     *   validates is its own decision.
     */
    public function __construct(protected readonly array $excludedUnconstrainedAttributes = [])
    {
    }

    /**
     * @param array<string, array<\Spryker\ApiPlatform\Contract\Coverage\ErrorMappingEntry>> $errorMappingEntries
     *   The resolved entries of each error mapping the enforced truth registers, keyed by source.
     */
    public function calculate(
        TruthSet $enforcedTruth,
        TruthSet $existenceTruth,
        CollectedAnnotations $annotations,
        array $errorMappingEntries = []
    ): CoverageReport {
        $claimedOperationKeys = $this->coverageKeyed($annotations->declaredOperations);

        // A code claim is judged stale by the error-code dimension; for the operation dimension it
        // only has to narrow an error response the schema declares.
        $operations = $this->diff(
            $enforcedTruth->servableOperations,
            $this->keyed($existenceTruth->allOperations()),
            $claimedOperationKeys,
            array_map(static fn (ApiOperation $operation): ApiOperation => $operation->withoutFacets(), $annotations->declaredOperations),
        );
        $errorCodes = $this->diff(
            $enforcedTruth->errorCodeOperations,
            $this->keyed($existenceTruth->errorCodeOperations),
            $claimedOperationKeys,
            array_map(
                static fn (ApiOperation $operation): ApiOperation => $operation->errorCodeItem(),
                array_values(array_filter($annotations->declaredOperations, static fn (ApiOperation $operation): bool => $operation->code !== null)),
            ),
        );
        $ownershipScenarios = $this->diff(
            $enforcedTruth->ownershipScenarioOperations,
            $this->keyed($existenceTruth->ownershipScenarioOperations),
            $claimedOperationKeys,
            array_map(
                static fn (ApiOperation $operation): ApiOperation => $operation->scenarioItem(),
                array_values(array_filter($annotations->declaredOperations, static fn (ApiOperation $operation): bool => $operation->scenario !== null)),
            ),
        );
        $validations = $this->diff(
            $enforcedTruth->validationConstraints,
            $this->keyed($existenceTruth->validationConstraints),
            $this->keyed($annotations->declaredValidations),
            $annotations->declaredValidations,
        );

        $markedOperationKeys = array_flip($annotations->responseAttributeCoveredOperations);

        $coveredResponseAttributes = [];
        $uncoveredResponseAttributes = [];
        foreach ($this->unique($enforcedTruth->responseAttributes) as $responseAttribute) {
            if (isset($markedOperationKeys[$responseAttribute->dispatchKey])) {
                $coveredResponseAttributes[] = $responseAttribute;

                continue;
            }
            $uncoveredResponseAttributes[] = $responseAttribute;
        }

        return new CoverageReport(
            $operations->covered,
            $operations->uncovered,
            $operations->stale,
            $validations->covered,
            $validations->uncovered,
            $validations->stale,
            $coveredResponseAttributes,
            $uncoveredResponseAttributes,
            [
                ContractCoverageDimension::ERROR_CODES->value => $errorCodes,
                ContractCoverageDimension::OWNERSHIP_SCENARIOS->value => $ownershipScenarios,
                ContractCoverageDimension::ERROR_MAPPINGS->value => $this->diffErrorMappings($enforcedTruth, $existenceTruth, $errorMappingEntries),
                ContractCoverageDimension::REQUEST_ATTRIBUTES->value => $this->diffRequestAttributes($enforcedTruth, $existenceTruth, $annotations),
                ContractCoverageDimension::INCLUDES->value => $this->diffIncludes($enforcedTruth, $existenceTruth, $annotations),
                ContractCoverageDimension::OPENAPI_EXAMPLE_REPLAY->value => $this->diff(
                    $enforcedTruth->replayableResources,
                    $this->keyed($existenceTruth->replayableResources),
                    array_fill_keys($annotations->replayedResources, true),
                    array_map(static fn (string $resourceShortName): ReplayedResource => new ReplayedResource($resourceShortName), $annotations->replayedResources),
                ),
                ContractCoverageDimension::THROWN_STATUSES->value => $this->diffThrownStatuses($enforcedTruth),
                ContractCoverageDimension::UNCONSTRAINED_ATTRIBUTES->value => $this->diffUnconstrainedAttributes($enforcedTruth, $existenceTruth),
            ],
        );
    }

    /**
     * A thrown status is covered when the operation's schema declares it; no test claims it. One that
     * cannot be read is never covered.
     *
     * @return \Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage<\Spryker\ApiPlatform\Contract\Coverage\ThrownStatus|\Spryker\ApiPlatform\Contract\Coverage\UnreadableThrownStatus>
     */
    protected function diffThrownStatuses(TruthSet $enforcedTruth): DimensionCoverage
    {
        $covered = [];
        $uncovered = [];

        foreach ($this->unique($enforcedTruth->thrownStatuses) as $thrownStatus) {
            if ($thrownStatus instanceof ThrownStatus && in_array($thrownStatus->status, $enforcedTruth->declaredResponses[$thrownStatus->dispatchKey] ?? [], true)) {
                $covered[] = $thrownStatus;

                continue;
            }
            $uncovered[] = $thrownStatus;
        }

        return new DimensionCoverage($covered, $uncovered);
    }

    /**
     * A typed attribute is covered when any constraint is active on it, or on a field below it, for
     * the operation. An excluded one is not owed at all, and an exclusion that names no typed
     * attribute of any resource is stale.
     *
     * @return \Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage<\Spryker\ApiPlatform\Contract\Coverage\CoverageItem>
     */
    protected function diffUnconstrainedAttributes(TruthSet $enforcedTruth, TruthSet $existenceTruth): DimensionCoverage
    {
        $constrainedKeys = [];
        foreach ($enforcedTruth->validationConstraints as $validationConstraint) {
            $dispatchKey = (new ApiOperation($validationConstraint->verb, $validationConstraint->uriTemplate))->dispatchKey();
            $constrainedKeys[(new RequestAttribute($dispatchKey, $validationConstraint->attribute))->key()] = true;
        }

        $excludedKeys = array_fill_keys($this->excludedUnconstrainedAttributes, true);
        $covered = [];
        $uncovered = [];

        foreach ($this->unique($enforcedTruth->typedRequestAttributes) as $typedRequestAttribute) {
            if (isset($excludedKeys[$typedRequestAttribute->exclusionKey()])) {
                continue;
            }

            if ($this->isConstrained($typedRequestAttribute, $constrainedKeys)) {
                $covered[] = $typedRequestAttribute;

                continue;
            }
            $uncovered[] = $typedRequestAttribute;
        }

        $existingExclusionKeys = [];
        foreach ($existenceTruth->typedRequestAttributes as $typedRequestAttribute) {
            $existingExclusionKeys[$typedRequestAttribute->exclusionKey()] = true;
        }

        $stale = [];
        foreach (array_unique($this->excludedUnconstrainedAttributes) as $exclusionKey) {
            if (!isset($existingExclusionKeys[$exclusionKey])) {
                $stale[] = new UnconstrainedAttributeExclusion($exclusionKey);
            }
        }

        return new DimensionCoverage($covered, $uncovered, $stale);
    }

    /**
     * A validation rule names a list element's field without the `[]` a request path carries.
     *
     * @param array<string, true> $constrainedKeys
     */
    protected function isConstrained(TypedRequestAttribute $typedRequestAttribute, array $constrainedKeys): bool
    {
        $key = (new RequestAttribute($typedRequestAttribute->dispatchKey, str_replace(static::PATH_WILDCARD, '', $typedRequestAttribute->path)))->key();

        foreach (array_keys($constrainedKeys) as $constrainedKey) {
            if ($constrainedKey === $key || str_starts_with($constrainedKey, $key . static::PATH_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads owe their includes; a claim on a write is accepted without being owed, so it is never
     * stale while the resource declares the relationship.
     *
     * @return \Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage<\Spryker\ApiPlatform\Contract\Coverage\IncludeRelationship>
     */
    protected function diffIncludes(TruthSet $enforcedTruth, TruthSet $existenceTruth, CollectedAnnotations $annotations): DimensionCoverage
    {
        $claims = [];
        foreach ($annotations->includeClaims as $dispatchKey => $relationshipNames) {
            foreach ($relationshipNames as $relationshipName) {
                $claims[] = new IncludeRelationship($dispatchKey, $relationshipName);
            }
        }

        return $this->diff(
            $enforcedTruth->includeRelationships,
            $this->keyed([...$existenceTruth->includeRelationships, ...$existenceTruth->writeIncludeRelationships]),
            $this->keyed($claims),
            $claims,
        );
    }

    /**
     * A request attribute is covered when a marker on its operation claims all attributes or names
     * it. A named path the schema does not accept on that operation is stale.
     *
     * @return \Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage<\Spryker\ApiPlatform\Contract\Coverage\RequestAttribute>
     */
    protected function diffRequestAttributes(TruthSet $enforcedTruth, TruthSet $existenceTruth, CollectedAnnotations $annotations): DimensionCoverage
    {
        $claimedKeys = [];
        $claims = [];
        foreach ($annotations->requestAttributeClaims as $dispatchKey => $paths) {
            if ($paths === true) {
                $claimedKeys[$dispatchKey] = true;

                continue;
            }

            foreach ($paths as $path) {
                $claim = new RequestAttribute($dispatchKey, $path);
                $claims[] = $claim;
                $claimedKeys[$claim->key()] = true;
            }
        }

        $covered = [];
        $uncovered = [];
        foreach ($this->unique($enforcedTruth->requestAttributes) as $requestAttribute) {
            if (isset($claimedKeys[$requestAttribute->dispatchKey]) || isset($claimedKeys[$requestAttribute->key()])) {
                $covered[] = $requestAttribute;

                continue;
            }
            $uncovered[] = $requestAttribute;
        }

        $existingKeys = $this->keyed($existenceTruth->requestAttributes);
        $stale = array_values(array_filter(
            $this->unique($claims),
            static fn (RequestAttribute $claim): bool => !isset($existingKeys[$claim->key()]),
        ));

        return new DimensionCoverage($covered, $uncovered, $stale);
    }

    /**
     * A mapped entry is answered when an operation of a resource registering its mapping declares
     * the entry's code under the entry's status, or the registration says the API never answers it.
     * A `notAnswered` code the mapping does not contain protects nothing, so it is stale. Whether an
     * entry is answered is read from every registering resource, not only the ones this run enforces.
     *
     * @param array<string, array<\Spryker\ApiPlatform\Contract\Coverage\ErrorMappingEntry>> $errorMappingEntries
     *
     * @return \Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage<\Spryker\ApiPlatform\Contract\Coverage\ErrorMappingEntry>
     */
    protected function diffErrorMappings(TruthSet $enforcedTruth, TruthSet $existenceTruth, array $errorMappingEntries): DimensionCoverage
    {
        $covered = [];
        $uncovered = [];
        $stale = [];

        foreach ($enforcedTruth->errorMappingRegistrations as $source => $enforcedRegistration) {
            $registration = $existenceTruth->errorMappingRegistrations[$source] ?? $enforcedRegistration;
            $mappedCodes = [];

            foreach ($this->unique($errorMappingEntries[$source] ?? []) as $entry) {
                $mappedCodes[$entry->code] = true;
                $isAnswered = in_array($entry->code, $registration['declaredErrorCodes'][(int)$entry->status] ?? [], true);

                if ($isAnswered || isset($registration['notAnswered'][$entry->code])) {
                    $covered[] = $entry;

                    continue;
                }
                $uncovered[] = $entry;
            }

            foreach (array_keys($registration['notAnswered']) as $code) {
                if (!isset($mappedCodes[(string)$code])) {
                    $stale[] = new ErrorMappingEntry($source, ErrorMappingEntry::NOT_ANSWERED, (string)$code, null);
                }
            }
        }

        return new DimensionCoverage($covered, $uncovered, $stale);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $declaredOperations
     *
     * @return array<string, true>
     */
    protected function coverageKeyed(array $declaredOperations): array
    {
        $keys = [];
        foreach ($declaredOperations as $declaredOperation) {
            foreach ($declaredOperation->coverageKeys() as $key) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /**
     * The one diff every dimension runs: an enforced item is covered when a claim names its key,
     * and a claim is stale when nothing the schema defines anywhere carries its key.
     *
     * @template T of \Spryker\ApiPlatform\Contract\Coverage\CoverageItem
     *
     * @param array<T> $enforced
     * @param array<string, true> $existingKeys
     * @param array<string, true> $claimedKeys
     * @param array<T> $claims The claims judged for staleness.
     *
     * @return \Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage<T>
     */
    protected function diff(array $enforced, array $existingKeys, array $claimedKeys, array $claims): DimensionCoverage
    {
        $covered = [];
        $uncovered = [];
        foreach ($this->unique($enforced) as $item) {
            if (isset($claimedKeys[$item->key()])) {
                $covered[] = $item;

                continue;
            }
            $uncovered[] = $item;
        }

        $stale = [];
        foreach ($this->unique($claims) as $claim) {
            if (!isset($existingKeys[$claim->key()])) {
                $stale[] = $claim;
            }
        }

        return new DimensionCoverage($covered, $uncovered, $stale);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\CoverageItem> $entries
     *
     * @return array<string, true>
     */
    protected function keyed(array $entries): array
    {
        $keys = [];
        foreach ($entries as $entry) {
            $keys[$entry->key()] = true;
        }

        return $keys;
    }

    /**
     * @template T of \Spryker\ApiPlatform\Contract\Coverage\CoverageItem
     *
     * @param array<T> $entries
     *
     * @return array<T>
     */
    protected function unique(array $entries): array
    {
        $seen = [];
        $unique = [];
        foreach ($entries as $entry) {
            if (isset($seen[$entry->key()])) {
                continue;
            }
            $seen[$entry->key()] = true;
            $unique[] = $entry;
        }

        return $unique;
    }
}
