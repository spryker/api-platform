<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class ProviderValidationRule implements ValidationRuleInterface
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $warnings = [];

        if (isset($schema['provider']) && is_string($schema['provider'])) {
            if (!$this->isValidFullyQualifiedClassName($schema['provider'])) {
                $warnings[] = sprintf(
                    'Warning: Provider "%s" does not appear to be a valid fully qualified class name in %s',
                    $schema['provider'],
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $warnings;
    }

    protected function isValidFullyQualifiedClassName(string $fqcn): bool
    {
        return (bool)preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/', $fqcn);
    }
}
