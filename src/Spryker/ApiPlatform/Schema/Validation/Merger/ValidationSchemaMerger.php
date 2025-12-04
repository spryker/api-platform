<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validation\Merger;

class ValidationSchemaMerger implements ValidationSchemaMergerInterface
{
    /**
     * @param array<array<string, mixed>> $schemas
     *
     * @return array<string, mixed>
     */
    public function merge(array $schemas): array
    {
        if ($schemas === []) {
            return [];
        }

        if (count($schemas) === 1) {
            return reset($schemas);
        }

        $result = [];

        foreach ($schemas as $schema) {
            foreach ($schema as $httpMethod => $fieldConstraints) {
                if (!isset($result[$httpMethod])) {
                    $result[$httpMethod] = $fieldConstraints;

                    continue;
                }

                foreach ($fieldConstraints as $fieldName => $constraints) {
                    $result[$httpMethod][$fieldName] = $constraints;
                }
            }
        }

        return $result;
    }
}
