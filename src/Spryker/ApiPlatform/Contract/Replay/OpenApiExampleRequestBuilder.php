<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Replay;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader;

/**
 * Builds, by reflection and without a kernel, the request a client would send for each servable
 * operation of a generated resource if it followed the published examples: the body from each
 * writable property's `openapiContext.example` - the values the JSON schema publishes - and the
 * query from the documented parameter examples. A replay of it must never answer 5xx; a 404 or a
 * 422 for a placeholder value is a correct answer.
 */
class OpenApiExampleRequestBuilder
{
    protected const string PATTERN_URI_VARIABLE = '/\{([^}]+)\}/';

    protected const string OPENAPI_KEY_EXAMPLE = 'example';

    protected const string PARAMETER_IN_QUERY = 'query';

    protected const string REQUIRED_PARAMETER_PLACEHOLDER = '1';

    protected const string JSON_API_KEY_DATA = 'data';

    protected const string JSON_API_KEY_TYPE = 'type';

    protected const string JSON_API_KEY_ATTRIBUTES = 'attributes';

    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\RequestAttributeTruthCollector::EXTRA_PROPERTY_WRITABLE_ON
     */
    protected const string EXTRA_PROPERTY_WRITABLE_ON = 'writableOn';

    protected const string RELATIONSHIP_DATA_SUFFIX = 'RelationshipData';

    /**
     * @var array<string>
     */
    protected const array INPUT_VERBS = ['POST', 'PUT', 'PATCH'];

    public function __construct(protected SchemaTruthLoader $schemaTruthLoader)
    {
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<\Spryker\ApiPlatform\Contract\Replay\ReplayableRequest>
     */
    public function build(string $resourceClass): array
    {
        $reflectionClass = new ReflectionClass($resourceClass);
        $requests = [];

        foreach ($this->schemaTruthLoader->servableOperationsOf($resourceClass) as $servableOperation) {
            $apiOperation = $servableOperation['apiOperation'];
            $httpOperation = $servableOperation['httpOperation'];
            $isInput = in_array(strtoupper($apiOperation->verb), static::INPUT_VERBS, true);

            $requests[] = new ReplayableRequest(
                $apiOperation,
                $isInput ? $this->buildBody($reflectionClass, $servableOperation['shortName'], (new ReflectionClass($httpOperation))->getShortName()) : null,
                $this->buildQuery($httpOperation),
                $this->uriVariableNames($apiOperation->uriTemplate),
            );
        }

        return $requests;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     *
     * @return array<string, mixed>
     */
    protected function buildBody(ReflectionClass $reflectionClass, string $shortName, string $operationType): array
    {
        return [
        static::JSON_API_KEY_DATA => [
            static::JSON_API_KEY_TYPE => $shortName,
            static::JSON_API_KEY_ATTRIBUTES => $this->buildAttributes($reflectionClass, $operationType, []),
        ]];
    }

    /**
     * A nested value object is built from its own children's examples, as the JSON schema nests
     * them.
     *
     * @param \ReflectionClass<object> $class
     * @param array<class-string, true> $visitedClasses
     *
     * @return array<string, mixed>
     */
    protected function buildAttributes(ReflectionClass $class, string $operationType, array $visitedClasses): array
    {
        $attributes = [];

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $apiProperty = $this->apiProperty($property);
            if ($this->isSkipped($property, $apiProperty, $operationType)) {
                continue;
            }

            $nestedClass = $this->nestedObjectClass($property);
            if ($nestedClass !== null && !isset($visitedClasses[$nestedClass->getName()])) {
                $nested = $this->buildAttributes($nestedClass, $operationType, $visitedClasses + [$nestedClass->getName() => true]);
                if ($nested !== []) {
                    $attributes[$property->getName()] = $nested;
                }

                continue;
            }

            $openapiContext = $apiProperty?->getOpenapiContext() ?? [];
            if (array_key_exists(static::OPENAPI_KEY_EXAMPLE, $openapiContext)) {
                $attributes[$property->getName()] = $openapiContext[static::OPENAPI_KEY_EXAMPLE];
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    protected function buildQuery(HttpOperation $httpOperation): array
    {
        $openapi = $httpOperation->getOpenapi();
        if (!$openapi instanceof OpenApiOperation) {
            return [];
        }

        $query = [];
        foreach ($openapi->getParameters() ?? [] as $parameter) {
            if ($parameter->getIn() !== static::PARAMETER_IN_QUERY) {
                continue;
            }

            $example = $parameter->getExample();
            if ($example === null && $parameter->getExamples() !== null && $parameter->getExamples()->count() > 0) {
                $firstExample = array_values($parameter->getExamples()->getArrayCopy())[0];
                $example = is_object($firstExample) && method_exists($firstExample, 'getValue') ? $firstExample->getValue() : ($firstExample['value'] ?? null);
            }

            if ($example !== null) {
                $query[$parameter->getName()] = is_scalar($example) ? (string)$example : (string)json_encode($example);

                continue;
            }

            if ($parameter->getRequired()) {
                $query[$parameter->getName()] = static::REQUIRED_PARAMETER_PLACEHOLDER;
            }
        }

        return $query;
    }

    /**
     * @return array<string>
     */
    protected function uriVariableNames(string $uriTemplate): array
    {
        preg_match_all(static::PATTERN_URI_VARIABLE, $uriTemplate, $matches);

        return $matches[1];
    }

    protected function apiProperty(ReflectionProperty $property): ?ApiProperty
    {
        $attributes = $property->getAttributes(ApiProperty::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    protected function isSkipped(ReflectionProperty $property, ?ApiProperty $apiProperty, string $operationType): bool
    {
        if (str_ends_with($property->getName(), static::RELATIONSHIP_DATA_SUFFIX)) {
            return true;
        }

        if ($apiProperty === null) {
            return false;
        }

        if ($apiProperty->isWritable() === false || $apiProperty->isIdentifier() === true || $apiProperty->getUriTemplate() !== null) {
            return true;
        }

        $writableOn = $apiProperty->getExtraProperties()[static::EXTRA_PROPERTY_WRITABLE_ON] ?? null;

        return is_array($writableOn) && !in_array($operationType, $writableOn, true);
    }

    /**
     * @return \ReflectionClass<object>|null
     */
    protected function nestedObjectClass(ReflectionProperty $property): ?ReflectionClass
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName())) {
            return null;
        }

        $reflectionClass = new ReflectionClass($type->getName());

        return !$reflectionClass->isInternal() && $reflectionClass->getAttributes(ApiResource::class) === [] ? $reflectionClass : null;
    }
}
