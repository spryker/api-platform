<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\JsonSchema;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ArrayObject;

/**
 * Builds the request-body schema of a JSON:API write operation.
 *
 * API Platform does not produce a usable one. {@see \ApiPlatform\JsonApi\JsonSchema\SchemaFactory::buildSchema()}
 * delegates to the generic factory with a hardcoded `'json'` format, which trips
 * `$isJsonMergePatch = 'json' === $format && 'PATCH' === $method && Schema::TYPE_INPUT === $type` in
 * {@see \ApiPlatform\JsonSchema\SchemaFactory}. A PATCH body is therefore described as a JSON merge
 * patch — flat, no `data` envelope — and named `*.jsonMergePatch`, while a POST body is described by
 * the flat resource schema. Sending either as documented answers `400 Post data is invalid.`, because
 * both media types of the `jsonapi` format are read by the JSON:API denormalizer, which requires the
 * envelope. Upstream issue: https://github.com/api-platform/core/issues/2635
 *
 * This factory wraps the flat schema the generic factory produces — which already honours the
 * operation's denormalization groups and its required list — into the JSON:API document, and names
 * the definition after the operation so POST, PUT and PATCH bodies stay distinct from each other and
 * from the response schema.
 */
class JsonApiInputSchemaFactory implements SchemaFactoryInterface
{
    protected const string FORMAT_JSON_API = 'jsonapi';

    protected const string FORMAT_JSON = 'json';

    protected const string METHOD_POST = 'POST';

    protected const string METHOD_PUT = 'PUT';

    protected const string METHOD_PATCH = 'PATCH';

    /**
     * The identifier is part of the resource object of an update, and assigned by the server on
     * create.
     *
     * @var array<string>
     */
    protected const array METHODS_WITH_IDENTIFIER = [self::METHOD_PUT, self::METHOD_PATCH];

    /**
     * @var array<string, string>
     */
    protected const array DEFINITION_SUFFIX_BY_METHOD = [
        self::METHOD_POST => '.jsonapi-post',
        self::METHOD_PUT => '.jsonapi-put',
        self::METHOD_PATCH => '.jsonapi-patch',
    ];

    protected const string PROPERTY_DATA = 'data';

    protected const string PROPERTY_TYPE = 'type';

    protected const string PROPERTY_ID = 'id';

    protected const string PROPERTY_ATTRIBUTES = 'attributes';

    protected const string SCHEMA_KEY_PROPERTIES = 'properties';

    protected const string SCHEMA_KEY_REQUIRED = 'required';

    protected const string SCHEMA_KEY_READ_ONLY = 'readOnly';

    protected const string SCHEMA_KEY_DESCRIPTION = 'description';

    protected const string SCHEMA_KEY_REF = '$ref';

    protected const string TYPE_OBJECT = 'object';

    protected const string TYPE_STRING = 'string';

    public function __construct(protected readonly SchemaFactoryInterface $decorated)
    {
    }

    /**
     * @param array<string, mixed>|null $serializerContext
     */
    public function buildSchema(
        string $className,
        string $format = self::FORMAT_JSON,
        string $type = Schema::TYPE_OUTPUT,
        ?Operation $operation = null,
        ?Schema $schema = null,
        ?array $serializerContext = null,
        bool $forceCollection = false
    ): Schema {
        if (!$operation instanceof HttpOperation || !$this->isJsonApiWriteInput($format, $type, $operation)) {
            return $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);
        }

        $flatSchema = $this->decorated->buildSchema($className, static::FORMAT_JSON, $type, $operation, $schema, $serializerContext, $forceCollection);
        $flatDefinitionKey = $flatSchema->getRootDefinitionKey();

        if ($flatDefinitionKey === null) {
            return $flatSchema;
        }

