<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use InvalidArgumentException;
use ValueError;

/**
 * Which {@see ContractCoverageDimension}s fail the gate. The static gate and the test runtime read
 * the same `contract_coverage_enforced_dimensions` list, so one config line switches a dimension on
 * for both.
 */
readonly class ContractCoverageEnforcement
{
    /**
     * Enforces every dimension, so an application that covers them all needs no list to maintain.
     */
    public const string ALL = 'all';

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension> $enforcedDimensions
     */
    public function __construct(public array $enforcedDimensions = [])
    {
    }

    /**
     * @param array<string> $values Dimension values, or {@see ContractCoverageEnforcement::ALL}.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromValues(array $values): self
    {
        $dimensions = [];

        foreach ($values as $value) {
            if ($value === static::ALL) {
                return static::all();
            }

            try {
                $dimensions[] = ContractCoverageDimension::from($value);
            } catch (ValueError $valueError) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown contract-coverage dimension "%s". Known: %s, or %s.',
                    $value,
                    implode(', ', array_column(ContractCoverageDimension::cases(), 'value')),
                    static::ALL,
                ), 0, $valueError);
            }
        }

        return new self(static::unique($dimensions));
    }

    public static function none(): self
    {
        return new self([]);
    }

    public static function all(): self
    {
        return new self(ContractCoverageDimension::cases());
    }

    /**
     * Every value the configuration and the `--enforce` option accept.
     *
     * @return array<string>
     */
    public static function acceptedValues(): array
    {
        return [...array_column(ContractCoverageDimension::cases(), 'value'), static::ALL];
    }

    public function isEnforced(ContractCoverageDimension $dimension): bool
    {
        return in_array($dimension, $this->enforcedDimensions, true);
    }

    /**
     * Widens, never narrows: a local preview can add a dimension to the configured set, but it can
     * never switch off one the application enforces.
     */
    public function withAdditional(self $additional): self
    {
        return new self(static::unique([...$this->enforcedDimensions, ...$additional->enforcedDimensions]));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension> $dimensions
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension>
     */
    protected static function unique(array $dimensions): array
    {
        $unique = [];
        foreach ($dimensions as $dimension) {
            $unique[$dimension->value] = $dimension;
        }

        return array_values($unique);
    }
}
