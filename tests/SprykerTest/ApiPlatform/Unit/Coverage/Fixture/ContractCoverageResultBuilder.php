<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageResult;
use Spryker\ApiPlatform\Contract\Coverage\CoverageReport;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute;
use Spryker\ApiPlatform\Contract\Coverage\SchemaDefect;
use Spryker\ApiPlatform\Contract\Coverage\ScopeResolution;
use Spryker\ApiPlatform\Contract\Coverage\TruthSet;
use Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint;

/**
 * Assembles a {@see ContractCoverageResult} from only the parts a rendering test cares about, so a
 * test does not have to restate the whole scope-resolution graph to exercise one section.
 */
class ContractCoverageResultBuilder
{
    /**
     * @var array<string>
     */
    protected array $selectedResources = ['wishlists'];

    protected int $generatedResourceCount = 1;

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected array $coveredOperations = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected array $uncoveredOperations = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected array $staleOperations = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected array $nonServableOperations = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected array $internalOperations = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    protected array $coveredValidations = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    protected array $uncoveredValidations = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    protected array $staleValidations = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute>
     */
    protected array $coveredResponseAttributes = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute>
     */
    protected array $uncoveredResponseAttributes = [];

    /**
     * @var array<string, array<string>>
     */
    protected array $operationDeclarers = [];

    /**
     * @var array<string>
     */
    /**
     * @var array<string>
     */
    protected array $unmatchedFilters = [];

    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\SchemaDefect>
     */
    protected array $schemaDefects = [];

    public static function create(): self
    {
        return new self();
    }

    public function withSelectedResources(string ...$resources): self
    {
        $this->selectedResources = $resources;

        return $this;
    }

    public function withGeneratedResourceCount(int $count): self
    {
        $this->generatedResourceCount = $count;

        return $this;
    }

    public function withCoveredOperations(ApiOperation ...$operations): self
    {
        $this->coveredOperations = $operations;

        return $this;
    }

    public function withUncoveredOperations(ApiOperation ...$operations): self
    {
        $this->uncoveredOperations = $operations;

        return $this;
    }

    public function withStaleOperations(ApiOperation ...$operations): self
    {
        $this->staleOperations = $operations;

        return $this;
    }

    public function withNonServableOperations(ApiOperation ...$operations): self
    {
        $this->nonServableOperations = $operations;

        return $this;
    }

    public function withInternalOperations(ApiOperation ...$operations): self
    {
        $this->internalOperations = $operations;

        return $this;
    }

    public function withCoveredValidations(ValidationConstraint ...$constraints): self
    {
        $this->coveredValidations = $constraints;

        return $this;
    }

    public function withUncoveredValidations(ValidationConstraint ...$constraints): self
    {
        $this->uncoveredValidations = $constraints;

        return $this;
    }

    public function withStaleValidations(ValidationConstraint ...$constraints): self
    {
        $this->staleValidations = $constraints;

        return $this;
    }

    public function withCoveredResponseAttributes(ResponseAttribute ...$responseAttributes): self
    {
        $this->coveredResponseAttributes = $responseAttributes;

        return $this;
    }

    public function withUncoveredResponseAttributes(ResponseAttribute ...$responseAttributes): self
    {
        $this->uncoveredResponseAttributes = $responseAttributes;

        return $this;
    }

    /**
     * @param array<string, array<string>> $operationDeclarers
     */
    public function withOperationDeclarers(array $operationDeclarers): self
    {
        $this->operationDeclarers = $operationDeclarers;

        return $this;
    }

    public function withUnmatchedFilters(string ...$filters): self
    {
        $this->unmatchedFilters = $filters;

        return $this;
    }

    public function withSchemaDefects(SchemaDefect ...$schemaDefects): self
    {
        $this->schemaDefects = $schemaDefects;

        return $this;
    }

    public function build(): ContractCoverageResult
    {
        $enforcedTruth = new TruthSet(
            array_merge($this->coveredOperations, $this->uncoveredOperations),
            $this->nonServableOperations,
            array_merge($this->coveredValidations, $this->uncoveredValidations),
            [],
            [],
            $this->internalOperations,
            array_merge($this->coveredResponseAttributes, $this->uncoveredResponseAttributes),
        );

        return new ContractCoverageResult(
            new CoverageReport(
                $this->coveredOperations,
                $this->uncoveredOperations,
                $this->staleOperations,
                $this->coveredValidations,
                $this->uncoveredValidations,
                $this->staleValidations,
                $this->coveredResponseAttributes,
                $this->uncoveredResponseAttributes,
            ),
            new ScopeResolution($enforcedTruth, $enforcedTruth),
            $this->selectedResources,
            $this->unmatchedFilters,
            $this->generatedResourceCount,
            $this->schemaDefects,
            $this->operationDeclarers,
        );
    }
}