        return $this->wrapInJsonApiDocument($flatSchema, $flatDefinitionKey, $operation);
    }

    /**
     * True when this factory owns the schema: the request body of a JSON:API write operation.
     * Everything else — a read schema, another format, a non-write operation — belongs to the
     * decorated factory.
     */
    protected function isJsonApiWriteInput(string $format, string $type, HttpOperation $operation): bool
    {
        return $format === static::FORMAT_JSON_API
            && $type === Schema::TYPE_INPUT
            && isset(static::DEFINITION_SUFFIX_BY_METHOD[$operation->getMethod()]);
    }

    protected function wrapInJsonApiDocument(Schema $flatSchema, string $flatDefinitionKey, HttpOperation $operation): Schema
    {
        $method = $operation->getMethod();
        $definitions = $flatSchema->getDefinitions();
        $flatDefinition = $this->toArray($definitions[$flatDefinitionKey] ?? []);
        $resourceShortName = (string)$operation->getShortName();
        $definitionName = $resourceShortName . static::DEFINITION_SUFFIX_BY_METHOD[$method];

        $definitions[$definitionName] = $this->buildDocumentDefinition($flatDefinition, $resourceShortName, $method);
        unset($definitions[$flatDefinitionKey]);
        $flatSchema->setDefinitions($definitions);
        $flatSchema[static::SCHEMA_KEY_REF] = $this->buildRef($flatSchema, $definitionName);

        return $flatSchema;
    }

    /**
     * @param array<string, mixed> $flatDefinition
     *
     * @return array<string, mixed>
     */
    protected function buildDocumentDefinition(array $flatDefinition, string $resourceShortName, string $method): array
    {
        $resourceObjectProperties = [
            static::PROPERTY_TYPE => ['type' => static::TYPE_STRING, 'example' => $resourceShortName],
        ];
        $resourceObjectRequired = [static::PROPERTY_TYPE];

        if (in_array($method, static::METHODS_WITH_IDENTIFIER, true)) {
            $resourceObjectProperties[static::PROPERTY_ID] = ['type' => static::TYPE_STRING];
            $resourceObjectRequired[] = static::PROPERTY_ID;
        }

        $attributesSchema = $this->buildAttributesSchema($flatDefinition);

        if ($attributesSchema !== null) {
            $resourceObjectProperties[static::PROPERTY_ATTRIBUTES] = $attributesSchema;
        }

        return [
            'type' => static::TYPE_OBJECT,
            static::SCHEMA_KEY_PROPERTIES => [
                static::PROPERTY_DATA => [
                    'type' => static::TYPE_OBJECT,
                    static::SCHEMA_KEY_PROPERTIES => $resourceObjectProperties,
                    static::SCHEMA_KEY_REQUIRED => $resourceObjectRequired,
                ],
            ],
            static::SCHEMA_KEY_REQUIRED => [static::PROPERTY_DATA],
        ];
    }

    /**
     * The flat schema becomes the attributes object, minus the properties it marks `readOnly`: OpenAPI
     * defines those as response-only, so listing them in a request body describes fields the endpoint
     * will not accept. Its `required` list is carried over, narrowed to the properties that survive,
     * so Swagger marks the same fields required as the validator enforces.
     *
     * Returns null when nothing is writable — an action endpoint addressed by its URI, whose body is
     * the bare JSON:API envelope.
     *
     * @param array<string, mixed> $flatDefinition
     *
     * @return array<string, mixed>|null
     */
    protected function buildAttributesSchema(array $flatDefinition): ?array
    {
        $properties = $this->removeReadOnlyProperties($this->toArray($flatDefinition[static::SCHEMA_KEY_PROPERTIES] ?? []));

        if ($properties === []) {
            return null;
        }

        $attributes = [
            'type' => static::TYPE_OBJECT,
            static::SCHEMA_KEY_PROPERTIES => $properties,
        ];

        $required = array_values(array_intersect(
            $this->toArray($flatDefinition[static::SCHEMA_KEY_REQUIRED] ?? []),
            array_keys($properties),
        ));

        if ($required !== []) {
            $attributes[static::SCHEMA_KEY_REQUIRED] = $required;
        }

        if (isset($flatDefinition[static::SCHEMA_KEY_DESCRIPTION])) {
            $attributes[static::SCHEMA_KEY_DESCRIPTION] = $flatDefinition[static::SCHEMA_KEY_DESCRIPTION];
        }

        return $attributes;
    }

    /**
     * @param array<array-key, mixed> $properties
     *
     * @return array<array-key, mixed>
     */
    protected function removeReadOnlyProperties(array $properties): array
    {
        foreach ($properties as $propertyName => $property) {
            if (($this->toArray($property)[static::SCHEMA_KEY_READ_ONLY] ?? false) !== true) {
                continue;
            }

            unset($properties[$propertyName]);
        }

        return $properties;
    }

    protected function buildRef(Schema $schema, string $definitionName): string
    {
        $prefix = $schema->getVersion() === Schema::VERSION_OPENAPI ? '#/components/schemas/' : '#/definitions/';

        return $prefix . $definitionName;
    }

    /**
     * @param \ArrayObject<array-key, mixed>|mixed|array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    protected function toArray(mixed $value): array
    {
        if ($value instanceof ArrayObject) {
            return $value->getArrayCopy();
        }

        return is_array($value) ? $value : [];
    }
}
