<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class PropertyValidationRule implements ValidationRuleInterface
{
    protected const string VALID_PROPERTY_NAME_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    protected const string VALID_SERIALIZED_PATH_PATTERN = '/^\[[\w]+\](\[[\w]+\])*$/';

    protected const array VALID_PROPERTY_TYPES = ['string', 'integer', 'number', 'boolean', 'array', 'object', 'map', 'mixed'];

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $errors = [];
        $properties = $schema['properties'] ?? [];

        if (!is_array($properties)) {
            return $errors;
        }

        $errors = array_merge($errors, $this->validatePropertyNames($properties, $schema));
        $errors = array_merge($errors, $this->validatePropertyTypes($properties, $schema));
        $errors = array_merge($errors, $this->validateBooleanAttributes($properties, $schema));
        $errors = array_merge($errors, $this->validateDefaultValues($properties, $schema));
        $errors = array_merge($errors, $this->validateOpenapiContext($properties, $schema));
        $errors = array_merge($errors, $this->validateSerializedPathAttributes($properties, $schema));
        $errors = array_merge($errors, $this->validateDuplicateItemDefinitions($properties, $schema));
        $errors = array_merge($errors, $this->validateRelationshipItemsCollision($properties, $schema));

        return $errors;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validatePropertyNames(array $properties, array $schema): array
    {
        $errors = [];

        foreach (array_keys($properties) as $propertyName) {
            if (!preg_match(static::VALID_PROPERTY_NAME_PATTERN, $propertyName)) {
                $errors[] = sprintf(
                    'Invalid property name "%s" in %s. Property names must start with a letter or underscore, followed by letters, numbers, or underscores.',
                    $propertyName,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $errors;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validatePropertyTypes(array $properties, array $schema): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            $type = $property['type'] ?? null;

            if ($type === null) {
                continue;
            }

            if (!$this->isValidPropertyType($type)) {
                $contributingFiles = $property['_contributingFiles'] ?? [];
                $resourceName = $schema['name'] ?? 'unknown';
                $codeBucket = $schema['codeBucket'] ?? null;

                $errorMessage = sprintf(
                    'Invalid property type "%s" for property "%s"',
                    $type,
                    $propertyName,
                );

                if ($codeBucket !== null) {
                    $errorMessage .= sprintf(' (Resource: %s, CodeBucket: %s)', $resourceName, $codeBucket);
                } else {
                    $errorMessage .= sprintf(' (Resource: %s)', $resourceName);
                }

                $errorMessage .= sprintf("\n  Valid types: %s or resource class names (e.g., CustomersStorefrontResource)", implode(', ', static::VALID_PROPERTY_TYPES));

                if ($contributingFiles !== []) {
                    $errorMessage .= "\n  Contributing files:";

                    foreach ($contributingFiles as $fileInfo) {
                        $file = $fileInfo['file'] ?? 'unknown';
                        $layer = $fileInfo['layer'] ?? 'unknown';
                        $fileBucket = $fileInfo['codeBucket'] ?? null;

                        $fileLabel = sprintf('    [%s]', $layer);

                        if ($fileBucket !== null) {
                            $fileLabel .= sprintf(' [CodeBucket: %s]', $fileBucket);
                        }

                        $errorMessage .= sprintf("\n%s %s", $fileLabel, $file);
                    }
                } else {
                    $errorMessage .= sprintf("\n  File: %s", $schema['sourceFile'] ?? 'unknown file');
                }

                $errors[] = $errorMessage;
            }
        }

        return $errors;
    }

    protected function isValidPropertyType(string $type): bool
    {
        if (in_array($type, static::VALID_PROPERTY_TYPES, true)) {
            return true;
        }

        return $this->isResourceClassName($type);
    }

    protected function isResourceClassName(string $type): bool
    {
        return str_ends_with($type, 'StorefrontResource') || str_ends_with($type, 'BackendResource');
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateBooleanAttributes(array $properties, array $schema): array
    {
        $errors = [];
        $booleanAttributes = ['writable', 'readable', 'identifier', 'required'];

        foreach ($properties as $propertyName => $property) {
            foreach ($booleanAttributes as $attribute) {
                if (!isset($property[$attribute])) {
                    continue;
                }

                if (!is_bool($property[$attribute])) {
                    $errors[] = sprintf(
                        'Property "%s" attribute "%s" must be a boolean in %s',
                        $propertyName,
                        $attribute,
                        $schema['sourceFile'] ?? 'unknown file',
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateDefaultValues(array $properties, array $schema): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            if (!isset($property['default'])) {
                continue;
            }

            $type = $property['type'] ?? 'string';
            $default = $property['default'];

            if (!$this->isTypeCompatible($default, $type)) {
                $errors[] = sprintf(
                    'Property "%s" default value type does not match declared type "%s" in %s',
                    $propertyName,
                    $type,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $errors;
    }

    protected function isTypeCompatible(mixed $value, string $type): bool
    {
        if ($this->isResourceClassName($type)) {
            return true;
        }

        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value) || is_array($value),
            'map' => is_array($value),
            'mixed' => true,
            default => true,
        };
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateSerializedPathAttributes(array $properties, array $schema): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            if (isset($property['serializedPath']) && isset($property['serializedName'])) {
                $errors[] = sprintf(
                    'Property "%s" cannot have both "serializedPath" and "serializedName" in %s',
                    $propertyName,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }

            if (!isset($property['serializedPath'])) {
                continue;
            }

            if (!is_string($property['serializedPath']) || !preg_match(static::VALID_SERIALIZED_PATH_PATTERN, $property['serializedPath'])) {
                $errors[] = sprintf(
                    'Property "%s" serializedPath must use bracket notation (e.g., "[key][nested]") in %s',
                    $propertyName,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $errors;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateOpenapiContext(array $properties, array $schema): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            if (!isset($property['openapiContext'])) {
                continue;
            }

            if (!is_array($property['openapiContext'])) {
                $errors[] = sprintf(
                    'Property "%s" openapiContext must be an array in %s',
                    $propertyName,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $errors;
    }

    /**
     * A `type: array` property must not carry both a real `items` block and a hand-written
     * `openapiContext.items`: openapiContext is merged on top of the derived schema, so the
     * hand-written shape would win and the typed one would be silently ignored.
     *
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateDuplicateItemDefinitions(array $properties, array $schema, string $pathPrefix = ''): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            $path = $pathPrefix === '' ? (string)$propertyName : $pathPrefix . '.' . $propertyName;

            // isset() is null-safe against scalar/null property values (e.g. a bare `foo:` key
            // parses to null), so no is_array($property) guard is needed before this check.
            if (isset($property['items']) && isset($property['openapiContext']['items'])) {
                $errors[] = sprintf(
                    'Property "%s" declares both "items" and "openapiContext.items" in %s. openapiContext is '
                    . 'merged on top of the derived schema, so the hand-written shape would win and the typed '
                    . 'one would be ignored — remove the "openapiContext.items" block.',
                    $path,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }

            if (isset($property['properties']) && is_array($property['properties'])) {
                $errors = array_merge($errors, $this->validateDuplicateItemDefinitions($property['properties'], $schema, $path));
            }

            if (isset($property['items']['properties']) && is_array($property['items']['properties'])) {
                $errors = array_merge(
                    $errors,
                    $this->validateDuplicateItemDefinitions($property['items']['properties'], $schema, $path . '.items'),
                );
            }
        }

        return $errors;
    }

    /**
     * `PhpTemplateRenderer::renderProperties()` emits exactly one `@var` docblock line per property, and
     * `ClassGenerator::transformProperties()` gives a relationship docblock precedence over a typed-collection
     * one. So a property that both matches a winning `includes` relationship and declares its own `items`
     * collection type silently loses its typed-collection docblock — the relationship docblock wins the slot
     * instead. This rule makes that silent loss loud.
     *
     * `includes` is a top-level list of resource relationships, so only a top-level property can ever collide
     * with one; a nested `properties` / `items.properties` entry cannot be a relationship target and is not
     * checked here.
     *
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateRelationshipItemsCollision(array $properties, array $schema): array
    {
        $errors = [];
        $includes = $schema['includes'] ?? [];

        if (!is_array($includes)) {
            return $errors;
        }

        foreach ($properties as $propertyName => $property) {
            // isset() is null-safe against scalar/null property values (e.g. a bare `foo:` key
            // parses to null), so no is_array($property) guard is needed before this check.
            if (!isset($property['items'])) {
                continue;
            }

            $winningInclude = $this->findWinningRelationshipInclude($property, (string)$propertyName, $includes);

            if ($winningInclude === null) {
                continue;
            }

            $errors[] = sprintf(
                'Property "%s" declares both a relationship in "includes" (%s) and an "items" collection type in %s. '
                . 'The relationship docblock wins the single available slot, so the "items" typed collection is '
                . 'silently dropped — remove the "items" block or that "includes" entry.',
                $propertyName,
                $this->describeInclude($winningInclude),
                $schema['sourceFile'] ?? 'unknown file',
            );
        }

        return $errors;
    }

    /**
     * Names the colliding entry the way it is authored in the schema, so the fix is a direct lookup rather
     * than a kebab-to-camel re-derivation across every `includes` entry of the resource.
     *
     * @param array<string, mixed> $include
     */
    protected function describeInclude(array $include): string
    {
        $relationshipName = $include['relationshipName'] ?? null;
        $targetResource = $include['targetResource'] ?? null;

        if (!is_string($targetResource)) {
            return sprintf('relationshipName: "%s"', is_string($relationshipName) ? $relationshipName : 'unknown');
        }

        return sprintf(
            'relationshipName: "%s", targetResource: "%s"',
            is_string($relationshipName) ? $relationshipName : 'unknown',
            $targetResource,
        );
    }

    /**
     * Mirrors the predicate in `RelationshipPhpDocGenerator::generate()` that decides whether that generator
     * would produce a non-empty relationship docblock for this property, and returns the `includes` entry that
     * wins the slot (null when none does) so the caller can name it. Duplicated rather than injected: this is a
     * validator-layer question, not a codegen concern, and injecting the generator here would cross that layer
     * boundary. The two must stay in step — if `generate()`'s conditions change, update this method to match.
     *
     * @param array<string, mixed> $property
     * @param array<int, array<string, mixed>> $includes
     *
     * @return array<string, mixed>|null
     */
    protected function findWinningRelationshipInclude(array $property, string $propertyName, array $includes): ?array
    {
        if (($property['type'] ?? '') !== 'array') {
            return null;
        }

        // Truthy, not strict === true: mirrors RelationshipPhpDocGenerator::generate()'s
        // `if ($property['writable'] ?? false) { continue; }`, which disqualifies on any truthy
        // value (e.g. a non-boolean 'false' string or 1). The `readable` check below is strict
        // === true on purpose — that asymmetry is in the generator too; do not harmonise them.
        if ($property['writable'] ?? false) {
            return null;
        }

        if (($property['readable'] ?? null) === true) {
            return null;
        }

        foreach ($includes as $include) {
            // isset() is null-safe against a malformed non-array include entry, so no is_array()
            // guard is needed before this check.
            if (isset($include['resolverClass'])) {
                continue;
            }

            $relationshipName = $include['relationshipName'] ?? null;

            if (!is_string($relationshipName)) {
                continue;
            }

            if ($this->kebabToCamelCase($relationshipName) === $propertyName) {
                return $include;
            }
        }

        return null;
    }

    /**
     * Mirrors `RelationshipPhpDocGenerator::kebabToCamelCase()`; see the note on
     * {@see findWinningRelationshipInclude()} about keeping the two in step.
     */
    protected function kebabToCamelCase(string $value): string
    {
        if (!str_contains($value, '-')) {
            return $value;
        }

        return lcfirst(str_replace('-', '', ucwords($value, '-')));
    }
}
