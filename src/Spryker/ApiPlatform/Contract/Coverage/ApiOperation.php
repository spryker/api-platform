<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The unit of operation coverage: an HTTP verb, a canonical OpenAPI uriTemplate and optionally the
 * declared error response the entry stands for, comparable by {@see ApiOperation::key()} across the
 * truth set, the annotations and the runtime recording. A null status is the operation's success
 * response.
 */
readonly class ApiOperation
{
    public function __construct(
        public string $verb,
        public string $uriTemplate,
        public ?int $status = null,
        public bool $addressesCollection = false,
    ) {
    }

    public function key(): string
    {
        return $this->dispatchKey() . ($this->status !== null ? ' ' . $this->status : '');
    }

    /**
     * The status-less identity — what the kernel-request recorder can observe. A declaration for an
     * error response is runtime-verified by the operation being dispatched; the response status
     * itself is the test body's assertion.
     */
    public function dispatchKey(): string
    {
        return strtoupper($this->verb) . ' ' . $this->uriTemplate;
    }
}
