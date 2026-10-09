<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The generated source of truth for a coverage scope: the operations a test must cover (servable),
 * the item-GETs that only mint IRIs and so cannot be covered (non-servable), the ones that do the
 * same by explicit declaration (internal), the validation constraints a test must cover, the
 * operations whose schema declares no responses at all (a schema defect the gate reports), the
 * statuses each operation's schema declares and the response attributes each success operation must
 * carry.
 */
readonly class TruthSet
{
    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $servableOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $nonServableOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $validationConstraints
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $undeclaredResponseOperations
     * @param array<string, array<int>> $declaredResponses dispatchKey => sorted unique declared statuses
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $internalOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $responseAttributes
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $errorCodeOperations One entry per declared error code of a servable operation.
     * @param array<string, array<int, array<string>>> $declaredErrorCodes dispatchKey => status => sorted declared codes
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $ownershipScenarioOperations One foreign-owner scenario item per servable operation guarded by an ownership check.
     * @param array<string, array{resources: array<string>, notAnswered: array<int|string, string>, declaredErrorCodes: array<int, array<string>>}> $errorMappingRegistrations
     *   `Class::method` of a registered error mapping => the resources registering it, the codes they declare they never answer (with the reason) and the codes their operations declare.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $readableArraysWithoutRequiredItems Response arrays whose element shape the schema does not demand.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RequestAttribute> $requestAttributes The attributes each input operation accepts.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\IncludeRelationship> $includeRelationships The includes each read has to prove.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\IncludeRelationship> $writeIncludeRelationships The includes a write may be claimed for without owing them.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ReplayedResource> $replayableResources The resources with a servable operation whose examples can be replayed.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ThrownStatus|\Spryker\ApiPlatform\Contract\Coverage\UnreadableThrownStatus> $thrownStatuses The error statuses each servable operation's processor and provider can throw, and the ones that cannot be read.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\TypedRequestAttribute> $typedRequestAttributes The writable attributes of each input operation whose value has a shape of its own.
     */
    public function __construct(
        public array $servableOperations,
        public array $nonServableOperations,
        public array $validationConstraints,
        public array $undeclaredResponseOperations = [],
        public array $declaredResponses = [],
        public array $internalOperations = [],
        public array $responseAttributes = [],
        public array $errorCodeOperations = [],
        public array $declaredErrorCodes = [],
        public array $ownershipScenarioOperations = [],
        public array $errorMappingRegistrations = [],
        public array $readableArraysWithoutRequiredItems = [],
        public array $requestAttributes = [],
        public array $includeRelationships = [],
        public array $writeIncludeRelationships = [],
        public array $replayableResources = [],
        public array $thrownStatuses = [],
        public array $typedRequestAttributes = [],
    ) {
    }

    /**
     * Every operation the schema defines anywhere, whatever a test can do with it. Existence checks
     * read this so a claim on an unreachable operation is reported as unreachable rather than stale.
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    public function allOperations(): array
    {
        return array_merge($this->servableOperations, $this->nonServableOperations, $this->internalOperations);
    }

    /**
     * The one place that knows every field: the per-resource truths of a short name and the
     * per-scope truths both merge through here, so a new field cannot be dropped by one of them.
     */
    public static function merge(self ...$truthSets): self
    {
        $servableOperations = [];
        $nonServableOperations = [];
        $validationConstraints = [];
        $undeclaredResponseOperations = [];
        $declaredResponses = [];
        $internalOperations = [];
        $responseAttributes = [];
        $errorCodeOperations = [];
        $declaredErrorCodes = [];
        $ownershipScenarioOperations = [];
        $errorMappingRegistrations = [];
        $readableArraysWithoutRequiredItems = [];
        $requestAttributes = [];
        $includeRelationships = [];
        $writeIncludeRelationships = [];
        $replayableResources = [];
        $thrownStatuses = [];
        $typedRequestAttributes = [];

        foreach ($truthSets as $truthSet) {
            $servableOperations = array_merge($servableOperations, $truthSet->servableOperations);
            $nonServableOperations = array_merge($nonServableOperations, $truthSet->nonServableOperations);
            $validationConstraints = array_merge($validationConstraints, $truthSet->validationConstraints);
            $undeclaredResponseOperations = array_merge($undeclaredResponseOperations, $truthSet->undeclaredResponseOperations);
            $declaredResponses = static::mergeDeclaredResponses($declaredResponses, $truthSet->declaredResponses);
            $internalOperations = array_merge($internalOperations, $truthSet->internalOperations);
            $responseAttributes = array_merge($responseAttributes, $truthSet->responseAttributes);
            $errorCodeOperations = array_merge($errorCodeOperations, $truthSet->errorCodeOperations);
            $declaredErrorCodes = static::mergeDeclaredErrorCodes($declaredErrorCodes, $truthSet->declaredErrorCodes);
            $ownershipScenarioOperations = array_merge($ownershipScenarioOperations, $truthSet->ownershipScenarioOperations);
            $errorMappingRegistrations = static::mergeErrorMappingRegistrations($errorMappingRegistrations, $truthSet->errorMappingRegistrations);
            $readableArraysWithoutRequiredItems = array_merge($readableArraysWithoutRequiredItems, $truthSet->readableArraysWithoutRequiredItems);
            $requestAttributes = array_merge($requestAttributes, $truthSet->requestAttributes);
            $includeRelationships = array_merge($includeRelationships, $truthSet->includeRelationships);
            $writeIncludeRelationships = array_merge($writeIncludeRelationships, $truthSet->writeIncludeRelationships);
            $replayableResources = array_merge($replayableResources, $truthSet->replayableResources);
            $thrownStatuses = array_merge($thrownStatuses, $truthSet->thrownStatuses);
            $typedRequestAttributes = array_merge($typedRequestAttributes, $truthSet->typedRequestAttributes);
        }

        return new self(
            $servableOperations,
            $nonServableOperations,
            $validationConstraints,
            $undeclaredResponseOperations,
            $declaredResponses,
            $internalOperations,
            $responseAttributes,
            $errorCodeOperations,
            $declaredErrorCodes,
            $ownershipScenarioOperations,
            $errorMappingRegistrations,
            $readableArraysWithoutRequiredItems,
            $requestAttributes,
            $includeRelationships,
            $writeIncludeRelationships,
            $replayableResources,
            $thrownStatuses,
            $typedRequestAttributes,
        );
    }

    /**
     * @param array<string, array{resources: array<string>, notAnswered: array<int|string, string>, declaredErrorCodes: array<int, array<string>>}> $registrations
     * @param array<string, array{resources: array<string>, notAnswered: array<int|string, string>, declaredErrorCodes: array<int, array<string>>}> $additionalRegistrations
     *
     * @return array<string, array{resources: array<string>, notAnswered: array<int|string, string>, declaredErrorCodes: array<int, array<string>>}>
     */
    public static function mergeErrorMappingRegistrations(array $registrations, array $additionalRegistrations): array
    {
        foreach ($additionalRegistrations as $source => $registration) {
            $existing = $registrations[$source] ?? ['resources' => [], 'notAnswered' => [], 'declaredErrorCodes' => []];
            $merged = static::mergeDeclaredErrorCodes([$source => $existing['declaredErrorCodes']], [$source => $registration['declaredErrorCodes']]);

            $registrations[$source] = [
                'resources' => array_values(array_unique([...$existing['resources'], ...$registration['resources']])),
                'notAnswered' => $existing['notAnswered'] + $registration['notAnswered'],
                'declaredErrorCodes' => $merged[$source] ?? [],
            ];
        }

        return $registrations;
    }

    /**
     * @param array<string, array<int, array<string>>> $declaredErrorCodes
     * @param array<string, array<int, array<string>>> $additionalDeclaredErrorCodes
     *
     * @return array<string, array<int, array<string>>>
     */
    public static function mergeDeclaredErrorCodes(array $declaredErrorCodes, array $additionalDeclaredErrorCodes): array
    {
        foreach ($additionalDeclaredErrorCodes as $dispatchKey => $codesByStatus) {
            foreach ($codesByStatus as $status => $codes) {
                $merged = array_values(array_unique(array_merge($declaredErrorCodes[$dispatchKey][$status] ?? [], $codes)));
                sort($merged, SORT_STRING);
                $declaredErrorCodes[$dispatchKey][$status] = $merged;
            }

            if (isset($declaredErrorCodes[$dispatchKey])) {
                ksort($declaredErrorCodes[$dispatchKey]);
            }
        }

        return $declaredErrorCodes;
    }

    /**
     * Two schema files can back one dispatch key, so declared statuses union rather than overwrite.
     *
     * @param array<string, array<int>> $declaredResponses
     * @param array<string, array<int>> $additionalDeclaredResponses
     *
     * @return array<string, array<int>>
     */
    public static function mergeDeclaredResponses(array $declaredResponses, array $additionalDeclaredResponses): array
    {
        foreach ($additionalDeclaredResponses as $dispatchKey => $statuses) {
            $merged = array_values(array_unique(array_merge($declaredResponses[$dispatchKey] ?? [], $statuses)));
            sort($merged);
            $declaredResponses[$dispatchKey] = $merged;
        }

        return $declaredResponses;
    }
}
