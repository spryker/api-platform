<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Generator;

/**
 * Main orchestrator for the complete resource generation pipeline.
 *
 * Coordinates: Find → Load → Parse → Merge → Validate → Generate
 */
interface ResourceGeneratorInterface
{
    /**
     * Generate resources for the given ApiType.
     *
     * Yields progress updates during generation:
     * - ['status' => 'generated', 'resource' => '...', 'file' => '...', 'className' => '...', 'sourceFiles' => [...], 'validationSourceFiles' => [...]] for each generated resource
     * - ['status' => 'error', 'message' => '...'] on errors
     *
     * @return \Generator<array{status: string, resource?: string, file?: string, className?: string, sourceFiles?: array<string>, validationSourceFiles?: array<string>, message?: string, diagnostics?: array<string, mixed>, suggestion?: string}>
     */
    public function generateResources(string $apiType): Generator;
}
