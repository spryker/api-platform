<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The runtime side of the annotation contract: given the operations a test method declared it
 * covers, the operations actually dispatched and the statuses actually returned, reports the
 * declarations no response satisfied and the responses the resource schema does not declare.
 *
 * The resource schema is the arbiter for both. A declared status is verified only by that status
 * really coming back, and an observed status the schema never declares is a contradiction between
 * code and contract that the developer has to resolve in one direction or the other.
 */
class OperationVerifier
{
    protected const int STATUS_SUCCESS_MIN = 200;

    protected const int STATUS_SUCCESS_MAX = 299;

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $declared
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $recordedDispatches Status-less, from the kernel.request listener.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $recordedResponses Status-carrying, from the kernel.response listener.
     * @param array<string, array<int>> $declaredResponses dispatchKey => schema-declared statuses.
     */
    public function verify(
        array $declared,
        array $recordedDispatches,
        array $recordedResponses,
        array $declaredResponses = [],
    ): OperationVerificationResult {
        $dispatchedKeys = array_map(static fn (ApiOperation $operation): string => $operation->dispatchKey(), $recordedDispatches);
        $observedStatuses = $this->groupStatusesByDispatchKey($recordedResponses);

        return new OperationVerificationResult(
            $this->findUnverifiedDeclarations($declared, $dispatchedKeys, $observedStatuses, $recordedResponses, $declaredResponses),
            $this->findUndeclaredObservations($declared, $recordedResponses, $declaredResponses),
        );
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $declared
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $recorded
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    public function findUnverified(array $declared, array $recorded): array
    {
        return $this->verify($declared, $recorded, [], [])->unverified;
    }

    /**
     * A status-carrying declaration needs that exact status back. A status-less one stands for the
     * success response, so it needs one of the schema's declared 2xx. Without response recordings —
     * a kernel whose response listener never ran — both fall back to dispatch presence, which is
     * what the recorder alone can prove.
     *
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $declared
     * @param array<string> $dispatchedKeys
     * @param array<string, array<int>> $observedStatuses
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $recordedResponses
     * @param array<string, array<int>> $declaredResponses
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected function findUnverifiedDeclarations(
        array $declared,
        array $dispatchedKeys,
        array $observedStatuses,
        array $recordedResponses,
        array $declaredResponses,
    ): array {
        return array_values(array_filter(
            $declared,
            fn (ApiOperation $operation): bool => !$this->isVerified(
                $operation,
                $dispatchedKeys,
                $observedStatuses,
                $recordedResponses,
                $declaredResponses,
            ),
        ));
    }

    /**
     * @param array<string> $dispatchedKeys
     * @param array<string, array<int>> $observedStatuses
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $recordedResponses
     * @param array<string, array<int>> $declaredResponses
     */
    protected function isVerified(
        ApiOperation $operation,
        array $dispatchedKeys,
        array $observedStatuses,
        array $recordedResponses,
        array $declaredResponses,
    ): bool {
        $dispatchKey = $operation->dispatchKey();

        if ($recordedResponses === []) {
            return in_array($dispatchKey, $dispatchedKeys, true);
        }

        return $this->isSatisfied(
            $operation,
            $observedStatuses[$dispatchKey] ?? [],
            $declaredResponses[$dispatchKey] ?? [],
        );
    }

    /**
     * @param array<int> $observed
     * @param array<int> $schemaStatuses
     */
    protected function isSatisfied(ApiOperation $operation, array $observed, array $schemaStatuses): bool
    {
        if ($operation->status !== null) {
            return in_array($operation->status, $observed, true);
        }

        $declaredSuccesses = array_filter(
            $schemaStatuses,
            static fn (int $status): bool => $status >= static::STATUS_SUCCESS_MIN && $status <= static::STATUS_SUCCESS_MAX,
        );

        // No schema entry: the gate reports that as a defect, so here any dispatch counts.
        if ($declaredSuccesses === []) {
            return $observed !== [];
        }

        return array_intersect($observed, $declaredSuccesses) !== [];
    }

    /**
     * Responses observed on an operation the test claims, whose status the schema does not declare.
     * Operations the test never claimed are ignored — helper requests (logins, fixtures) are not
     * part of its contract — and so are operations with no schema entry, which the gate reports as
     * a schema defect instead.
     *
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $declared
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $recordedResponses
     * @param array<string, array<int>> $declaredResponses
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected function findUndeclaredObservations(array $declared, array $recordedResponses, array $declaredResponses): array
    {
        $declaredKeys = array_map(static fn (ApiOperation $operation): string => $operation->dispatchKey(), $declared);

        $undeclaredObservations = [];
        $seenKeys = [];

        foreach ($recordedResponses as $response) {
            if (!$this->isJudgeable($response, $declaredKeys, $declaredResponses)) {
                continue;
            }

            if ($this->isDeclaredStatus($response, $declaredResponses) || isset($seenKeys[$response->key()])) {
                continue;
            }

            $seenKeys[$response->key()] = true;
            $undeclaredObservations[] = $response;
        }

        return $undeclaredObservations;
    }

    /**
     * Whether the schema can be held against this response at all: the test has to have claimed the
     * operation, and the schema has to say something about it.
     *
     * @param array<string> $declaredKeys
     * @param array<string, array<int>> $declaredResponses
     */
    protected function isJudgeable(ApiOperation $response, array $declaredKeys, array $declaredResponses): bool
    {
        $dispatchKey = $response->dispatchKey();

        return in_array($dispatchKey, $declaredKeys, true) && isset($declaredResponses[$dispatchKey]);
    }

    /**
     * @param array<string, array<int>> $declaredResponses
     */
    protected function isDeclaredStatus(ApiOperation $response, array $declaredResponses): bool
    {
        return in_array($response->status, $declaredResponses[$response->dispatchKey()] ?? [], true);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $recordedResponses
     *
     * @return array<string, array<int>>
     */
    protected function groupStatusesByDispatchKey(array $recordedResponses): array
    {
        $statuses = [];

        foreach ($recordedResponses as $response) {
            if ($response->status !== null) {
                $statuses[$response->dispatchKey()][] = $response->status;
            }
        }

        return $statuses;
    }
}
