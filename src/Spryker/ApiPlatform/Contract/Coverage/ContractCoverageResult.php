<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The outcome of one contract-coverage run, ready to render. Carries no formatting of its own so
 * the console command owns presentation.
 */
class ContractCoverageResult
{
    /**
     * @param array<string> $selectedResources The resources this run enforced.
     * @param array<string> $unmatchedFilters Module filters that named no generated resource.
     * @param int $generatedResourceCount Every generated resource, enforced or excluded.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\SchemaDefect> $schemaDefects Enforced operations whose schema declares no responses.
     * @param array<string, array<string>> $operationDeclarers Dispatch key => the tests declaring it, so a gap can name where its marker belongs.
     * @param array<string> $unknownExclusions Excluded short names no generated resource carries.
     */
    public function __construct(
        public readonly CoverageReport $report,
        public readonly ScopeResolution $scope,
        public readonly array $selectedResources,
        public readonly array $unmatchedFilters,
        public readonly int $generatedResourceCount,
        public readonly array $schemaDefects = [],
        public readonly array $operationDeclarers = [],
        public readonly array $unknownExclusions = [],
    ) {
    }

    /**
     * Why the gate fails, in the order a reader wants them. Empty means PASS.
     *
     * @return array<string>
     */
    public function failureReasons(): array
    {
        $reasons = [];

        if ($this->unmatchedFilters !== []) {
            $reasons[] = sprintf('unknown module(s): %s', implode(', ', $this->unmatchedFilters));
        }
        // An exclusion naming nothing is a resource that was renamed or removed without its
        // exclusion following, so the gate would keep honouring a line that protects nothing.
        if ($this->unknownExclusions !== []) {
            $reasons[] = sprintf('excluded resource(s) that do not exist: %s', implode(', ', $this->unknownExclusions));
        }
        // First: without declared responses there is no contract to measure the rest against.
        if ($this->schemaDefects !== []) {
            $reasons[] = count($this->schemaDefects) . ' operation(s) without schema-declared responses';
        }
        if ($this->report->uncoveredOperations !== []) {
            $reasons[] = count($this->report->uncoveredOperations) . ' uncovered operation(s)';
        }
        if ($this->report->staleOperations !== []) {
            $reasons[] = count($this->report->staleOperations) . ' stale operation claim(s)';
        }
        if ($this->report->uncoveredValidations !== []) {
            $reasons[] = count($this->report->uncoveredValidations) . ' uncovered validation rule(s)';
        }
        if ($this->report->staleValidations !== []) {
            $reasons[] = count($this->report->staleValidations) . ' stale validation claim(s)';
        }
        if ($this->report->uncoveredResponseAttributes !== []) {
            $reasons[] = count($this->report->uncoveredResponseAttributes) . ' uncovered response attribute(s)';
        }

        return $reasons;
    }

    public function isSuccessful(): bool
    {
        return $this->failureReasons() === [];
    }
}
