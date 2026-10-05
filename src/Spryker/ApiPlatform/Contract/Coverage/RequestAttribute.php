<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The unit of request-attribute coverage: an input operation's dispatch key paired with the path of
 * one attribute a client can write, which a successful request of some test has to send.
 */
readonly class RequestAttribute implements CoverageItem
{
    protected const string KEY_SEPARATOR = '  ';

    public function __construct(public string $dispatchKey, public string $path)
    {
    }

    public function key(): string
    {
        return $this->dispatchKey . static::KEY_SEPARATOR . $this->path;
    }
}
