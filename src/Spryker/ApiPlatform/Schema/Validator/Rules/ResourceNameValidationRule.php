<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class ResourceNameValidationRule implements ValidationRuleInterface
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        if (!isset($schema['name']) || trim((string)$schema['name']) === '') {
            return [
                sprintf(
                    'name is required and must not be empty in %s',
                    $schema['sourceFile'] ?? 'unknown file',
                ),
            ];
        }

        return [];
    }
}
