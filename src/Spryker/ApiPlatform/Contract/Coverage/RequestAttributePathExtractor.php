<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The request attribute paths a request body really sent, in the form the truth names them: an
 * object contributes its own name and its children (`salesUnit`, `salesUnit.id`), a list of objects
 * its name and its children under the `[]` wildcard (`shipments`, `shipments[].items`). One level
 * deep, like the truth. A value that says nothing - `null`, `''`, `[]` - is not sent.
 */
class RequestAttributePathExtractor
{
    protected const string PATH_SEPARATOR = '.';

    protected const string PATH_WILDCARD = '[]';

    /**
     * @param array<string, mixed> $attributes The decoded `data.attributes`.
     *
     * @return array<string>
     */
    public function extract(array $attributes): array
    {
        $paths = [];

        foreach ($attributes as $name => $value) {
            if ($this->isEmpty($value)) {
                continue;
            }

            $paths[] = (string)$name;
            if (!is_array($value)) {
                continue;
            }

            if (!array_is_list($value)) {
                $paths = [...$paths, ...$this->childPaths($value, $name . static::PATH_SEPARATOR)];

                continue;
            }

            foreach ($value as $item) {
                if (is_array($item) && !array_is_list($item)) {
                    $paths = [...$paths, ...$this->childPaths($item, $name . static::PATH_WILDCARD . static::PATH_SEPARATOR)];
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param array<mixed> $object
     *
     * @return array<string>
     */
    protected function childPaths(array $object, string $prefix): array
    {
        $paths = [];

        foreach ($object as $name => $value) {
            if (!$this->isEmpty($value)) {
                $paths[] = $prefix . $name;
            }
        }

        return $paths;
    }

    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
