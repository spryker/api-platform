<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ArrayObject;

/**
 * Attaches to every 4xx/5xx and `default` response of an operation the {@see GlueApiErrorSchema} under each error
 * format, the status-specific examples and, where the resource YAML declared no description or API Platform's
 * factory left its own text, the Glue description. Content an author wrote by hand — anything not referencing API
 * Platform's own error schemas — is kept as is.
 */
class ErrorResponseDocumenter
{
    public const string RESPONSE_KEY_DEFAULT = 'default';

    /**
     * @see \ApiPlatform\OpenApi\Factory\OpenApiFactory
     * @see \ApiPlatform\Validator\Exception\ValidationException
     * @see \ApiPlatform\ApiResource\Error::getTitle()
     *
     * @var array<string>
     */
    protected const array API_PLATFORM_DESCRIPTIONS = ['Invalid input', 'Forbidden', 'Not found', 'Unprocessable entity', 'An error occurred', 'Unexpected error'];

    protected const int FIRST_ERROR_STATUS = 400;

    /**
     * @var array<string>
     */
    protected array $errorMimeTypes;

    /**
     * @param array<string, array<string>> $errorFormats
     */
    public function __construct(
        protected readonly ErrorResponseBuilder $errorResponseBuilder,
        protected readonly SchemaReferenceResolver $schemaReferenceResolver,
        array $errorFormats,
    ) {
        $this->errorMimeTypes = array_values(array_unique(array_merge(...array_values($errorFormats))));
    }

    /**
     * @param array<string> $requiredAttributes
     */
    public function document(Operation $operation, ?HttpOperation $httpOperation, array $requiredAttributes): Operation
    {
        foreach ($operation->getResponses() ?? [] as $status => $response) {
            if (!$this->isErrorResponse($status) || !$this->needsContent($response)) {
                continue;
            }

            $operation = $operation->withResponse($status, $this->documentResponse($status, $response, $httpOperation, $requiredAttributes));
        }

        return $operation;
    }

    protected function isErrorResponse(int|string $status): bool
    {
        if ($status === static::RESPONSE_KEY_DEFAULT) {
            return true;
        }

        return is_numeric($status) && (int)$status >= static::FIRST_ERROR_STATUS;
    }

    protected function needsContent(Response $response): bool
    {
        $content = $response->getContent();

        if ($content === null || count($content) === 0) {
            return true;
        }

        foreach ($content as $mediaType) {
            if ($this->schemaReferenceResolver->isApiPlatformErrorSchema($mediaType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string> $requiredAttributes
     */
    protected function documentResponse(int|string $status, Response $response, ?HttpOperation $httpOperation, array $requiredAttributes): Response
    {
        $isDefault = $status === static::RESPONSE_KEY_DEFAULT;
        $examples = $isDefault
            ? $this->errorResponseBuilder->buildDefaultExamples($httpOperation)
            : $this->errorResponseBuilder->buildExamples((int)$status, $httpOperation, $requiredAttributes);

        /** @var \ArrayObject<string, \ApiPlatform\OpenApi\Model\MediaType> $content */
        $content = new ArrayObject();

        foreach ($this->errorMimeTypes as $mimeType) {
            $content[$mimeType] = new MediaType(
                schema: $this->schemaReferenceResolver->createSchemaReference(GlueApiErrorSchema::REFERENCE),
                examples: $examples->count() > 0 ? $examples : null,
            );
        }

        $response = $response->withContent($content);

        if ($this->isMissingDescription($response)) {
            $response = $response->withDescription(
                $isDefault
                    ? $this->errorResponseBuilder->buildDefaultDescription($httpOperation)
                    : $this->errorResponseBuilder->buildDescription((int)$status, $httpOperation),
            );
        }

        return $response;
    }

    protected function isMissingDescription(Response $response): bool
    {
        $description = $response->getDescription();

        return $description === null || $description === '' || in_array($description, static::API_PLATFORM_DESCRIPTIONS, true);
    }
}
