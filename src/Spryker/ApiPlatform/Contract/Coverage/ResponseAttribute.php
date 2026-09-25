<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The unit of response-attribute coverage: an operation's dispatch key paired with the normalized
 * path of one of its response attributes, comparable by {@see ResponseAttribute::key()} across the
 * truth set, the annotations and the runtime recording — the response-attribute counterpart to how
 * {@see ApiOperation::key()} compares operations.
 */
readonly class ResponseAttribute
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
