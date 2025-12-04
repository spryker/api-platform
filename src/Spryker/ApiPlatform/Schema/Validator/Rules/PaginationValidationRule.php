<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class PaginationValidationRule implements ValidationRuleInterface
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        if (!isset($schema['paginationItemsPerPage'])) {
            return [];
        }

        $pagination = $schema['paginationItemsPerPage'];

        if (!is_int($pagination) || $pagination <= 0) {
            return [
                sprintf(
                    'paginationItemsPerPage must be a positive integer in %s',
                    $schema['sourceFile'] ?? 'unknown file',
                ),
            ];
        }

        return [];
    }
}
