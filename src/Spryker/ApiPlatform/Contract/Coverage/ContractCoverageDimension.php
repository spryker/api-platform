<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * A coverage dimension beyond operations, validation rules and response attributes, which are always
 * enforced. Each one is computed and reported on every run, and fails the gate only once the
 * application lists it in `contract_coverage_enforced_dimensions`.
 *
 * A runtime-only dimension has no static truth: the test runtime judges it against what a test
 * actually sent and received, so the gate can only report whether it is enforced.
 */
enum ContractCoverageDimension: string
{
    case ERROR_CODES = 'error-codes';
    case ERROR_MAPPINGS = 'error-mappings';
    case VALIDATION_EVIDENCE = 'validation-evidence';
    case REQUEST_ATTRIBUTES = 'request-attributes';
    case INCLUDES = 'includes';
    case OWNERSHIP_SCENARIOS = 'ownership-scenarios';
    case NON_EMPTY_ARRAYS = 'non-empty-arrays';
    case OPENAPI_EXAMPLE_REPLAY = 'openapi-example-replay';

    public function label(): string
    {
        return match ($this) {
            static::ERROR_CODES => 'Error codes',
            static::ERROR_MAPPINGS => 'Error mappings',
            static::VALIDATION_EVIDENCE => 'Validation evidence',
            static::REQUEST_ATTRIBUTES => 'Request attributes',
            static::INCLUDES => 'Includes',
            static::OWNERSHIP_SCENARIOS => 'Ownership scenarios',
            static::NON_EMPTY_ARRAYS => 'Non-empty arrays',
            static::OPENAPI_EXAMPLE_REPLAY => 'Example replay',
        };
    }

    public function uncoveredSectionLabel(): string
    {
        return match ($this) {
            static::ERROR_CODES => 'Uncovered error codes',
            static::ERROR_MAPPINGS => 'Undeclared mapped error codes',
            static::REQUEST_ATTRIBUTES => 'Uncovered request attributes',
            static::INCLUDES => 'Uncovered includes',
            static::OWNERSHIP_SCENARIOS => 'Uncovered ownership scenarios',
            static::OPENAPI_EXAMPLE_REPLAY => 'Resources without an example replay',
            static::VALIDATION_EVIDENCE, static::NON_EMPTY_ARRAYS => 'Uncovered ' . strtolower($this->label()),
        };
    }

    public function staleSectionLabel(): string
    {
        return match ($this) {
            static::ERROR_CODES => 'Stale error code claims',
            static::ERROR_MAPPINGS => 'Stale notAnswered codes',
            static::REQUEST_ATTRIBUTES => 'Stale request attribute claims',
            static::INCLUDES => 'Stale include claims',
            static::OWNERSHIP_SCENARIOS => 'Stale scenario claims',
            static::OPENAPI_EXAMPLE_REPLAY => 'Stale example replay claims',
            static::VALIDATION_EVIDENCE, static::NON_EMPTY_ARRAYS => 'Stale ' . strtolower($this->label()) . ' claims',
        };
    }

    /**
     * The singular noun a failure reason counts, e.g. `3 uncovered error code(s)`.
     */
    public function itemNoun(): string
    {
        return match ($this) {
            static::ERROR_CODES => 'error code',
            static::ERROR_MAPPINGS => 'mapped error code',
            static::VALIDATION_EVIDENCE => 'validation evidence',
            static::REQUEST_ATTRIBUTES => 'request attribute',
            static::INCLUDES => 'include',
            static::OWNERSHIP_SCENARIOS => 'ownership scenario',
            static::NON_EMPTY_ARRAYS => 'non-empty array',
            static::OPENAPI_EXAMPLE_REPLAY => 'example replay',
        };
    }

    public function isRuntimeOnly(): bool
    {
        return $this === static::VALIDATION_EVIDENCE || $this === static::NON_EMPTY_ARRAYS;
    }
}
