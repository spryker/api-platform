<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Utility;

/**
 * Static utility for normalizing API type casing across the system.
 *
 * API Type Casing Convention:
 * - Schema lookup uses lowercase (e.g., 'backoffice', 'storefront')
 *   Schema files are organized in lowercase directories: schemas/backoffice/, schemas/storefront/
 *
 * - Code generation uses ucfirst (e.g., 'Backoffice', 'Storefront')
 *   Generated classes follow PSR-4: Generated\Api\Backoffice\, Generated\Api\Storefront\
 *
 * Usage Examples:
 *
 *   // Schema lookup
 *   $apiType = ApiTypeNormalizer::normalizeForSchemaLookup('BACKOFFICE');
 *   // Result: 'backoffice'
 *
 *   // Code generation
 *   $apiType = ApiTypeNormalizer::normalizeForGeneration('backoffice');
 *   // Result: 'Backoffice'
 *
 *   // Case-insensitive matching
 *   $match = ApiTypeNormalizer::findMatchingConfiguredType('STOREFRONT', ['Backoffice', 'Storefront']);
 *   // Result: 'Storefront'
 */
class ApiTypeNormalizer
{
    /**
     * Normalizes API type to lowercase for schema file lookup.
     *
     * Schema files are organized in lowercase directories following the convention:
     * - schemas/backoffice/
     * - schemas/storefront/
     *
     * This method ensures consistent directory matching regardless of user input casing.
     *
     * @param string $apiType The API type in any casing
     *
     * @return string The API type in lowercase format
     */
    public static function normalizeForSchemaLookup(string $apiType): string
    {
        return strtolower($apiType);
    }

    /**
     * Normalizes API type to ucfirst for code generation.
     *
     * Generated code follows PSR-4 naming conventions where:
     * - Namespaces use ucfirst: Generated\Api\Backoffice\
     * - Directory names use ucfirst: src/Generated/Api/Backoffice/
     * - Class names incorporate ucfirst: OrdersBackofficeResource
     *
     * This method ensures consistent class and namespace generation regardless of input casing.
     *
     * @param string $apiType The API type in any casing
     *
     * @return string The API type in ucfirst format
     */
    public static function normalizeForGeneration(string $apiType): string
    {
        return ucfirst(strtolower($apiType));
    }

    /**
     * Finds a matching configured API type using case-insensitive comparison.
     *
     * This method attempts to match user input against the list of configured API types,
     * ignoring case differences. If a match is found, the properly cased type from the
     * configuration is returned.
     *
     * Use this method at system entry points (console commands) to handle user input
     * gracefully before triggering interactive selection.
     *
     * @param string $userInput The user-provided API type (any casing)
     * @param array<string> $configuredTypes List of properly configured API types
     *
     * @return string|null The matched configured type with proper casing, or null if no match found
     */
    public static function findMatchingConfiguredType(string $userInput, array $configuredTypes): ?string
    {
        $normalizedInput = strtolower($userInput);

        foreach ($configuredTypes as $configuredType) {
            if (strtolower($configuredType) === $normalizedInput) {
                return $configuredType;
            }
        }

        return null;
    }
}
