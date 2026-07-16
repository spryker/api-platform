<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Generator\Result\CanonicalObjectResult;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Spryker\ApiPlatform\Utility\ResourceNameNormalizer;

/**
 * Builds shared canonical value-object classes from project-authored `*.object.yml` definitions.
 *
 * Runs as a pre-pass before the per-resource generation loop. The canonical shape is
 * DEFINITION-DRIVEN: it comes solely from the resolved `*.object.yml` definitions
 * (output of {@see \Spryker\ApiPlatform\Schema\Object\CanonicalObjectDefinitionResolver}). A
 * canonical class is generated ONLY for objects a resolved definition exists for; every other
 * `objectName` reference keeps today's per-resource inline behaviour.
 *
 * Per resolved object the registry:
 * - asserts the object name does not collide with a generated resource class name (hard error),
 * - lifts the object's own `*.object.validation.yml` field constraints into `#[Assert\...]`
 *   attributes on the value object (so a denormalized object is validated field-by-field rather
 *   than failing an array-shaped parent `Collection`), and
 * - generates one class into `Generated\Api\{ApiType}\{ObjectName}` via the shared
 *   {@see NestedObjectClassGenerator} (4-arg form — the canonical home, no per-owner sub-namespace).
 */
class CanonicalObjectRegistry
{
    public function __construct(
        protected readonly NestedObjectClassGenerator $nestedObjectClassGenerator,
        protected readonly ValidationAttributeGenerator $validationAttributeGenerator,
    ) {
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $resolvedDefinitions
     * @param array<string, array<string, mixed>> $objectValidationSchemas
     * @param array<string, mixed> $validatedSchemas
     */
    public function build(
        array $resolvedDefinitions,
        array $objectValidationSchemas,
        array $validatedSchemas,
        string $apiType
    ): CanonicalObjectResult {
        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

        if ($resolvedDefinitions === []) {
            return new CanonicalObjectResult();
        }

        $resourceClassNames = $this->collectResourceClassNames($validatedSchemas, $apiType);

        $canonicalObjectClasses = [];
        $knownCanonicalObjectNames = [];

        foreach ($resolvedDefinitions as $objectName => $fields) {
            $objectName = (string)$objectName;

            $this->assertNoResourceClassNameCollision($objectName, $resourceClassNames);

            $rendererFields = $this->attachFieldValidation(
                $this->stripInternalFieldKeys($fields),
                $this->liftObjectValidation($objectName, $objectValidationSchemas),
            );

            // Reuse the existing nested-object renderer: a canonical object is the same plain typed
            // value-object class, generated once as class `{ObjectName}` in namespace
            // `Generated\Api\{ApiType}` for every referencing site. It returns this class and any descendant classes its own
            // nested objects emit. The class is named the bare `objectName` (via the override), which
            // is exactly what each referencing resource imports and types (`Address`, not the
            // per-resource `AddressStorefrontObject` companion form).
            $canonicalObjectClasses += $this->nestedObjectClassGenerator->generate(
                $objectName,
                $rendererFields,
                $apiType,
                $this->sourceFilesFor($objectName, $objectValidationSchemas),
                false,
                '',
                $objectName,
            );

            $knownCanonicalObjectNames[$objectName] = true;
        }

        return new CanonicalObjectResult($canonicalObjectClasses, $knownCanonicalObjectNames);
    }

    /**
     * @param array<string, array<string, mixed>> $objectValidationSchemas
     *
     * @return array<string, array<int, string>>
     */
    protected function liftObjectValidation(string $objectName, array $objectValidationSchemas): array
    {
        $validationSchema = is_array($objectValidationSchemas[$objectName] ?? null)
            ? $objectValidationSchemas[$objectName]
            : [];

        if ($validationSchema === []) {
            return [];
        }

        $operations = [];
        $fieldNames = [];

        foreach ($validationSchema as $httpMethod => $fieldConstraints) {
            $operations[ucfirst((string)$httpMethod)] = ['type' => ucfirst((string)$httpMethod)];

            if (!is_array($fieldConstraints)) {
                continue;
            }

            foreach (array_keys($fieldConstraints) as $fieldName) {
                $fieldNames[(string)$fieldName] = true;
            }
        }

        $fieldValidation = [];

        foreach (array_keys($fieldNames) as $fieldName) {
            $attributes = $this->validationAttributeGenerator->generate(
                $validationSchema,
                $operations,
                $fieldName,
                $objectName,
            );

            if ($attributes !== []) {
                $fieldValidation[$fieldName] = $attributes;
            }
        }

        return $fieldValidation;
    }

    /**
     * @param array<string, array<string, mixed>> $objectValidationSchemas
     *
     * @return array<int, string>
     */
    protected function sourceFilesFor(string $objectName, array $objectValidationSchemas): array
    {
        $sourceFile = $objectValidationSchemas[$objectName]['sourceFile'] ?? null;

        return is_string($sourceFile) && $sourceFile !== '' ? [$sourceFile] : [];
    }

    /**
     * @param array<string, true> $resourceClassNames
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     */
    protected function assertNoResourceClassNameCollision(string $objectName, array $resourceClassNames): void
    {
        if (!isset($resourceClassNames[$objectName])) {
            return;
        }

        throw new ApiSchemaGenerationException(
            sprintf(
                "Canonical object '%s' collides with the generated resource class '%s'. "
                . 'Rename the objectName so it does not clash with a resource class.',
                $objectName,
                $objectName,
            ),
        );
    }

    /**
     * @param array<string, mixed> $validatedSchemas
     *
     * @return array<string, true>
     */
    protected function collectResourceClassNames(array $validatedSchemas, string $apiType): array
    {
        $resourceClassNames = [];

        foreach ($validatedSchemas as $schema) {
            if (!is_array($schema)) {
                continue;
            }

            $resourceName = $schema['name'] ?? $schema['shortName'] ?? null;

            if ($resourceName === null) {
                continue;
            }

            $normalized = ResourceNameNormalizer::normalize((string)$resourceName);
            $codeBucket = $schema['codeBucket'] ?? null;

            $className = $codeBucket !== null
                ? sprintf('%s%s%sResource', $normalized, $codeBucket, $apiType)
                : sprintf('%s%sResource', $normalized, $apiType);

            $resourceClassNames[$className] = true;
        }

        return $resourceClassNames;
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @return array<string, array<string, mixed>>
     */
    protected function stripInternalFieldKeys(array $fields): array
    {
        $rendererFields = [];

        foreach ($fields as $fieldName => $field) {
            if (!is_array($field)) {
                continue;
            }

            unset($field['sourceFile']);
            $rendererFields[(string)$fieldName] = $field;
        }

        return $rendererFields;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, array<int, string>> $fieldValidation
     *
     * @return array<string, array<string, mixed>>
     */
    protected function attachFieldValidation(array $fields, array $fieldValidation): array
    {
        foreach ($fieldValidation as $fieldName => $attributes) {
            if (!isset($fields[$fieldName])) {
                continue;
            }

            $fields[$fieldName]['_validationAttributes'] = $this->normalizeFieldAttributes(array_values($attributes));
        }

        return $fields;
    }

    /**
     * @param array<int, string> $attributes
     *
     * @return array<int, string>
     */
    protected function normalizeFieldAttributes(array $attributes): array
    {
        $relaxedGroups = [];

        foreach ($attributes as $attribute) {
            if (str_contains($attribute, 'NotBlank(allowNull: true') && preg_match('/groups: \[([^\]]*)\]/', $attribute, $matches)) {
                $relaxedGroups[$matches[1]] = true;
            }
        }

        if ($relaxedGroups === []) {
            return $attributes;
        }

        return array_values(array_filter($attributes, static function (string $attribute) use ($relaxedGroups): bool {
            if (!str_contains($attribute, 'NotBlank(') || str_contains($attribute, 'allowNull')) {
                return true;
            }

            return !(preg_match('/groups: \[([^\]]*)\]/', $attribute, $matches) && isset($relaxedGroups[$matches[1]]));
        }));
    }
}
