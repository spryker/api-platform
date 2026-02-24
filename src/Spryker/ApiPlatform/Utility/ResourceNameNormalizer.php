<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Utility;

use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;

class ResourceNameNormalizer
{
    /**
     * Normalizes a resource name to a valid PascalCase PHP class name.
     *
     * The normalization process:
     * 1. Trims leading and trailing whitespace
     * 2. Replaces special characters with spaces
     * 3. Inserts delimiters at CamelCase boundaries (e.g., "CustomersAddresses" → "Customers Addresses")
     * 4. Splits on separators (space, hyphen, underscore, dot, forward slash)
     * 5. Filters out empty parts
     * 6. Capitalizes first letter of each part
     * 7. Joins all parts together
     * 8. Validates result is a valid PHP identifier
     * 9. Throws exception if starts with number or is invalid
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     */
    public static function normalize(string $resourceName): string
    {
        $trimmed = trim($resourceName);

        static::validateNotEmpty($trimmed, $resourceName);

        $withoutSpecialChars = static::replaceSpecialCharactersWithSpaces($trimmed);

        $withCamelCaseDelimiters = static::insertDelimitersForCamelCase($withoutSpecialChars);

        $parts = static::splitIntoParts($withCamelCaseDelimiters);

        $filteredParts = array_filter($parts, fn (string $part): bool => $part !== '');

        if ($filteredParts === []) {
            throw new ApiSchemaGenerationException(
                sprintf('Resource name must contain at least one alphanumeric character. Got: "%s"', $resourceName),
            );
        }

        $normalized = static::capitalizeAndJoinParts($filteredParts);

        static::validateResult($normalized, $resourceName);

        return $normalized;
    }

    protected static function validateNotEmpty(string $trimmed, string $original): void
    {
        if ($trimmed === '') {
            throw new ApiSchemaGenerationException('Resource name cannot be empty');
        }
    }

    protected static function replaceSpecialCharactersWithSpaces(string $input): string
    {
        $result = preg_replace('/[^\w\s\-_.\\/]/', ' ', $input);

        return $result ?? $input;
    }

    /**
     * Inserts delimiters before uppercase letters in CamelCase strings.
     *
     * Handles:
     * - Lowercase to uppercase transitions: "customersAddresses" → "customers Addresses"
     * - Number to uppercase transitions: "OAuth2Tokens" → "OAuth2 Tokens"
     * - Multiple consecutive uppercase letters: "XMLParser" → "XML Parser"
     * - Preserves numbers and existing delimiters
     *
     * @return string String with spaces inserted at CamelCase boundaries
     */
    protected static function insertDelimitersForCamelCase(string $input): string
    {
        $withSpaces = preg_replace('/([a-z])([A-Z])/', '$1 $2', $input);

        $withSpaces = preg_replace('/([0-9])([A-Z])/', '$1 $2', $withSpaces ?? $input);

        $withSpaces = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $withSpaces ?? $input);

        return $withSpaces ?? $input;
    }

    /**
     * @return array<string>
     */
    protected static function splitIntoParts(string $input): array
    {
        $parts = preg_split('/[\s\-_.\\/]+/', $input);

        return $parts !== false ? $parts : [$input];
    }

    /**
     * @param array<string> $parts
     */
    protected static function capitalizeAndJoinParts(array $parts): string
    {
        $capitalizedParts = array_map(
            fn (string $part): string => ucfirst(strtolower($part)),
            $parts,
        );

        return implode('', $capitalizedParts);
    }

    protected static function validateResult(string $normalized, string $original): void
    {
        if (preg_match('/^\d/', $normalized)) {
            throw new ApiSchemaGenerationException(
                sprintf(
                    'Resource name cannot start with a number. PHP class names must begin with a letter or underscore. Got: "%s". Consider prefixing with a word (e.g., "Auth2fa" instead of "2fa").',
                    $original,
                ),
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $normalized)) {
            throw new ApiSchemaGenerationException(
                sprintf('Invalid PHP identifier after normalization: "%s" from input: "%s"', $normalized, $original),
            );
        }
    }
}
