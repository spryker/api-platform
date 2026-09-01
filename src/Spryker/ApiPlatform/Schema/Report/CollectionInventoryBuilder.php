<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Report;

/**
 * Classifies every list-shaped property of a merged resource schema by how well the published
 * contract describes its elements. Drives the untyped-collection inventory and its drift gate.
 *
 * {@see CollectionState} documents what each classification means. Scalar lists need no adoption and
 * are omitted so the inventory stays a work list. Rows carry the state's backing string rather than
 * the enum itself: they are JSON-encoded verbatim into the committed artifact.
 */
class CollectionInventoryBuilder
{
    protected const string NESTED_PATH_SEGMENT = 'items';

    /**
     * @param array<string, mixed> $mergedSchema
     *
     * @return array<array{apiType: string, resource: string, property: string, state: string, itemKeyCount: int}>
     */
    public function build(array $mergedSchema, string $apiType): array
    {
        // `name`, not `shortName`: {@see \Spryker\ApiPlatform\Command\ApiCollectionsReportCommand}
        // groups and merges schemas by `name`, so keying rows on anything else lets two distinct
        // resources that share a `shortName` collapse onto one label — the row key stops being
        // unique per merge unit and the drift gate can no longer say which resource regressed.
        $resource = (string)($mergedSchema['name'] ?? $mergedSchema['shortName'] ?? 'unknown');
        $properties = $mergedSchema['properties'] ?? [];

        if (!is_array($properties)) {
            return [];
        }

        $includes = $mergedSchema['includes'] ?? [];
        $includes = is_array($includes) ? $includes : [];

        $rows = $this->collectRows($properties, $apiType, $resource, '', $includes, false);

        usort($rows, static fn (array $left, array $right): int => $left['property'] <=> $right['property']);

        return $rows;
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<int, array<string, mixed>> $includes Only meaningful at the top level (`$pathPrefix === ''`);
     *   `includes:` can only ever collide with a top-level property, never a nested one.
     *
     * @return array<array{apiType: string, resource: string, property: string, state: string, itemKeyCount: int}>
     */
    protected function collectRows(
        array $properties,
        string $apiType,
        string $resource,
        string $pathPrefix,
        array $includes,
        bool $isHandwrittenContext
    ): array {
        $rows = [];

        foreach ($properties as $propertyName => $property) {
            if (!is_array($property)) {
                continue;
            }

            $path = $pathPrefix === '' ? (string)$propertyName : $pathPrefix . '.' . $propertyName;

            if (($property['type'] ?? null) === 'array') {
                $state = $pathPrefix === '' && $this->isTopLevelRelationshipProperty($property, (string)$propertyName, $includes)
                    ? CollectionState::RELATIONSHIP
                    : $this->resolveState($property, $isHandwrittenContext);

                if ($state !== null) {
                    $rows[] = [
                        'apiType' => $apiType,
                        'resource' => $resource,
                        'property' => $path,
                        'state' => $state->value,
                        'itemKeyCount' => $this->countItemKeys($property),
                    ];
                }
            }

            if (isset($property['properties']) && is_array($property['properties'])) {
                $rows = array_merge($rows, $this->collectRows(
                    $property['properties'],
                    $apiType,
                    $resource,
                    $path,
                    [],
                    $isHandwrittenContext,
                ));
            }

            $rows = array_merge($rows, $this->collectNestedItemRows($property, $apiType, $resource, $path, $isHandwrittenContext));
        }

        return $rows;
    }

    /**
     * Both blocks describe the elements of the same list, so they share one `.items` path — only one of
     * them may contribute rows or a property carrying both emits two rows under the same key with
     * conflicting states. A real `items` wins: it is what the generator actually reads, and
     * {@see resolveState()} and {@see countItemKeys()} already resolve the pair in this same order.
     *
     * @param array<string, mixed> $property
     *
     * @return array<array{apiType: string, resource: string, property: string, state: string, itemKeyCount: int}>
     */
    protected function collectNestedItemRows(
        array $property,
        string $apiType,
        string $resource,
        string $path,
        bool $isHandwrittenContext
    ): array {
        $nestedPath = $path . '.' . static::NESTED_PATH_SEGMENT;

        if (isset($property['items']['properties']) && is_array($property['items']['properties'])) {
            return $this->collectRows($property['items']['properties'], $apiType, $resource, $nestedPath, [], $isHandwrittenContext);
        }

        // `openapiContext` is copied verbatim by the parser and never fed to the real generator
        // (see `resolveState()`'s `$isHandwrittenContext` parameter), so a list nested inside one
        // — e.g. `productOfferServicePointAvailabilityResponseItems` inside
        // `product-offer-service-point-availabilities::productOfferServicePointAvailabilities` —
        // is exactly as hand-described as its parent, even when it declares its own sibling
        // `items.properties`. Without this branch that nested list was invisible to both the
        // inventory and the drift gate.
        if (isset($property['openapiContext']['items']['properties']) && is_array($property['openapiContext']['items']['properties'])) {
            return $this->collectRows($property['openapiContext']['items']['properties'], $apiType, $resource, $nestedPath, [], true);
        }

        return [];
    }

    /**
     * Returns null for a list that needs no adoption (a list of scalars).
     *
     * @param array<string, mixed> $property
     * @param bool $isHandwrittenContext True when this property was reached by recursing into an
     *   `openapiContext` block: the real generator never sees anything inside `openapiContext`, so a
     *   sibling `items.properties` found there is hand-described documentation, not a registered child
     *   definition — it must classify as `handwritten`, never `typed`.
     */
    protected function resolveState(array $property, bool $isHandwrittenContext): ?CollectionState
    {
        if (isset($property['objectName'])) {
            return CollectionState::CANONICAL;
        }

        if (isset($property['items']['properties']) && is_array($property['items']['properties'])) {
            return $isHandwrittenContext ? CollectionState::HANDWRITTEN : CollectionState::TYPED;
        }

        if (isset($property['items'])) {
            return null;
        }

        if (isset($property['openapiContext']['items']['properties']) && is_array($property['openapiContext']['items']['properties'])) {
            return CollectionState::HANDWRITTEN;
        }

        if (isset($property['openapiContext']['items'])) {
            return null;
        }

        $example = $property['openapiContext']['example'] ?? null;

        if (is_array($example) && $example !== []) {
            return is_array(reset($example)) ? CollectionState::EXAMPLE_ONLY : null;
        }

        return CollectionState::UNKNOWN;
    }

    /**
     * Mirrors `PropertyValidationRule::findWinningRelationshipInclude()` — the predicate that decides
     * whether `RelationshipPhpDocGenerator` would produce a relationship docblock for this property,
     * and whether `PropertyValidationRule::validateRelationshipItemsCollision()` would then reject an
     * `items` block on it. A property matching this predicate is not an author-written collection: it
     * is either the auto-generated `type: array, writable: false, readable: false` placeholder
     * `SchemaParser::normalizeProperties()` synthesizes for an `includes:` entry with no explicit
     * counterpart, or an explicit property that happens to occupy the same relationship slot. Either
     * way, adding `items` to it does not type a real collection — the relationship docblock wins the
     * single available slot and the validator rejects the change. Duplicated rather than shared: this
     * builder lives in the reporting layer and the validator in the schema layer, and the two must
     * stay in step by mirroring, not by a cross-layer dependency — same rationale as the validator's
     * own duplication of the generator's predicate.
     *
     * @param array<string, mixed> $property
     * @param array<int, array<string, mixed>> $includes
     */
    protected function isTopLevelRelationshipProperty(array $property, string $propertyName, array $includes): bool
    {
        if (($property['type'] ?? '') !== 'array') {
            return false;
        }

        if ($property['writable'] ?? false) {
            return false;
        }

        if (($property['readable'] ?? null) === true) {
            return false;
        }

        foreach ($includes as $include) {
            // isset() is null-safe against a malformed non-array include entry, so no is_array()
            // guard is needed before this check — mirrors the same note on
            // `PropertyValidationRule::findWinningRelationshipInclude()`.
            if (isset($include['resolverClass'])) {
                continue;
            }

            $relationshipName = $include['relationshipName'] ?? null;

            if (!is_string($relationshipName)) {
                continue;
            }

            if ($this->kebabToCamelCase($relationshipName) === $propertyName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mirrors `PropertyValidationRule::kebabToCamelCase()`; see the note on
     * {@see isTopLevelRelationshipProperty()} about keeping the two in step.
     */
    protected function kebabToCamelCase(string $value): string
    {
        if (!str_contains($value, '-')) {
            return $value;
        }

        return lcfirst(str_replace('-', '', ucwords($value, '-')));
    }

    /**
     * @param array<string, mixed> $property
     */
    protected function countItemKeys(array $property): int
    {
        if (isset($property['items']['properties']) && is_array($property['items']['properties'])) {
            return count($property['items']['properties']);
        }

        if (isset($property['openapiContext']['items']['properties']) && is_array($property['openapiContext']['items']['properties'])) {
            return count($property['openapiContext']['items']['properties']);
        }

        $example = $property['openapiContext']['example'] ?? null;

        if (is_array($example) && $example !== [] && is_array(reset($example))) {
            return count((array)reset($example));
        }

        return 0;
    }
}
