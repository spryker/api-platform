<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Exception;

use Throwable;

/**
 * Exception thrown when resource generation fails.
 *
 * Covers errors during class generation, template rendering, or file writing.
 */
class ApiSchemaGenerationException extends ApiSchemaException
{
    /**
     * @param string $message
     * @param string|null $resourceName
     * @param string|null $apiType
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        protected readonly ?string $resourceName = null,
        protected readonly ?string $apiType = null,
        ?Throwable $previous = null,
    ) {
        $fullMessage = $this->buildMessage($message);
        parent::__construct($fullMessage, 0, $previous);
    }

    /**
     * Get the resource name being generated.
     *
     * @return string|null
     */
    public function getResourceName(): ?string
    {
        return $this->resourceName;
    }

    /**
     * Get the ApiType being generated.
     *
     * @return string|null
     */
    public function getApiType(): ?string
    {
        return $this->apiType;
    }

    /**
     * Build a detailed error message with resource context.
     *
     * @param string $message
     *
     * @return string
     */
    protected function buildMessage(string $message): string
    {
        $parts = [$message];

        if ($this->resourceName !== null || $this->apiType !== null) {
            $context = [];
            if ($this->resourceName !== null) {
                $context[] = sprintf('Resource: %s', $this->resourceName);
            }
            if ($this->apiType !== null) {
                $context[] = sprintf('ApiType: %s', $this->apiType);
            }
            $parts[] = implode(', ', $context);
        }

        return implode("\n\n", $parts);
    }
}
