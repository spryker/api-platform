<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

/**
 * Validates that pagination-related schema fields have the correct types
 * (positive integers for page sizes, booleans for toggles).
 */
class PaginationValidationRule implements ValidationRuleInterface
{
    protected const string PAGINATION_ITEMS_PER_PAGE = 'paginationItemsPerPage';

    protected const string PAGINATION_ENABLED = 'paginationEnabled';

    protected const string PAGINATION_MAXIMUM_ITEMS_PER_PAGE = 'paginationMaximumItemsPerPage';

    protected const string PAGINATION_CLIENT_ENABLED = 'paginationClientEnabled';

    protected const string PAGINATION_CLIENT_ITEMS_PER_PAGE = 'paginationClientItemsPerPage';

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $errors = [];
        $sourceFile = $schema['sourceFile'] ?? 'unknown file';

        $errors = array_merge($errors, $this->validatePositiveInteger($schema, static::PAGINATION_ITEMS_PER_PAGE, $sourceFile));
        $errors = array_merge($errors, $this->validatePositiveInteger($schema, static::PAGINATION_MAXIMUM_ITEMS_PER_PAGE, $sourceFile));
        $errors = array_merge($errors, $this->validateBoolean($schema, static::PAGINATION_ENABLED, $sourceFile));
        $errors = array_merge($errors, $this->validateBoolean($schema, static::PAGINATION_CLIENT_ENABLED, $sourceFile));
        $errors = array_merge($errors, $this->validateBoolean($schema, static::PAGINATION_CLIENT_ITEMS_PER_PAGE, $sourceFile));

        return $errors;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validatePositiveInteger(array $schema, string $field, string $sourceFile): array
    {
        if (!isset($schema[$field])) {
            return [];
        }

        if (!is_int($schema[$field]) || $schema[$field] <= 0) {
            return [sprintf('%s must be a positive integer in %s', $field, $sourceFile)];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateBoolean(array $schema, string $field, string $sourceFile): array
    {
        if (!isset($schema[$field])) {
            return [];
        }

        if (!is_bool($schema[$field])) {
            return [sprintf('%s must be a boolean in %s', $field, $sourceFile)];
        }

        return [];
    }
}
