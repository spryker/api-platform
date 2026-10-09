<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * An HTTP exception an operation's processor or provider can throw whose status cannot be read
 * from source, because its constructor requires an argument. It is never covered: listing the class
 * in {@see ThrownStatusAnalyzer::STATUS_BY_EXCEPTION_CLASS} turns it into a {@see ThrownStatus}.
 */
readonly class UnreadableThrownStatus implements CoverageItem
{
    protected const string MARKER_UNREADABLE = 'unreadable';

    /**
     * @param class-string $exceptionClassName
     */
    public function __construct(public string $dispatchKey, public string $exceptionClassName)
    {
    }

    public function key(): string
    {
        return $this->dispatchKey . ' ' . static::MARKER_UNREADABLE . ' ' . $this->exceptionClassName;
    }
}
