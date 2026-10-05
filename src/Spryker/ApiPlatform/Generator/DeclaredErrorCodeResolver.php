<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;

/**
 * Reads the error codes an operation declares per error status, from
 * `openapiContext.responses.<status>.codes`, and adds the resource's `commonErrorCodes` to every
 * status that declares codes of its own. A common code joins a status, it never creates one, so
 * `validate()` rejects a common code whose status no operation declares codes on.
 *
 * A `codes` entry is either a code string or `{code, description}`. Codes are strings because the
 * JSON:API `code` member is one, and `'005'` keeps its leading zero. The OpenAPI examples and the
 * coverage truth both read this one normalization, so they cannot disagree.
 */
class DeclaredErrorCodeResolver
{
    protected const string KEY_OPEN_API_CONTEXT = 'openapiContext';

    protected const string KEY_RESPONSES = 'responses';

    protected const string KEY_CODES = 'codes';

    protected const string KEY_CODE = 'code';

    protected const string KEY_DESCRIPTION = 'description';

    protected const string KEY_STATUS = 'status';

    protected const string KEY_COMMON_ERROR_CODES = 'commonErrorCodes';

    protected const string KEY_OPERATIONS = 'operations';

    protected const int STATUS_CLIENT_ERROR_MIN = 400;

    /**
     * @param array<string, mixed> $parsedSchema
     * @param array<string, mixed> $operation
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     *
     * @return array<int, array<string, string>> Status => code => description, both sorted.
     */
    public function resolve(array $parsedSchema, array $operation, string $operationLocator): array
    {
        $responses = $operation[static::KEY_OPEN_API_CONTEXT][static::KEY_RESPONSES] ?? null;
        if (!is_array($responses)) {
            return [];
        }

        $declaredCodes = [];
        foreach ($responses as $status => $response) {
            if (!is_array($response) || !array_key_exists(static::KEY_CODES, $response)) {
                continue;
            }

            $status = (int)$status;
            if ($status < static::STATUS_CLIENT_ERROR_MIN) {
                throw new ApiSchemaGenerationException(sprintf(
                    'openapiContext.responses.%d of %s declares codes, but only an error status (4xx/5xx) answers an error code.',
                    $status,
                    $operationLocator,
                ));
            }

            $description = $response[static::KEY_DESCRIPTION] ?? null;
            if (!is_string($description) || $description === '') {
                throw new ApiSchemaGenerationException(sprintf(
                    'openapiContext.responses.%d of %s declares codes but no description, so the published OpenAPI would drop the response.',
                    $status,
                    $operationLocator,
                ));
            }

            $declaredCodes[$status] = $this->normalizeCodes($response[static::KEY_CODES], sprintf('openapiContext.responses.%d.codes of %s', $status, $operationLocator));
        }

        if ($declaredCodes === []) {
            return [];
        }

        foreach ($this->resolveCommonErrorCodes($parsedSchema, $operationLocator) as $status => $commonCodes) {
            if (isset($declaredCodes[$status])) {
                $declaredCodes[$status] += $commonCodes;
            }
        }

        ksort($declaredCodes);
        foreach ($declaredCodes as $status => $codes) {
            ksort($codes, SORT_STRING);
            $declaredCodes[$status] = $codes;
        }

        return $declaredCodes;
    }

    /**
     * Every declaration error of the schema at once, so `api:generate` reports all of them rather
     * than the first.
     *
     * @param array<string, mixed> $parsedSchema
     *
     * @return array<string>
     */
    public function validate(array $parsedSchema): array
    {
        $errors = [];
        $sourceFile = (string)($parsedSchema['sourceFile'] ?? 'unknown file');
        $commonCodes = [];

        try {
            $commonCodes = $this->resolveCommonErrorCodes($parsedSchema, $sourceFile);
        } catch (ApiSchemaGenerationException $exception) {
            $errors[] = $exception->getMessage();
        }

        $statusesDeclaringCodes = [];
        $operations = $parsedSchema[static::KEY_OPERATIONS] ?? [];
        foreach (is_array($operations) ? $operations : [] as $operationType => $operation) {
            if (!is_array($operation)) {
                continue;
            }

            $statusesDeclaringCodes += $this->statusesDeclaringCodes($operation);

            try {
                $this->resolve([], $operation, sprintf('%s %s (%s)', (string)($parsedSchema['shortName'] ?? ''), (string)$operationType, $sourceFile));
            } catch (ApiSchemaGenerationException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        foreach (array_diff_key($commonCodes, $statusesDeclaringCodes) as $status => $codes) {
            $errors[] = sprintf(
                'commonErrorCodes of %s declare %s for status %d, but no operation of the merged resource declares codes on its %d response, so they would reach neither the OpenAPI nor the contract coverage.',
                $sourceFile,
                implode(', ', array_map('strval', array_keys($codes))),
                $status,
                $status,
            );
        }

        return $errors;
    }

    /**
     * Read straight from the declaration, so an operation whose codes are malformed for another
     * reason does not also make every common code on that status look orphaned.
     *
     * @param array<string, mixed> $operation
     *
     * @return array<int, true>
     */
    protected function statusesDeclaringCodes(array $operation): array
    {
        $responses = $operation[static::KEY_OPEN_API_CONTEXT][static::KEY_RESPONSES] ?? null;
        $statuses = [];

        foreach (is_array($responses) ? $responses : [] as $status => $response) {
            if (is_array($response) && array_key_exists(static::KEY_CODES, $response)) {
                $statuses[(int)$status] = true;
            }
        }

        return $statuses;
    }

    /**
     * @param array<string, mixed> $parsedSchema
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     *
     * @return array<int, array<string, string>>
     */
    protected function resolveCommonErrorCodes(array $parsedSchema, string $locator): array
    {
        $entries = $parsedSchema[static::KEY_COMMON_ERROR_CODES] ?? [];
        if (!is_array($entries)) {
            return [];
        }

        $commonCodes = [];
        foreach ($entries as $index => $entry) {
            $status = is_array($entry) ? ($entry[static::KEY_STATUS] ?? null) : null;
            if (!is_int($status) || $status < static::STATUS_CLIENT_ERROR_MIN) {
                throw new ApiSchemaGenerationException(sprintf(
                    'commonErrorCodes entry #%d of %s needs an error "status" (4xx/5xx) next to its "code".',
                    (int)$index,
                    $locator,
                ));
            }

            $commonCodes[$status] = ($commonCodes[$status] ?? []) + $this->normalizeCodes([$entry], sprintf('commonErrorCodes entry #%d of %s', (int)$index, $locator));
        }

        return $commonCodes;
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     *
     * @return array<string, string> Code => description.
     */
    protected function normalizeCodes(mixed $codes, string $locator): array
    {
        if (!is_array($codes) || $codes === []) {
            throw new ApiSchemaGenerationException(sprintf('%s must be a non-empty list of codes.', $locator));
        }

        $normalized = [];
        foreach ($codes as $index => $entry) {
            $code = is_array($entry) ? ($entry[static::KEY_CODE] ?? null) : $entry;
            if (!is_string($code) || $code === '') {
                throw new ApiSchemaGenerationException(sprintf(
                    'Entry #%d of %s declares no string "code". Quote it in the yml (\'005\'), a JSON:API error code is a string.',
                    (int)$index,
                    $locator,
                ));
            }

            $description = is_array($entry) ? ($entry[static::KEY_DESCRIPTION] ?? null) : null;
            $normalized[$code] = is_string($description) && $description !== '' ? $description : $code;
        }

        return $normalized;
    }
}
