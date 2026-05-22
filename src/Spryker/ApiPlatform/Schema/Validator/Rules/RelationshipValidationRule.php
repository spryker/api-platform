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
        return $this->validateIncludes($schema);
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
}
