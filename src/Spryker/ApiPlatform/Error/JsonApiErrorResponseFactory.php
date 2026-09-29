<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Error;

use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ProviderNotFoundErrorResolver;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

/**
 * Builds every JSON:API error response the Glue exception subscriber answers with.
 */
class JsonApiErrorResponseFactory
{
    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    protected const string HEADER_ALLOW = 'Allow';

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const string ERROR_META_KEY_EXCEPTION = 'exception';

    protected const string ERROR_META_KEY_FILE = 'file';

    protected const string ERROR_META_KEY_LINE = 'line';

    protected const string ERROR_META_KEY_TRACE = 'trace';

    public function __construct(
        protected ProviderNotFoundErrorResolver $providerNotFoundErrorResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function createJsonApiResponse(array $data, int $statusCode, array $headers = []): JsonResponse
    {
        $response = new JsonResponse(null, $statusCode, $headers);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->setData($data);
        $response->headers->set('Content-Type', static::CONTENT_TYPE_JSON_API);

        return $response;
    }

    public function createGlueApiErrorResponse(GlueApiException $exception): JsonResponse
    {
        $errors = $exception->getErrors();
        foreach ($errors as &$errorItem) {
            if (!isset($errorItem['message'])) {
                $errorItem['message'] = (string)$errorItem['detail'];
            }

            if (array_key_exists('code', $errorItem) && $errorItem['code'] === null) {
                unset($errorItem['code']);
            }
        }

        if ($errors === []) {
            $error = [];
            $errorCode = $exception->getErrorCode();

            if ($errorCode !== null && $errorCode !== '') {
                $error['code'] = $errorCode;
            }

            $error['status'] = $exception->getStatusCode();
            $error['detail'] = $exception->getMessage();
            $error['message'] = $exception->getMessage();

            $errors = [$error];
        }

        return $this->createJsonApiResponse(['errors' => $errors], $exception->getStatusCode());
    }

    /**
     * One 422 error with the Spryker validation code 901 per detail, the legacy REST API validation format.
     *
     * @param array<string> $details
     */
    public function createValidationErrorResponse(array $details): JsonResponse
    {
        $errors = [];

        foreach ($details as $detail) {
            $errors[] = [
                'code' => static::ERROR_CODE_VALIDATION,
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => $detail,
            ];
        }

        return $this->createJsonApiResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function createBadRequestResponse(string $detail): JsonResponse
    {
        return $this->createJsonApiResponse(
            [
                'errors' => [
                    [
                        'code' => (string)Response::HTTP_BAD_REQUEST,
                        'status' => Response::HTTP_BAD_REQUEST,
                        'detail' => $detail,
                    ],
                ],
            ],
            Response::HTTP_BAD_REQUEST,
        );
    }

    public function createMethodNotAllowedResponse(MethodNotAllowedHttpException $exception, Request $request): JsonResponse
    {
        return $this->createHttpExceptionResponse(
            new MethodNotAllowedHttpException($this->extractAllowedMethods($exception)),
            $request,
        );
    }

    public function createHttpExceptionResponse(HttpExceptionInterface $exception, Request $request): JsonResponse
    {
        $statusCode = $exception->getStatusCode();
        $message = $exception->getMessage();
        $detail = ($message !== '' && $message !== (Response::$statusTexts[$statusCode] ?? ''))
            ? $message
            : (Response::$statusTexts[$statusCode] ?? 'Error');

        $error = [
            'status' => $statusCode,
            'detail' => $detail,
        ];

        // For 404 responses, check if the resource class has a domain-specific
        // "not found" error code and message defined as class constants.
        if ($statusCode === Response::HTTP_NOT_FOUND) {
            $resourceClass = (string)$request->attributes->get(RequestAttribute::API_RESOURCE_CLASS, '');
            $notFoundError = $this->providerNotFoundErrorResolver->resolveByResourceClass($resourceClass);

            if ($notFoundError !== null) {
                $error = $notFoundError;
            }
        }

        return $this->createJsonApiResponse(['errors' => [$error]], $statusCode, $exception->getHeaders());
    }

    /**
     * BC: the uncaught last-resort fallback keeps the legacy `text/html` + "Internal Server Error"
     * shape, which consumers use to detect 500s by failed JSON parsing. Handled errors still return
     * the JSON:API envelope.
     */
    public function createInternalServerErrorResponse(): Response
    {
        return new Response(
            Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR] ?? 'Internal Server Error',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function createDebugInternalServerErrorResponse(Throwable $throwable): JsonResponse
    {
        return $this->createJsonApiResponse(
            [
                'errors' => [
                    [
                        'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                        'title' => $throwable::class,
                        'detail' => $throwable->getMessage(),
                        'meta' => [
                            static::ERROR_META_KEY_EXCEPTION => $throwable::class,
                            static::ERROR_META_KEY_FILE => $throwable->getFile(),
                            static::ERROR_META_KEY_LINE => $throwable->getLine(),
                            static::ERROR_META_KEY_TRACE => explode(PHP_EOL, $throwable->getTraceAsString()),
                        ],
                    ],
                ],
            ],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    /**
     * @return array<string>
     */
    protected function extractAllowedMethods(MethodNotAllowedHttpException $exception): array
    {
        $allowHeader = $exception->getHeaders()[static::HEADER_ALLOW] ?? '';

        if (!is_string($allowHeader) || $allowHeader === '') {
            return [];
        }

        return array_map('trim', explode(',', $allowHeader));
    }
}
