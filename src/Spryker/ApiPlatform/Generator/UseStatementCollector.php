<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * Collects and generates use statements (imports) for generated resource classes.
 *
 * Analyzes schema and properties to determine which classes need to be imported,
 * including API Platform attributes, Symfony validation constraints, and custom providers/processors.
 *
 * Collected imports:
 * - ApiPlatform\Metadata\ApiResource (always)
 * - ApiPlatform\Metadata\ApiProperty (if any property has ApiProperty attribute)
 * - Symfony\Component\Validator\Constraints as Assert (if validation attributes exist)
 * - Fully qualified class name constraints with aliases (e.g., Custom\MyValidator as CustomMyValidator)
 * - Provider and processor classes from schema
 *
 * Example output for a resource with validation:
 * ```php
 * use ApiPlatform\Metadata\ApiResource;
 * use ApiPlatform\Metadata\ApiProperty;
 * use Symfony\Component\Validator\Constraints as Assert;
 * use Spryker\Glue\Customer\Api\Storefront\Provider\CustomersStorefrontProvider;
 * use Spryker\Glue\Customer\Api\Storefront\Processor\CustomersStorefrontProcessor;
 * ```
 *
 * Handles alias resolution for fully qualified class name constraints to avoid naming conflicts.
 */
class UseStatementCollector
{
    /**
     * @param array<string, mixed> $schema
     * @param array<array{name: string, type: string, phpType: string, attributes: string, description: string, serializedName?: string, serializedPath?: string}> $properties
     * @param array<string, array{fqcn: string, shortName: string, alias: string}> $fqcnConstraintMap
     *
     * @return array<string>
     */
    public function collect(array $schema, array $properties, array $fqcnConstraintMap): array
    {
        $uses = [];

        $uses[] = 'ApiPlatform\Metadata\ApiResource';

        $hasApiProperty = false;
        $hasValidation = false;
        $hasSerializedName = false;
        $hasSerializedPath = false;

        foreach ($properties as $property) {
            if (!$hasSerializedPath && isset($property['serializedPath']) && $property['serializedPath'] !== '') {
                $uses[] = 'Symfony\Component\Serializer\Attribute\SerializedPath';
                $hasSerializedPath = true;
            }

            if (!$hasSerializedName && isset($property['serializedName']) && $property['serializedName'] !== '') {
                $uses[] = 'Symfony\Component\Serializer\Attribute\SerializedName';
                $hasSerializedName = true;
            }

            if ($property['attributes'] === '') {
                continue;
            }

            if (!$hasApiProperty && str_contains($property['attributes'], '#[ApiProperty')) {
                $uses[] = 'ApiPlatform\Metadata\ApiProperty';
                $hasApiProperty = true;
            }

            if (!$hasValidation && str_contains($property['attributes'], '#[Assert\\')) {
                $uses[] = 'Symfony\Component\Validator\Constraints as Assert';
                $hasValidation = true;
            }
        }

        foreach ($fqcnConstraintMap as $constraintData) {
            $uses[] = $this->formatUseStatement($constraintData);
        }

        if (isset($schema['provider']) && $schema['provider'] !== '') {
            $uses[] = $schema['provider'];
        }

        if (isset($schema['processor']) && $schema['processor'] !== '') {
            $uses[] = $schema['processor'];
        }

        return array_unique($uses);
    }

    /**
     * @param array<string, string> $constraintData
     *
     * @return string
     */
    protected function formatUseStatement(array $constraintData): string
    {
        if ($constraintData['alias'] === $constraintData['shortName']) {
            return $constraintData['fqcn'];
        }

        return sprintf('%s as %s', $constraintData['fqcn'], $constraintData['alias']);
    }
}
