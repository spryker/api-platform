<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Exception;

use Throwable;

/**
 * Exception thrown when schema merging fails.
 *
 * Includes conflict details to help developers understand merge issues.
 */
class ApiSchemaMergeException extends ApiSchemaException
{
    /**
     * @param string $message
     * @param string|null $coreFile
     * @param string|null $projectFile
     * @param array<string, mixed> $conflictDetails
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        protected readonly ?string $coreFile = null,
        protected readonly ?string $projectFile = null,
        protected readonly array $conflictDetails = [],
        ?Throwable $previous = null,
    ) {
        $fullMessage = $this->buildMessage($message);
        parent::__construct($fullMessage, 0, $previous);
    }

    /**
     * Get the core schema file path.
     *
     * @return string|null
     */
    public function getCoreFile(): ?string
    {
        return $this->coreFile;
    }

    /**
     * Get the project schema file path.
     *
     * @return string|null
     */
    public function getProjectFile(): ?string
    {
        return $this->projectFile;
    }

    /**
     * Get conflict details.
     *
     * @return array<string, mixed>
     */
    public function getConflictDetails(): array
    {
        return $this->conflictDetails;
    }

    /**
     * Build a detailed error message with conflict information.
     *
     * @param string $message
     *
     * @return string
     */
    protected function buildMessage(string $message): string
    {
        $parts = [$message];

        if ($this->coreFile !== null || $this->projectFile !== null) {
            $files = [];
            if ($this->coreFile !== null) {
                $files[] = sprintf('Core: %s', $this->coreFile);
            }
            if ($this->projectFile !== null) {
                $files[] = sprintf('Project: %s', $this->projectFile);
            }
            $parts[] = "Conflicting files:\n" . implode("\n", $files);
        }

        if ($this->conflictDetails !== []) {
            $parts[] = 'Conflict details: ' . json_encode($this->conflictDetails, JSON_PRETTY_PRINT);
        }

        return implode("\n\n", $parts);
    }
}
