<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use Spryker\ApiPlatform\Contract\Attribute\Rule;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Maps a Symfony validator constraint (by its short class name, plus the resolved bounds of a
 * `Length`) to zero or more rule identifiers.
 *
 * The default is the constraint's own short name, so only the constraints that cannot follow it are
 * listed: {@see self::SPECIAL}, {@see self::CASCADING} — which the caller must walk into — and
 * {@see self::INERT}.
 */
class ConstraintRuleMapper
{
    protected const string CONSTRAINT_LENGTH = 'Length';

    protected const string PARAMETER_VALUE_LENGTH = '{{ value_length }}';

    protected const string PARAMETER_LIMIT = '{{ limit }}';

    /**
     * Constraints that do not map to the rule of the same name. `Length` is the only one so far: a
     * single declaration can carry both bounds and each is an independently testable rule.
     *
     * @var array<string>
     */
    public const array SPECIAL = [self::CONSTRAINT_LENGTH];

    /**
     * Constraints whose rules live one level down — the caller has to descend into them.
     *
     * @var array<string>
     */
    public const array CASCADING = ['Valid', 'Collection', 'All', 'Optional', 'Required', 'Sequentially'];

    /**
     * Constraints that genuinely carry nothing to cover.
     *
     * @var array<string>
     */
    public const array INERT = ['GroupSequence', 'Compound'];

    public function isCascading(string $constraintShortName): bool
    {
        return in_array($constraintShortName, static::CASCADING, true);
    }

    /**
     * @return array<string>
     */
    public function rulesFor(string $constraintShortName, ?int $lengthMin = null, ?int $lengthMax = null): array
    {
        if ($this->isCascading($constraintShortName) || in_array($constraintShortName, static::INERT, true)) {
            return [];
        }

        if ($constraintShortName === static::CONSTRAINT_LENGTH) {
            return $this->lengthRules($lengthMin, $lengthMax);
        }

        return [$constraintShortName];
    }

    /**
     * The one rule a raised violation stands for. A `Length` names its bound through the violation
     * code, since one declaration carries both. An exact length (`min` equal to `max`) raises the
     * same code for either side, so its parameters tell which bound the value missed.
     *
     * @param array<string, mixed> $violationParameters
     */
    public function ruleForViolation(string $constraintShortName, ?string $violationCode, array $violationParameters = []): string
    {
        if ($constraintShortName !== static::CONSTRAINT_LENGTH) {
            return $constraintShortName;
        }

        return match ($violationCode) {
            Length::TOO_SHORT_ERROR => Rule::LENGTH_MIN->value,
            Length::TOO_LONG_ERROR => Rule::LENGTH_MAX->value,
            Length::NOT_EQUAL_LENGTH_ERROR => $this->exactLengthRule($violationParameters) ?? $constraintShortName,
            default => $constraintShortName,
        };
    }

    /**
     * @param array<string, mixed> $violationParameters
     */
    protected function exactLengthRule(array $violationParameters): ?string
    {
        $valueLength = $violationParameters[static::PARAMETER_VALUE_LENGTH] ?? null;
        $limit = $violationParameters[static::PARAMETER_LIMIT] ?? null;

        if (!is_numeric($valueLength) || !is_numeric($limit)) {
            return null;
        }

        return (int)$valueLength < (int)$limit ? Rule::LENGTH_MIN->value : Rule::LENGTH_MAX->value;
    }

    /**
     * @return array<string>
     */
    protected function lengthRules(?int $lengthMin, ?int $lengthMax): array
    {
        $rules = [];

        if ($lengthMin !== null) {
            $rules[] = Rule::LENGTH_MIN->value;
        }

        if ($lengthMax !== null) {
            $rules[] = Rule::LENGTH_MAX->value;
        }

        return $rules;
    }
}
