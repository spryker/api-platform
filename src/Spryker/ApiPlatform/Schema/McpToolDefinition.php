<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema;

class McpToolDefinition
{
    /**
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $tool The `mcp` map of the operation; empty for `mcp: true`.
     */
    public function __construct(
        public readonly string $toolName,
        public readonly string $operationKey,
        public readonly array $operation,
        public readonly array $tool,
    ) {
    }
}
