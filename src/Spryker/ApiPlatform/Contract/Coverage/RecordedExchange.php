<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Everything one request of a test did that a coverage check can hold against a declaration: the
 * operation it matched, the status and error codes it answered, the attributes and includes it
 * sent, and what the validator and the access decision saw on the way. Built once per response by
 * {@see RecordedExchangeFactory}, so every runtime check reads the same record.
 */
readonly class RecordedExchange
{
    /**
     * @param \Spryker\ApiPlatform\Contract\Coverage\ApiOperation $operation Verb and uriTemplate, without a status.
     * @param array<string, mixed> $requestAttributes The decoded `data.attributes` of the request body, empty when none.
     * @param array<string> $includeRelationshipNames The first path segment of each `?include=` entry.
     * @param array<string> $errorCodes `errors[].code` of the response, empty on success.
     * @param array<string> $errorDetails `errors[].detail` of the response.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RecordedConstraintViolation> $constraintViolations
     * @param bool $isAccessDenied Whether the request was rejected by an access decision.
     * @param bool $isAuthenticated Whether the request carried credentials.
     * @param string|null $exceptionClass The exception the kernel saw on the way, before it became the error envelope.
     */
    public function __construct(
        public ApiOperation $operation,
        public int $status,
        public array $requestAttributes = [],
        public array $includeRelationshipNames = [],
        public array $errorCodes = [],
        public array $errorDetails = [],
        public array $constraintViolations = [],
        public bool $isAccessDenied = false,
        public bool $isAuthenticated = false,
        public ?string $exceptionClass = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
