<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * An enforced operation whose resource schema declares no responses. Coverage cannot be derived for
 * it — the schema is the source of truth, so the gate reports the operation together with the
 * `.resource.yml` files to edit rather than inventing a status to demand.
 */
readonly class SchemaDefect
{
    /**
     * @param array<string> $schemaFiles
     */
    public function __construct(
        public ApiOperation $operation,
        public string $resource,
        public array $schemaFiles,
    ) {
    }

    public function key(): string
    {
        return $this->resource . ' ' . $this->operation->dispatchKey();
    }
}
