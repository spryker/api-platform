<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

use Spryker\ApiPlatform\OpenApi\ErrorResponse\GlueApiErrorSchema;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\PathItemOperationAccessor;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\SchemaReferenceResolver;

/**
 * Structural assertions over a decoded OpenAPI document produced by the full factory chain: every `$ref` resolves,
 * the error schema this module registers is the only error schema and is referenced by every error response, every
 * operation carries the responses the chain adds without any resource declaring them, and the nullable `code` is
 * rendered in the wire format of the requested OpenAPI version. Meant for an integration tier that exports the
 * document of a real or representative resource set. Mix into a PHPUnit test case, such as one extending
 * {@see AbstractApiTestCase}.
 */
trait OpenApiDocumentAssertionsTrait
{
    protected const string OPENAPI_KEY_REFERENCE = '$ref';

    protected const string OPENAPI_KEY_PATHS = 'paths';

    protected const string OPENAPI_KEY_COMPONENTS = 'components';

    protected const string OPENAPI_KEY_SCHEMAS = 'schemas';

    protected const string OPENAPI_KEY_RESPONSES = 'responses';

    protected const string OPENAPI_KEY_CONTENT = 'content';

    protected const string OPENAPI_KEY_SCHEMA = 'schema';

    protected const string OPENAPI_KEY_SECURITY = 'security';

    protected const string OPENAPI_KEY_PROPERTIES = 'properties';

    protected const string OPENAPI_KEY_ITEMS = 'items';

    protected const string OPENAPI_KEY_TYPE = 'type';

    protected const string OPENAPI_KEY_NULLABLE = 'nullable';

    protected const string OPENAPI_RESPONSE_DEFAULT = 'default';

    protected const string OPENAPI_RESPONSE_BAD_REQUEST = '400';

    protected const string OPENAPI_RESPONSE_UNAUTHORIZED = '401';

    protected const string OPENAPI_RESPONSE_FORBIDDEN = '403';

    protected const int OPENAPI_FIRST_ERROR_STATUS = 400;

    protected const string GLUE_API_ERROR_PROPERTY_ERRORS = 'errors';

    protected const string GLUE_API_ERROR_PROPERTY_CODE = 'code';

    protected const string JSON_SCHEMA_TYPE_STRING = 'string';

    protected const string JSON_SCHEMA_TYPE_NULL = 'null';

