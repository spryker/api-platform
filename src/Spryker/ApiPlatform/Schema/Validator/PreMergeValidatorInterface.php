<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\ApiPlatform\Schema\Validator;

interface PreMergeValidatorInterface
{
    /**
     * @param array<string, mixed> $schema
     */
    public function validate(array $schema, string $filePath): void;
}
