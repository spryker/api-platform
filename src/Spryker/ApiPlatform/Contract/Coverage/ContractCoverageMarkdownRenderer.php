<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Renders a {@see ContractCoverageResult} as markdown for the GitHub Actions run summary page, so
 * the gaps are visible without expanding the job log. Covered entries are never listed: the summary
 * page is a place to see what is missing, and the console report already carries the full picture.
 */
class ContractCoverageMarkdownRenderer
{
    protected const string HEADING = '## API contract coverage';

    protected const string TABLE_HEADER = '| Section | Covered | Uncovered | Stale |';

    protected const string TABLE_DIVIDER = '| --- | ---: | ---: | ---: |';

    protected const string ROW_FORMAT = '| %s | %d / %d | %d | %d |';

    protected const string RESPONSE_ATTRIBUTE_TABLE_HEADER = '| Operation | Attribute | Covered by |';

    protected const string RESPONSE_ATTRIBUTE_TABLE_DIVIDER = '| --- | --- | --- |';

    protected const string RESPONSE_ATTRIBUTE_ROW_FORMAT = '| `%s` | `%s` | %s |';

    protected const string NO_DECLARER_PLACEHOLDER = '(no test declares this operation yet)';

    public function render(ContractCoverageResult $result): string
    {
        $summary = ContractCoverageSummary::fromResult($result);

        $lines = [
            static::HEADING,
            '',
            $this->renderVerdict($result),
            '',
            static::TABLE_HEADER,
            static::TABLE_DIVIDER,
            sprintf(
                static::ROW_FORMAT,
                'Operations',
                $summary->coveredOperationCount,
                $summary->operationTotal(),
                $summary->uncoveredOperationCount,
                $summary->staleOperationCount,
            ),
            sprintf(
                static::ROW_FORMAT,
                'Validation rules',
                $summary->coveredValidationCount,
                $summary->validationTotal(),
                $summary->uncoveredValidationCount,
                $summary->staleValidationCount,
            ),
            '',
            $this->renderScope($result),
        ];

        if ($summary->schemaDefects !== []) {
            $lines[] = '';
            $lines[] = sprintf('### Schema defects (%d)', $summary->schemaDefectCount);
            $lines[] = '';
            $lines[] = 'Declare the operation\'s responses under `openapiContext.responses`, then rerun '
                . '`vendor/bin/glue api:generate`.';
            $lines[] = '';

            foreach ($summary->schemaDefects as $schemaDefect) {
                $lines[] = sprintf(
                    '- `%s` `%s` — %s',
                    $schemaDefect->resource,
                    $schemaDefect->operation->dispatchKey(),
                    implode(', ', array_map(
                        static fn (string $schemaFile): string => sprintf('`%s`', $schemaFile),
                        $schemaDefect->schemaFiles,
                    )),
                );
            }
        }

        foreach ([...$summary->warningSections, ...$summary->gapSections] as $label => $keys) {
            $lines[] = '';
            $lines[] = sprintf('### %s (%d)', $label, count($keys));
            $lines[] = '';

            // A `GET /orders  orderReference` key reads as one run-on string in a bullet list, and
            // the tests to mark do not fit one either, so this dimension gets a table instead.
            if ($label === ContractCoverageSummary::SECTION_UNCOVERED_RESPONSE_ATTRIBUTES) {
                $lines = [
                    ...$lines,
                    ...$this->renderResponseAttributeTable($summary->uncoveredResponseAttributes, $result->operationDeclarers),
                ];

                continue;
            }

            foreach ($keys as $key) {
                $lines[] = sprintf('- `%s`', $key);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $uncoveredResponseAttributes
     * @param array<string, array<string>> $operationDeclarers
     *
     * @return array<string>
     */
    protected function renderResponseAttributeTable(array $uncoveredResponseAttributes, array $operationDeclarers): array
    {
        $lines = [static::RESPONSE_ATTRIBUTE_TABLE_HEADER, static::RESPONSE_ATTRIBUTE_TABLE_DIVIDER];

        foreach ($uncoveredResponseAttributes as $responseAttribute) {
            $declarers = $operationDeclarers[$responseAttribute->dispatchKey] ?? [];
            $lines[] = sprintf(
                static::RESPONSE_ATTRIBUTE_ROW_FORMAT,
                $responseAttribute->dispatchKey,
                $responseAttribute->path,
                $declarers === [] ? static::NO_DECLARER_PLACEHOLDER : implode(', ', array_map(
                    static fn (string $declarer): string => sprintf('`%s`', $declarer),
                    $declarers,
                )),
            );
        }

        return $lines;
    }

    protected function renderVerdict(ContractCoverageResult $result): string
    {
        if ($result->isSuccessful()) {
            return '✅ **PASS**';
        }

        return sprintf('❌ **FAIL** — %s', implode(', ', $result->failureReasons()));
    }

    protected function renderScope(ContractCoverageResult $result): string
    {
        $resources = array_map(
            static fn (string $resource): string => sprintf('`%s`', $resource),
            $result->selectedResources,
        );

        return sprintf(
            'Enforced scope: %d of %d generated resources — %s',
            count($result->selectedResources),
            $result->generatedResourceCount,
            $resources === [] ? 'none' : implode(', ', $resources),
        );
    }
}
