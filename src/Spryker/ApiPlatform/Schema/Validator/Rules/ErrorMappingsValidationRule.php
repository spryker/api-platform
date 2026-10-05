<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

/**
 * Rejects an `errorMappings` registration the contract coverage could not read: a source that is
 * not an existing `Class::method`, or a `notAnswered` code without a reason. The reason is what keeps
 * the choice not to answer a mapped code reviewable.
 */
class ErrorMappingsValidationRule implements ValidationRuleInterface
{
    protected const string KEY_ERROR_MAPPINGS = 'errorMappings';

    protected const string KEY_SOURCE = 'source';

    protected const string KEY_NOT_ANSWERED = 'notAnswered';

    /**
     * @var non-empty-string
     */
    protected const string SOURCE_SEPARATOR = '::';

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $errorMappings = $schema[static::KEY_ERROR_MAPPINGS] ?? null;
        if (!is_array($errorMappings)) {
            return [];
        }

        $errors = [];
        $sourceFile = (string)($schema['sourceFile'] ?? 'unknown file');

        foreach ($errorMappings as $index => $errorMapping) {
            $source = is_array($errorMapping) ? ($errorMapping[static::KEY_SOURCE] ?? null) : null;
            if (!is_string($source) || !$this->isExistingMethod($source)) {
                $errors[] = sprintf(
                    'errorMappings entry #%d of %s names "%s" as its source, which is not an existing Class::method.',
                    (int)$index,
                    $sourceFile,
                    is_string($source) ? $source : '',
                );

                continue;
            }

            foreach ((array)($errorMapping[static::KEY_NOT_ANSWERED] ?? []) as $code => $reason) {
                if (!is_string($reason) || trim($reason) === '') {
                    $errors[] = sprintf('errorMappings entry #%d of %s marks code %s notAnswered without a reason.', (int)$index, $sourceFile, (string)$code);
                }
            }
        }

        return $errors;
    }

    protected function isExistingMethod(string $source): bool
    {
        $parts = explode(static::SOURCE_SEPARATOR, $source);

        return count($parts) === 2 && class_exists($parts[0]) && method_exists($parts[0], $parts[1]);
    }
}