    /**
     * @param array<string, mixed> $document
     */
    protected function assertOpenApiDocumentReferencesResolve(array $document): void
    {
        $schemaNames = array_keys($this->getOpenApiSchemas($document));
        $danglingReferences = [];

        foreach ($this->collectOpenApiReferences($document) as $reference) {
            $schemaName = $this->resolveOpenApiSchemaName($reference);

            if ($schemaName !== null && in_array($schemaName, $schemaNames, true)) {
                continue;
            }

            $danglingReferences[] = $reference;
        }

        $this->assertSame([], array_values(array_unique($danglingReferences)), 'Every `$ref` must point to a declared component schema.');
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string> $errorMediaTypes
     */
    protected function assertOpenApiDocumentErrorResponsesCarryTheGlueApiError(array $document, array $errorMediaTypes): void
    {
        $schemas = $this->getOpenApiSchemas($document);
        $this->assertArrayHasKey(GlueApiErrorSchema::SCHEMA_NAME, $schemas, 'The Glue error schema must be registered.');
        $this->assertSame(
            [],
            array_values(array_intersect(SchemaReferenceResolver::API_PLATFORM_ERROR_SCHEMA_NAMES, array_keys($schemas))),
            'No API Platform error schema may remain once every error response carries the Glue error schema.',
        );

        $errorResponseCount = 0;
        sort($errorMediaTypes);

        foreach ($this->getOpenApiOperations($document) as $operationKey => $operation) {
            foreach ($operation[static::OPENAPI_KEY_RESPONSES] ?? [] as $status => $response) {
                if (!$this->isOpenApiErrorResponse((string)$status)) {
                    continue;
                }

                $errorResponseCount++;
                $content = $response[static::OPENAPI_KEY_CONTENT] ?? [];
                $mediaTypes = array_keys($content);
                sort($mediaTypes);
                $this->assertSame($errorMediaTypes, $mediaTypes, sprintf('%s %s must carry every error media type.', $operationKey, $status));

                foreach ($content as $mediaType => $mediaTypeObject) {
                    $this->assertSame(
                        GlueApiErrorSchema::REFERENCE,
                        $mediaTypeObject[static::OPENAPI_KEY_SCHEMA][static::OPENAPI_KEY_REFERENCE] ?? null,
                        sprintf('%s %s %s must reference the Glue error schema.', $operationKey, $status, $mediaType),
                    );
                }
            }
        }

        $this->assertGreaterThan(0, $errorResponseCount, 'The document must contain at least one error response.');
    }

    /**
     * @param array<string, mixed> $document
     */
    protected function assertOpenApiDocumentCoversEveryOperation(array $document): void
    {
        $operationCount = 0;

        foreach ($this->getOpenApiOperations($document) as $operationKey => $operation) {
            $operationCount++;
            $responses = $operation[static::OPENAPI_KEY_RESPONSES] ?? [];
            $this->assertArrayHasKey(static::OPENAPI_RESPONSE_BAD_REQUEST, $responses, sprintf('%s must document a 400.', $operationKey));
            $this->assertArrayHasKey(static::OPENAPI_RESPONSE_DEFAULT, $responses, sprintf('%s must document a default response.', $operationKey));

            if (($operation[static::OPENAPI_KEY_SECURITY] ?? null) === []) {
                continue;
            }

            $this->assertArrayHasKey(static::OPENAPI_RESPONSE_UNAUTHORIZED, $responses, sprintf('%s is protected and must document a 401.', $operationKey));
            $this->assertArrayHasKey(static::OPENAPI_RESPONSE_FORBIDDEN, $responses, sprintf('%s is protected and must document a 403.', $operationKey));
        }

        $this->assertGreaterThan(0, $operationCount, 'The document must contain at least one operation.');
    }

    /**
     * @param array<string, mixed> $document
     */
    protected function assertOpenApiDocumentRendersNullableCode(array $document, bool $isOpenApiVersion30): void
    {
        $codeProperty = $this->getOpenApiSchemas($document)[GlueApiErrorSchema::SCHEMA_NAME][static::OPENAPI_KEY_PROPERTIES][static::GLUE_API_ERROR_PROPERTY_ERRORS][static::OPENAPI_KEY_ITEMS][static::OPENAPI_KEY_PROPERTIES][static::GLUE_API_ERROR_PROPERTY_CODE] ?? null;
        $this->assertIsArray($codeProperty, 'The Glue error schema must describe the `code` member.');

        if ($isOpenApiVersion30) {
            $this->assertSame(static::JSON_SCHEMA_TYPE_STRING, $codeProperty[static::OPENAPI_KEY_TYPE] ?? null);
            $this->assertTrue($codeProperty[static::OPENAPI_KEY_NULLABLE] ?? false, 'OpenAPI 3.0 spells a nullable member with the `nullable` keyword.');

            return;
        }

        $this->assertSame([static::JSON_SCHEMA_TYPE_STRING, static::JSON_SCHEMA_TYPE_NULL], $codeProperty[static::OPENAPI_KEY_TYPE] ?? null);
        $this->assertArrayNotHasKey(static::OPENAPI_KEY_NULLABLE, $codeProperty, 'OpenAPI 3.1 and later have no `nullable` keyword.');
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getOpenApiSchemas(array $document): array
    {
        return $document[static::OPENAPI_KEY_COMPONENTS][static::OPENAPI_KEY_SCHEMAS] ?? [];
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return iterable<string, array<string, mixed>> Keyed by `METHOD /path`.
     */
    protected function getOpenApiOperations(array $document): iterable
    {
        $httpMethods = (new PathItemOperationAccessor())->getHttpMethods();

        foreach ($document[static::OPENAPI_KEY_PATHS] ?? [] as $path => $pathItem) {
            foreach ($httpMethods as $method) {
                if (!isset($pathItem[$method])) {
                    continue;
                }

                yield sprintf('%s %s', strtoupper($method), $path) => $pathItem[$method];
            }
        }
    }

    /**
     * @return array<string>
     */
    protected function collectOpenApiReferences(mixed $node): array
    {
        if (!is_array($node)) {
            return [];
        }

        $references = [];

        foreach ($node as $key => $value) {
            if ($key === static::OPENAPI_KEY_REFERENCE && is_string($value)) {
                $references[] = $value;

                continue;
            }

            $references = array_merge($references, $this->collectOpenApiReferences($value));
        }

        return $references;
    }

    protected function resolveOpenApiSchemaName(string $reference): ?string
    {
        $prefixLength = strlen(SchemaReferenceResolver::SCHEMA_REFERENCE_PREFIX);

        if (!str_starts_with($reference, SchemaReferenceResolver::SCHEMA_REFERENCE_PREFIX) || strlen($reference) === $prefixLength) {
            return null;
        }

        return substr($reference, $prefixLength);
    }

    protected function isOpenApiErrorResponse(string $status): bool
    {
        if ($status === static::OPENAPI_RESPONSE_DEFAULT) {
            return true;
        }

        return is_numeric($status) && (int)$status >= static::OPENAPI_FIRST_ERROR_STATUS;
    }
}
