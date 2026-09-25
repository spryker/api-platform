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
    public function calculate(
        TruthSet $enforcedTruth,
        TruthSet $existenceTruth,
        CollectedAnnotations $annotations
    ): CoverageReport {
        $declaredOperationKeys = $this->keyed($annotations->declaredOperations);
        $existingOperationKeys = $this->keyed($existenceTruth->allOperations());

        $coveredOperations = [];
        $uncoveredOperations = [];
        foreach ($this->unique($enforcedTruth->servableOperations) as $operation) {
            if (isset($declaredOperationKeys[$operation->key()])) {
                $coveredOperations[] = $operation;

                continue;
            }
            $uncoveredOperations[] = $operation;
        }

        $staleOperations = [];
        foreach ($this->unique($annotations->declaredOperations) as $operation) {
            if (!isset($existingOperationKeys[$operation->key()])) {
                $staleOperations[] = $operation;
            }
        }

        $declaredValidationKeys = $this->keyed($annotations->declaredValidations);
        $existingValidationKeys = $this->keyed($existenceTruth->validationConstraints);

        $coveredValidations = [];
        $uncoveredValidations = [];
        foreach ($this->unique($enforcedTruth->validationConstraints) as $validation) {
            if (isset($declaredValidationKeys[$validation->key()])) {
                $coveredValidations[] = $validation;

                continue;
            }
            $uncoveredValidations[] = $validation;
        }

        $staleValidations = [];
        foreach ($this->unique($annotations->declaredValidations) as $validation) {
            if (!isset($existingValidationKeys[$validation->key()])) {
                $staleValidations[] = $validation;
            }
        }

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
            $coveredOperations,
            $uncoveredOperations,
            $staleOperations,
            $coveredValidations,
            $uncoveredValidations,
            $staleValidations,
            $coveredResponseAttributes,
            $uncoveredResponseAttributes,
        );
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation|\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $entries
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
     * @template T of \Spryker\ApiPlatform\Contract\Coverage\ApiOperation|\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint|\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute
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
