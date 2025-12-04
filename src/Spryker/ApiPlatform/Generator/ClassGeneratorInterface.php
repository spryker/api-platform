<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * Generates PHP class code from parsed and merged schemas.
 */
interface ClassGeneratorInterface
{
    /**
     * @param array<string, mixed> $schema
     */
    public function generate(array $schema, string $apiType): string;
}
