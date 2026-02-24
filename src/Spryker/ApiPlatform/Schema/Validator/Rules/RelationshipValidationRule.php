<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class RelationshipValidationRule implements ValidationRuleInterface
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $warnings = [];

        $warnings = array_merge($warnings, $this->validateIncludes($schema));
        $warnings = array_merge($warnings, $this->validateIncludableIn($schema));

        return $warnings;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateIncludes(array $schema): array
    {
        $warnings = [];

        if (!isset($schema['includes']) || !is_array($schema['includes'])) {
            return $warnings;
        }

        foreach ($schema['includes'] as $index => $include) {
            if (!is_array($include)) {
                $warnings[] = sprintf(
                    'Warning: includes[%d] must be an array in %s',
                    $index,
                    $schema['sourceFile'] ?? 'unknown file',
                );

                continue;
            }

            if (!isset($include['relationshipName']) || !is_string($include['relationshipName'])) {
                $warnings[] = sprintf(
                    'Warning: includes[%d] is missing required field "relationshipName" in %s',
                    $index,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }

            if (!isset($include['targetResource']) || !is_string($include['targetResource'])) {
                $warnings[] = sprintf(
                    'Warning: includes[%d] is missing required field "targetResource" in %s',
                    $index,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }

            if (isset($include['uriVariableMappings']) && !is_array($include['uriVariableMappings'])) {
                $warnings[] = sprintf(
                    'Warning: includes[%d].uriVariableMappings must be an array in %s',
                    $index,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateIncludableIn(array $schema): array
    {
        $warnings = [];

        if (!isset($schema['includableIn']) || !is_array($schema['includableIn'])) {
            return $warnings;
        }

        foreach ($schema['includableIn'] as $index => $includable) {
            if (!is_array($includable)) {
                $warnings[] = sprintf(
                    'Warning: includableIn[%d] must be an array in %s',
                    $index,
                    $schema['sourceFile'] ?? 'unknown file',
                );

                continue;
            }

            if (!isset($includable['resource']) || !is_string($includable['resource'])) {
                $warnings[] = sprintf(
                    'Warning: includableIn[%d] is missing required field "resource" in %s',
                    $index,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }

            if (!isset($includable['relationshipName']) || !is_string($includable['relationshipName'])) {
                $warnings[] = sprintf(
                    'Warning: includableIn[%d] is missing required field "relationshipName" in %s',
                    $index,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }

            if (isset($includable['uriVariableMappings']) && !is_array($includable['uriVariableMappings'])) {
                $warnings[] = sprintf(
                    'Warning: includableIn[%d].uriVariableMappings must be an array in %s',
                    $index,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $warnings;
    }
}
