<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * One entry of a module's Zed-error to REST-error mapping: the error the domain layer raises and the
 * code and status the API answers it with. A `notAnswered` claim that names a code the mapping does
 * not contain is reported with the {@see ErrorMappingEntry::NOT_ANSWERED} identifier.
 */
readonly class ErrorMappingEntry implements CoverageItem
{
    public const string NOT_ANSWERED = 'notAnswered';

    protected const string KEY_SEPARATOR = '  ';

    public function __construct(
        public string $source,
        public string $errorIdentifier,
        public string $code,
        public ?int $status,
    ) {
    }

    public function key(): string
    {
        return $this->source . static::KEY_SEPARATOR . $this->errorIdentifier . static::KEY_SEPARATOR
            . ($this->status !== null ? $this->status . ' ' : '') . 'code ' . $this->code;
    }
}
