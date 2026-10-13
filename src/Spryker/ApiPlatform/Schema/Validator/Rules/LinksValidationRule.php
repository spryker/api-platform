<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

use Spryker\ApiPlatform\Schema\LinkSchemaKeys;

class LinksValidationRule implements ValidationRuleInterface
{
    protected const string SCHEMA_KEY_PROPERTIES = 'properties';

    protected const string SCHEMA_KEY_OPERATIONS = 'operations';

    protected const string OPERATION_KEY_TYPE = 'type';

    protected const string OPERATION_KEY_NAME = 'name';

    /**
     * @var non-empty-string
     */
    protected const string PROPERTY_PATH_SEGMENT_PATTERN = '/[.\[]/';

    /**
     * @var array<int, string>
     */
    protected const array ALLOWED_LINK_KEYS = [
        LinkSchemaKeys::LINK_ROUTE,
        LinkSchemaKeys::LINK_DESCRIPTION,
        LinkSchemaKeys::LINK_PARAMETERS,
        LinkSchemaKeys::LINK_CONDITION,
        LinkSchemaKeys::LINK_OPERATIONS,
    ];

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        if (!isset($schema[LinkSchemaKeys::LINKS])) {
            return [];
        }

        $sourceFile = (string)($schema['sourceFile'] ?? 'unknown file');

        if (!is_array($schema[LinkSchemaKeys::LINKS])) {
            return [sprintf('links must be a list of link definitions in %s', $sourceFile)];
        }

        $errors = [];

