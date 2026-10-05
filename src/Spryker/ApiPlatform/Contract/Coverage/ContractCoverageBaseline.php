<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use InvalidArgumentException;

/**
 * The coverage items a known product bug keeps uncovered, per {@see ContractCoverageDimension}, each
 * with the bug in plain language. A baselined item does not fail its dimension, and an entry whose
 * item is covered, or that names no gap, fails the gate until it is removed: the baseline only ever
 * shrinks. The static gate and the test runtime read the same `contract_coverage_baseline`.
 */
readonly class ContractCoverageBaseline
{
    /**
     * @param array<string, array<string, string>> $reasonsByDimension Dimension value => item key => reason.
     */
    public function __construct(public array $reasonsByDimension = [])
    {
    }

    /**
     * @param array<mixed> $configuration Dimension value => item key => the bug that keeps the item uncovered.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromConfiguration(array $configuration): self
    {
        $reasonsByDimension = [];

        foreach ($configuration as $dimensionValue => $entries) {
            $dimension = ContractCoverageDimension::tryFrom((string)$dimensionValue);
            if ($dimension === null) {
                throw new InvalidArgumentException(sprintf(
                    'The contract-coverage baseline names the unknown dimension "%s". Known: %s.',
                    $dimensionValue,
                    implode(', ', array_column(ContractCoverageDimension::cases(), 'value')),
                ));
            }

            if (!is_array($entries)) {
                throw new InvalidArgumentException(sprintf(
                    'The contract-coverage baseline of "%s" must map each item key to the bug that keeps it uncovered.',
                    $dimension->value,
                ));
            }

            foreach ($entries as $itemKey => $reason) {
                if (!is_string($reason) || trim($reason) === '') {
                    throw new InvalidArgumentException(sprintf(
                        'The contract-coverage baseline entry "%s" of "%s" gives no reason. Describe the bug that keeps the item uncovered.',
                        $itemKey,
                        $dimension->value,
                    ));
                }

                $reasonsByDimension[$dimension->value][(string)$itemKey] = $reason;
            }
        }

        return new self($reasonsByDimension);
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * @return array<string, string> Item key => reason.
     */
    public function reasonsOf(ContractCoverageDimension $dimension): array
    {
        return $this->reasonsByDimension[$dimension->value] ?? [];
    }

    public function isBaselined(ContractCoverageDimension $dimension, string $itemKey): bool
    {
        return isset($this->reasonsByDimension[$dimension->value][$itemKey]);
    }

    /**
     * Moves the uncovered items the baseline names out of the gaps, and returns the entries that
     * have to leave it: one whose item a claim covers, and - on a run over the whole enforced scope,
     * where every gap is known - one that names no uncovered item at all. A runtime-only dimension
     * has no static items, so its entries are listed and the test runtime judges them.
     *
     * @param \Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage<\Spryker\ApiPlatform\Contract\Coverage\CoverageItem> $dimensionCoverage
     *
     * @return \Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage<\Spryker\ApiPlatform\Contract\Coverage\CoverageItem>
     */
    public function apply(ContractCoverageDimension $dimension, DimensionCoverage $dimensionCoverage, bool $isFullScope): DimensionCoverage
    {
        $reasons = $this->reasonsOf($dimension);
        if ($reasons === []) {
            return $dimensionCoverage;
        }

        if ($dimension->isRuntimeOnly()) {
            return new DimensionCoverage(
                $dimensionCoverage->covered,
                $dimensionCoverage->uncovered,
                $dimensionCoverage->stale,
                $this->toEntries($reasons),
            );
        }

        $uncovered = [];
        $baselined = [];
        foreach ($dimensionCoverage->uncovered as $item) {
            if (isset($reasons[$item->key()])) {
                $baselined[] = new BaselineEntry($item->key(), $reasons[$item->key()]);
                unset($reasons[$item->key()]);

                continue;
            }
            $uncovered[] = $item;
        }

        $coveredKeys = [];
        foreach ($dimensionCoverage->covered as $item) {
            $coveredKeys[$item->key()] = true;
        }

        $nowCovered = [];
        $namingNoGap = [];
        foreach ($this->toEntries($reasons) as $entry) {
            if (isset($coveredKeys[$entry->itemKey])) {
                $nowCovered[] = $entry;

                continue;
            }

            if ($isFullScope) {
                $namingNoGap[] = $entry;
            }
        }

        return new DimensionCoverage(
            $dimensionCoverage->covered,
            $uncovered,
            $dimensionCoverage->stale,
            $baselined,
            $nowCovered,
            $namingNoGap,
        );
    }

    /**
     * @param array<string, string> $reasons
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\BaselineEntry>
     */
    protected function toEntries(array $reasons): array
    {
        $entries = [];
        foreach ($reasons as $itemKey => $reason) {
            $entries[] = new BaselineEntry((string)$itemKey, $reason);
        }

        return $entries;
    }
}
