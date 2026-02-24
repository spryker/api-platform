<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Spryker\ApiPlatform\Utility\ResourceNameNormalizer;

/**
 * Generates PHPDoc type annotations for relationship properties in API Platform resources.
 *
 * This generator creates fully qualified class name (FQCN) annotations for array-type
 * relationship properties, enabling IDE autocomplete and static analysis for related resources.
 *
 * Input:
 * - Property: ['type' => 'array', 'description' => '...']
 * - Property name: 'addresses'
 * - Includes: [['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses']]
 * - API type: 'Storefront'
 *
 * Output:
 * '@var \Generated\Api\Storefront\CustomersAddressesStorefrontResource[]'
 *
 * The generated PHPDoc enables:
 * ```php
 * $customer->addresses[0]->street // IDE autocomplete works
 * ```
 */
class RelationshipPhpDocGenerator
{
    /**
     * Generates a PHPDoc @var annotation for array-type relationship properties.
     *
     * This method only processes array properties that match an include relationship.
     * Non-array properties or properties without matching includes return an empty string.
     *
     * @param array<string, mixed> $property The property schema definition
     * @param string $propertyName The property name to match against includes
     * @param array<array{relationshipName: string, targetResource: string}> $includes List of relationship definitions
     * @param string $apiType The API type (e.g., 'Storefront', 'Backoffice')
     *
     * @return string The PHPDoc annotation or empty string if not a relationship
     */
    public function generate(
        array $property,
        string $propertyName,
        array $includes,
        string $apiType,
    ): string {
        if (($property['type'] ?? '') !== 'array') {
            return '';
        }

        $targetResource = null;

        foreach ($includes as $include) {
            if ($include['relationshipName'] === $propertyName) {
                $targetResource = $include['targetResource'];

                break;
            }
        }

        if ($targetResource === null) {
            return '';
        }

        $normalizedApiType = ApiTypeNormalizer::normalizeForGeneration($apiType);
        $normalizedResource = ResourceNameNormalizer::normalize($targetResource);
        $fqcn = sprintf(
            '\\Generated\\Api\\%s\\%s%sResource[]',
            $normalizedApiType,
            $normalizedResource,
            $normalizedApiType,
        );

        return sprintf('@var %s', $fqcn);
    }
}
