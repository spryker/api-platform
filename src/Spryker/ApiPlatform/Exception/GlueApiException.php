<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * HTTP exception for JSON:API error responses. The Glue error code is optional: resources migrated
 * from the legacy Glue REST API keep their legacy codes, new resources answer with status and detail only.
 */
class GlueApiException extends HttpException
{
    /**
     * @var array<int, array{code?: string|null, status: int, detail: string, message?: string}>
     */
    protected array $errors = [];

    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        int $statusCode,
        protected ?string $errorCode = null,
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $message, $previous, $headers);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * @param array<int, array{code?: string|null, status: int, detail: string, message?: string}> $errors
     */
    public function setErrors(array $errors): static
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * @return array<int, array{code?: string|null, status: int, detail: string, message?: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
