<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Exception;

use Throwable;

/**
 * Exception thrown when schema validation fails.
 *
 * Includes file path and line number context for better error reporting.
 */
class ApiSchemaValidationException extends ApiSchemaException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        protected readonly ?string $filePath = null,
        protected int $line = 0,
        protected readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        $fullMessage = $this->buildMessage($message);
        parent::__construct($fullMessage, 0, $previous);
    }

    /**
     * Get the file path where the error occurred.
     *
     * @return string|null
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Get additional context information.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Build a detailed error message with file and line context.
     *
     * @param string $message
     *
     * @return string
     */
    protected function buildMessage(string $message): string
    {
        $parts = [$message];

        if ($this->filePath !== null) {
            $location = sprintf('File: %s', $this->filePath);
            if ($this->line !== null) {
                $location .= sprintf(' (line %d)', $this->line);
            }
            $parts[] = $location;
        }

        if ($this->context !== []) {
            $contextStr = 'Context: ' . json_encode($this->context, JSON_PRETTY_PRINT);
            $parts[] = $contextStr;
        }

        return implode("\n\n", $parts);
    }
}
