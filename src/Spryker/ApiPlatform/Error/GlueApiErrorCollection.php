<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Error;

use Spryker\ApiPlatform\Exception\GlueApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gathers every reason a request was rejected so a resource can keep checking after the first one
 * and answer with all of them at once, instead of refusing on the first problem and making the
 * caller discover the rest one round trip at a time.
 *
 * Use it directly with addError(), or extend it with one method per rejection reason so call sites
 * read as the reason rather than as a code and a message:
 *
 * ```php
 * class OrdersBackendErrorCollection extends GlueApiErrorCollection
 * {
 *     public function addOrderNotFound(string $reference): static
 *     {
 *         return $this->addError('4001', Response::HTTP_NOT_FOUND, sprintf('Order "%s" was not found.', $reference));
 *     }
 * }
 * ```
 *
 * A new rejection reason is then a new method here, and no call site changes shape.
 */
class GlueApiErrorCollection
{
    /**
     * @var array<int, array{code: string, status: int, detail: string}>
     */
    protected array $errors = [];

    public function addError(string $code, int $statusCode, string $detail): static
    {
        $this->errors[] = $this->buildError($code, $statusCode, $detail);

        return $this;
    }

    /**
     * @return array{code: string, status: int, detail: string}
     */
    protected function buildError(string $code, int $statusCode, string $detail): array
    {
        return [
            'code' => $code,
            'status' => $statusCode,
            'detail' => $detail,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    public function count(): int
    {
        return count($this->errors);
    }

    /**
     * @return array<int, array{code: string, status: int, detail: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toGlueApiException(): GlueApiException
    {
        if ($this->errors === []) {
            throw new GlueApiException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                null,
                'No error was gathered, so there is nothing to answer with. Guard the throw with isEmpty().',
            );
        }

        $glueApiException = new GlueApiException(
            $this->resolveStatusCode(),
            $this->errors[0]['code'],
            $this->errors[0]['detail'],
        );

        if (count($this->errors) > 1) {
            $glueApiException->setErrors($this->errors);
        }

        return $glueApiException;
    }

    protected function resolveStatusCode(): int
    {
        $statusCodes = array_unique(array_column($this->errors, 'status'));

        if (count($statusCodes) === 1) {
            return (int)reset($statusCodes);
        }

        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
