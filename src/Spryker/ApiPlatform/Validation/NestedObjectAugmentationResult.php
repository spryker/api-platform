<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

/**
 * Outcome of augmenting nested value-object validation errors.
 *
 * `modified` reports whether the augmenter changed the error set; when false the caller leaves the
 * response untouched. `errors` is the final error list (already reduced to validation errors only
 * when `forceUnprocessableEntity` is set). `forceUnprocessableEntity` marks the empty-required-object
 * case where a downstream domain error was superseded so the response contract stays the pure
 * field-missing set.
 */
class NestedObjectAugmentationResult
{
    /**
     * @param array<int, array<string, mixed>> $errors
     */
    public function __construct(
        public readonly bool $modified,
        public readonly array $errors,
        public readonly bool $forceUnprocessableEntity,
    ) {
    }
}