        foreach ($schema[LinkSchemaKeys::LINKS] as $linkName => $link) {
            if (!is_array($link)) {
                $errors[] = sprintf('Link "%s" must be an array in %s', $linkName, $sourceFile);

                continue;
            }

            $errors = array_merge($errors, $this->validateLink((string)$linkName, $link, $sourceFile));
            $errors = array_merge($errors, $this->validateReferences((string)$linkName, $link, $schema, $sourceFile));
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateReferences(string $linkName, array $link, array $schema, string $sourceFile): array
    {
        $errors = [];
        $properties = $schema[static::SCHEMA_KEY_PROPERTIES] ?? null;

        if (is_array($properties)) {
            $errors = array_merge($errors, $this->validatePropertyReferences($linkName, $link, array_map('strval', array_keys($properties)), $sourceFile));
        }

        $operations = $schema[static::SCHEMA_KEY_OPERATIONS] ?? null;

        if (is_array($operations)) {
            $errors = array_merge($errors, $this->validateOperationReferences($linkName, $link, $operations, $sourceFile));
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $link
     * @param array<int, string> $declaredProperties
     *
     * @return array<string>
     */
    protected function validatePropertyReferences(string $linkName, array $link, array $declaredProperties, string $sourceFile): array
    {
        $errors = [];
        $conditionProperty = $link[LinkSchemaKeys::LINK_CONDITION][LinkSchemaKeys::CONDITION_PROPERTY] ?? null;

        if (is_string($conditionProperty) && $conditionProperty !== '' && !$this->isDeclaredProperty($conditionProperty, $declaredProperties)) {
            $errors[] = sprintf(
                'Link "%s" condition refers to the undeclared property "%s" in %s',
                $linkName,
                $conditionProperty,
                $sourceFile,
            );
        }

        $parameters = $link[LinkSchemaKeys::LINK_PARAMETERS] ?? null;

        if (!is_array($parameters)) {
            return $errors;
        }

        foreach ($parameters as $routeParameter => $propertyPath) {
            if (!is_string($propertyPath) || $propertyPath === '' || $this->isDeclaredProperty($propertyPath, $declaredProperties)) {
                continue;
            }

            $errors[] = sprintf(
                'Link "%s" maps route parameter "%s" to the undeclared property "%s" in %s',
                $linkName,
                $routeParameter,
                $propertyPath,
                $sourceFile,
            );
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $link
     * @param array<int|string, mixed> $operations
     *
     * @return array<string>
     */
    protected function validateOperationReferences(string $linkName, array $link, array $operations, string $sourceFile): array
    {
        $linkOperations = $link[LinkSchemaKeys::LINK_OPERATIONS] ?? null;

        if (!is_array($linkOperations)) {
            return [];
        }

        $declaredOperations = [];

        foreach ($operations as $operationKey => $operation) {
            if (!is_array($operation)) {
                continue;
            }

            foreach ([static::OPERATION_KEY_TYPE, static::OPERATION_KEY_NAME] as $key) {
                if (is_string($operation[$key] ?? null)) {
                    $declaredOperations[] = $operation[$key];
                }
            }

            if (is_string($operationKey)) {
                $declaredOperations[] = $operationKey;
            }
        }

        $errors = [];

        foreach ($linkOperations as $linkOperation) {
            if (!is_string($linkOperation) || $linkOperation === '' || in_array($linkOperation, $declaredOperations, true)) {
                continue;
            }

            $errors[] = sprintf(
                'Link "%s" is scoped to "%s", which is neither an operation type nor an operation name of the resource in %s',
                $linkName,
                $linkOperation,
                $sourceFile,
            );
        }

        return $errors;
    }

    /**
     * @param array<int, string> $declaredProperties
     */
    protected function isDeclaredProperty(string $propertyPath, array $declaredProperties): bool
    {
        $segments = preg_split(static::PROPERTY_PATH_SEGMENT_PATTERN, $propertyPath);

        return in_array((string)($segments[0] ?? ''), $declaredProperties, true);
    }

    /**
     * @param array<string, mixed> $link
     *
     * @return array<string>
     */
    protected function validateLink(string $linkName, array $link, string $sourceFile): array
    {
        $errors = [];

        $unknownKeys = array_diff(array_keys($link), static::ALLOWED_LINK_KEYS);

        if ($unknownKeys !== []) {
            $errors[] = sprintf(
                'Link "%s" declares unknown key(s) "%s"; allowed keys are "%s" in %s',
                $linkName,
                implode('", "', array_map('strval', $unknownKeys)),
                implode('", "', static::ALLOWED_LINK_KEYS),
                $sourceFile,
            );
        }

        if (!isset($link[LinkSchemaKeys::LINK_ROUTE]) || !is_string($link[LinkSchemaKeys::LINK_ROUTE]) || $link[LinkSchemaKeys::LINK_ROUTE] === '') {
            $errors[] = sprintf('Link "%s" must declare a non-empty "route" in %s', $linkName, $sourceFile);
        }

        if (isset($link[LinkSchemaKeys::LINK_DESCRIPTION]) && !is_string($link[LinkSchemaKeys::LINK_DESCRIPTION])) {
            $errors[] = sprintf('Link "%s" must declare "description" as a string in %s', $linkName, $sourceFile);
        }

        $errors = array_merge($errors, $this->validateParameters($linkName, $link, $sourceFile));
        $errors = array_merge($errors, $this->validateCondition($linkName, $link, $sourceFile));

        return array_merge($errors, $this->validateOperations($linkName, $link, $sourceFile));
    }

    /**
     * @param array<string, mixed> $link
     *
     * @return array<string>
     */
    protected function validateParameters(string $linkName, array $link, string $sourceFile): array
    {
        if (!isset($link[LinkSchemaKeys::LINK_PARAMETERS])) {
            return [];
        }

        if (!is_array($link[LinkSchemaKeys::LINK_PARAMETERS])) {
            return [sprintf(
                'Link "%s" must declare "parameters" as a map of route parameter to property path in %s',
                $linkName,
                $sourceFile,
            )];
        }

        foreach ($link[LinkSchemaKeys::LINK_PARAMETERS] as $routeParameter => $propertyPath) {
            if (is_string($routeParameter) && is_string($propertyPath) && $propertyPath !== '') {
                continue;
            }

            return [sprintf(
                'Link "%s" must map each route parameter to a non-empty property path in "parameters" in %s',
                $linkName,
                $sourceFile,
            )];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $link
     *
     * @return array<string>
     */
    protected function validateCondition(string $linkName, array $link, string $sourceFile): array
    {
        if (!isset($link[LinkSchemaKeys::LINK_CONDITION])) {
            return [];
        }

        $condition = $link[LinkSchemaKeys::LINK_CONDITION];

        if (!is_array($condition)) {
            return [sprintf('Link "%s" condition must be an array in %s', $linkName, $sourceFile)];
        }

        $property = $condition[LinkSchemaKeys::CONDITION_PROPERTY] ?? null;

        if (!is_string($property) || $property === '') {
            return [sprintf('Link "%s" condition must declare a non-empty "property" in %s', $linkName, $sourceFile)];
        }

        if (!array_key_exists(LinkSchemaKeys::CONDITION_EQUALS, $condition)) {
            return [sprintf('Link "%s" condition must declare an "equals" value in %s', $linkName, $sourceFile)];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $link
     *
     * @return array<string>
     */
    protected function validateOperations(string $linkName, array $link, string $sourceFile): array
    {
        if (!isset($link[LinkSchemaKeys::LINK_OPERATIONS])) {
            return [];
        }

        if (!is_array($link[LinkSchemaKeys::LINK_OPERATIONS])) {
            return [sprintf('Link "%s" must declare "operations" as a list in %s', $linkName, $sourceFile)];
        }

        foreach ($link[LinkSchemaKeys::LINK_OPERATIONS] as $operation) {
            if (is_string($operation) && $operation !== '') {
                continue;
            }

            return [sprintf(
                'Link "%s" must list operation types or operation names as strings in "operations" in %s',
                $linkName,
                $sourceFile,
            )];
        }

        return [];
    }
}
