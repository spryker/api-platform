<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Renders a {@see ContractCoverageResult} as the console report: a counter line per section, then
 * only the entries a reader has to act on. Once most of the API is covered, listing every covered
 * entry buries the handful that need work, so the covered listing moves behind verbose mode.
 */
class ContractCoverageConsoleRenderer
{
    protected const string SEPARATOR = '===================================================';

    protected const string VERBOSE_HINT = 'Run with -v to list every covered entry.';

    protected const string COUNTER_FORMAT_OPERATIONS = '  %-19s %5s covered · %d uncovered · %d stale · %d non-servable · %d internal';

    protected const string COUNTER_FORMAT_VALIDATIONS = '  %-19s %5s covered · %d uncovered · %d stale';

    protected const string COUNTER_FORMAT_RESPONSE_ATTRIBUTES = '  %-19s %5s covered · %d uncovered';

    protected const string COUNTER_FORMAT_SCHEMA_DEFECTS = '  %-19s %5d without declared responses';

    protected const string DECLARER_FORMAT = '      covered by: %s';

    protected const string NO_DECLARER_PLACEHOLDER = '(no test declares this operation yet)';

    protected const string SCHEMA_DEFECT_HEADING = 'SCHEMA DEFECT  %s  %s';

    protected const string SCHEMA_DEFECT_EXPLANATION = '  The resource schema declares no responses for this operation. The schema is the source'
        . "\n" . '  of truth — declare at least one response under openapiContext.responses in:';

    protected const string SCHEMA_DEFECT_REGENERATE_HINT = '  then regenerate: vendor/bin/glue api:generate';

    /**
     * @return array<string>
     */
    public function render(ContractCoverageResult $result, bool $verbose = false): array
    {
        $summary = ContractCoverageSummary::fromResult($result);

        $lines = [
            ...$this->renderHeader($result),
            ...$this->renderCounters($summary),
            ...$this->renderSchemaDefects($summary->schemaDefects),
            ...$this->renderSections($summary->warningSections),
            ...$this->renderSections(
                $summary->gapSections,
                $this->declarersByResponseAttributeKey($summary, $result->operationDeclarers),
            ),
        ];

        if ($verbose) {
            $lines = [...$lines, ...$this->renderSections($summary->coveredSections)];
        }

        $lines[] = '';
        $lines[] = static::SEPARATOR;
        $lines[] = $this->renderVerdict($result);

        if (!$verbose && $summary->hasCollapsedEntries()) {
            $lines[] = static::VERBOSE_HINT;
        }

        return $lines;
    }

    /**
     * @return array<string>
     */
    protected function renderHeader(ContractCoverageResult $result): array
    {
        return [
            'API contract coverage',
            sprintf(
                'Scope: %d of %d generated resources enforced',
                count($result->selectedResources),
                $result->generatedResourceCount,
            ),
            sprintf(
                '  (%s)',
                $result->selectedResources === [] ? 'none' : implode(', ', $result->selectedResources),
            ),
        ];
    }

    /**
     * @return array<string>
     */
    protected function renderCounters(ContractCoverageSummary $summary): array
    {
        return [
            '',
            sprintf(
                static::COUNTER_FORMAT_OPERATIONS,
                'Operations',
                sprintf('%d/%d', $summary->coveredOperationCount, $summary->operationTotal()),
                $summary->uncoveredOperationCount,
                $summary->staleOperationCount,
                $summary->nonServableOperationCount,
                $summary->internalOperationCount,
            ),
            sprintf(
                static::COUNTER_FORMAT_VALIDATIONS,
                'Validation rules',
                sprintf('%d/%d', $summary->coveredValidationCount, $summary->validationTotal()),
                $summary->uncoveredValidationCount,
                $summary->staleValidationCount,
            ),
            sprintf(
                static::COUNTER_FORMAT_RESPONSE_ATTRIBUTES,
                'Response attributes',
                sprintf('%d/%d', $summary->coveredResponseAttributeCount, $summary->responseAttributeTotal()),
                $summary->uncoveredResponseAttributeCount,
            ),
            sprintf(static::COUNTER_FORMAT_SCHEMA_DEFECTS, 'Schema defects', $summary->schemaDefectCount),
        ];
    }

    /**
     * Rendered as its own block rather than as a key list: the reader needs the file to edit and the
     * command to rerun, which a one-line entry cannot carry.
     *
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\SchemaDefect> $schemaDefects
     *
     * @return array<string>
     */
    protected function renderSchemaDefects(array $schemaDefects): array
    {
        $lines = [];

        foreach ($schemaDefects as $schemaDefect) {
            $lines[] = '';
            $lines[] = sprintf(
                static::SCHEMA_DEFECT_HEADING,
                $schemaDefect->resource,
                $schemaDefect->operation->dispatchKey(),
            );
            $lines[] = static::SCHEMA_DEFECT_EXPLANATION;

            foreach ($schemaDefect->schemaFiles as $schemaFile) {
                $lines[] = '    - ' . $schemaFile;
            }

            $lines[] = static::SCHEMA_DEFECT_REGENERATE_HINT;
        }

        return $lines;
    }

    /**
     * @param array<string, array<string>> $sections
     * @param array<string, array<string>> $declarersByKey Entry key => the tests to name beneath it.
     *
     * @return array<string>
     */
    protected function renderSections(array $sections, array $declarersByKey = []): array
    {
        $lines = [];

        foreach ($sections as $label => $keys) {
            $lines[] = '';
            $lines[] = sprintf('[%s] (%d)', $label, count($keys));

            foreach ($keys as $key) {
                $lines[] = '  - ' . $key;

                foreach ($declarersByKey[$key] ?? [] as $declarer) {
                    $lines[] = sprintf(static::DECLARER_FORMAT, $declarer);
                }
            }
        }

        return $lines;
    }

    /**
     * The tests that already declare the operation are where its response-attribute marker belongs,
     * so every uncovered attribute names them instead of leaving the sweep to look them up.
     *
     * @param array<string, array<string>> $operationDeclarers
     *
     * @return array<string, array<string>>
     */
    protected function declarersByResponseAttributeKey(ContractCoverageSummary $summary, array $operationDeclarers): array
    {
        $declarersByKey = [];

        foreach ($summary->uncoveredResponseAttributes as $responseAttribute) {
            $declarers = $operationDeclarers[$responseAttribute->dispatchKey] ?? [];
            $declarersByKey[$responseAttribute->key()] = $declarers === []
                ? [static::NO_DECLARER_PLACEHOLDER]
                : $declarers;
        }

        return $declarersByKey;
    }

    protected function renderVerdict(ContractCoverageResult $result): string
    {
        if ($result->isSuccessful()) {
            return '<info>Contract coverage gate: PASS</info>';
        }

        return sprintf(
            '<error>Contract coverage gate: FAIL (%s)</error>',
            implode(', ', $result->failureReasons()),
        );
    }
}
