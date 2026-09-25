<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The counted, sorted, label-ordered view of a {@see ContractCoverageResult}. Every renderer reads
 * the report through this, so the console and the markdown summary can never drift in what they
 * count, what they call a section, or the order they list them in.
 *
 * Stale claims stay out of the covered/total ratio — they point at something the generated truth
 * no longer contains.
 */
readonly class ContractCoverageSummary
{
    /**
     * Named rather than inlined like the sibling labels: the markdown renderer recognises this one
     * section to render it as a table.
     */
    public const string SECTION_UNCOVERED_RESPONSE_ATTRIBUTES = 'Uncovered response attributes';

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $uncoveredResponseAttributes Sorted by key.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\SchemaDefect> $schemaDefects Sorted by resource then operation.
     * @param array<string, array<string>> $warningSections Label to sorted entry keys, empty sections removed.
     * @param array<string, array<string>> $gapSections Label to sorted entry keys, empty sections removed.
     * @param array<string, array<string>> $coveredSections Label to sorted entry keys, empty sections removed.
     */
    protected function __construct(
        public int $coveredOperationCount,
        public int $uncoveredOperationCount,
        public int $staleOperationCount,
        public int $nonServableOperationCount,
        public int $internalOperationCount,
        public int $coveredValidationCount,
        public int $uncoveredValidationCount,
        public int $staleValidationCount,
        public int $coveredResponseAttributeCount,
        public int $uncoveredResponseAttributeCount,
        public int $schemaDefectCount,
        public array $uncoveredResponseAttributes,
        public array $schemaDefects,
        public array $warningSections,
        public array $gapSections,
        public array $coveredSections,
    ) {
    }

    public static function fromResult(ContractCoverageResult $result): self
    {
        $report = $result->report;
        $nonServableOperations = $result->scope->enforcedTruth->nonServableOperations;
        $internalOperations = $result->scope->enforcedTruth->internalOperations;

        return new self(
            count($report->coveredOperations),
            count($report->uncoveredOperations),
            count($report->staleOperations),
            count($nonServableOperations),
            count($internalOperations),
            count($report->coveredValidations),
            count($report->uncoveredValidations),
            count($report->staleValidations),
            count($report->coveredResponseAttributes),
            count($report->uncoveredResponseAttributes),
            count($result->schemaDefects),
            static::sortedResponseAttributes($report->uncoveredResponseAttributes),
            static::sortedDefects($result->schemaDefects),
            // An item GET with no provider only mints IRIs, so no test can assert on it and the gate
            // cannot demand coverage. It is still almost always a resource that should have had a
            // provider, so it is reported on every run instead of being filed with the covered set.
            static::withoutEmptySections([
                'Non-servable operations (no provider — review the resource)' => static::sortedKeys($nonServableOperations),
            ]),
            static::withoutEmptySections([
                'Uncovered operations' => static::sortedKeys($report->uncoveredOperations),
                'Stale operation claims' => static::sortedKeys($report->staleOperations),
                'Uncovered validation rules' => static::sortedKeys($report->uncoveredValidations),
                'Stale validation claims' => static::sortedKeys($report->staleValidations),
                static::SECTION_UNCOVERED_RESPONSE_ATTRIBUTES => static::sortedKeys($report->uncoveredResponseAttributes),
            ]),
            // An operation the schema declares internal answers the warning above rather than
            // raising it, so it collapses with the covered set instead of being listed every run.
            static::withoutEmptySections([
                'Covered operations' => static::sortedKeys($report->coveredOperations),
                'Covered validation rules' => static::sortedKeys($report->coveredValidations),
                'Covered response attributes' => static::sortedKeys($report->coveredResponseAttributes),
                'Internal operations (IRI anchors, not reachable and not published)' => static::sortedKeys($internalOperations),
            ]),
        );
    }

    public function operationTotal(): int
    {
        return $this->coveredOperationCount + $this->uncoveredOperationCount;
    }

    public function validationTotal(): int
    {
        return $this->coveredValidationCount + $this->uncoveredValidationCount;
    }

    public function responseAttributeTotal(): int
    {
        return $this->coveredResponseAttributeCount + $this->uncoveredResponseAttributeCount;
    }

    /**
     * Whether the default report hid anything, and so whether pointing at verbose mode helps.
     */
    public function hasCollapsedEntries(): bool
    {
        return $this->coveredSections !== [];
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation|\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint|\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $entries
     *
     * @return array<string>
     */
    protected static function sortedKeys(array $entries): array
    {
        return static::sorted(array_map(
            static fn (ApiOperation|ValidationConstraint|ResponseAttribute $entry): string => $entry->key(),
            $entries,
        ));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $responseAttributes
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute>
     */
    protected static function sortedResponseAttributes(array $responseAttributes): array
    {
        usort($responseAttributes, static fn (ResponseAttribute $a, ResponseAttribute $b): int => $a->key() <=> $b->key());

        return $responseAttributes;
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\SchemaDefect> $schemaDefects
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\SchemaDefect>
     */
    protected static function sortedDefects(array $schemaDefects): array
    {
        usort($schemaDefects, static fn (SchemaDefect $a, SchemaDefect $b): int => $a->key() <=> $b->key());

        return $schemaDefects;
    }

    /**
     * @param array<string> $keys
     *
     * @return array<string>
     */
    protected static function sorted(array $keys): array
    {
        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, array<string>> $sections
     *
     * @return array<string, array<string>>
     */
    protected static function withoutEmptySections(array $sections): array
    {
        return array_filter($sections, static fn (array $keys): bool => $keys !== []);
    }
}
