<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use Spryker\ApiPlatform\Contract\Attribute\Rule;

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
