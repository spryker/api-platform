<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Generator;
use Psr\Log\LoggerInterface;

interface ResourceGeneratorInterface
{
    public function setLogger(LoggerInterface $logger): void;

    /**
     * @return \Generator<array{status: string, resource?: string, file?: string, className?: string, sourceFiles?: array<string>, validationSourceFiles?: array<string>, message?: string, diagnostics?: array<string, mixed>, suggestion?: string}>
     */
    public function generateResources(string $apiType): Generator;
}
