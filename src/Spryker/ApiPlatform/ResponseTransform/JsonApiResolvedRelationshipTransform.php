<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\ResponseTransform;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;
use WeakMap;

/**
 * Injects resolver-based relationship data into the JSON:API response document.
 * URI-template-based relationships are handled natively by API Platform via
 * property population in RelationshipProvider.
 */
class JsonApiResolvedRelationshipTransform
{
    public const string REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS = '_spryker_resolved_relationships';

    /**
     * @var array<string, class-string>|null
     */
    protected ?array $resourceClassIndex = null;

    /**
     * @var array<string, int>|null
     */
    protected ?array $includedSortPriorityIndex = null;

    /**
     * Per-class reflection metadata cache (perf). Reflection results are static per class,
     * so memoizing them avoids re-reflecting the same class for every resource instance in a
     * response. Values are identical to on-demand reflection, so response output is unchanged.
     *
     * @var array<class-string, array{apiResource: \ApiPlatform\Metadata\ApiResource|null, shortName: string|null, props: array<string, array{apiProperty: \ApiPlatform\Metadata\ApiProperty|null, serializedName: string|null}>}>
     */
    protected array $classMetaCache = [];

    /**
     * Memo of normalizeRelatedResource() results, keyed by the resource object itself.
     *
     * normalizeRelatedResource() runs the full API Platform serializer on a related resource, and the
     * same object is normalized more than once per request: once for its included[] entry, again in
     * buildRefsForParent() only to read type/id for a relationship ref, and once per reference when a
     * resource is shared across parents. The result is a pure function of the (within-request immutable)
     * object, so memoizing per object collapses those redundant serializer passes while returning
     * an identical structure. Callers receive copy-on-write array values, so their mutations cannot
     * leak back into the cache.
     *
     * A WeakMap keys entries by the live object: an entry disappears together with its object, so a
     * recycled object id can never be served another object's payload, and the cache cannot grow
     * stale or unbounded when the service outlives a request (worker runtimes, container reuse).
     *
     * @var \WeakMap<object, array<string, mixed>|null>
     */
    protected WeakMap $normalizedRelatedResourceCache;

    public function __construct(protected NormalizerInterface $normalizer)
    {
        $this->normalizedRelatedResourceCache = new WeakMap();
    }

    /**
     * Returns memoized reflection metadata for a resource class: its #[ApiResource] instance,
     * short name, and per public property its #[ApiProperty] instance and #[SerializedName].
     * Property order matches ReflectionClass::getProperties(IS_PUBLIC) (declaration order).
     *
     * @param class-string $className
     *
     * @return array{apiResource: \ApiPlatform\Metadata\ApiResource|null, shortName: string|null, props: array<string, array{apiProperty: \ApiPlatform\Metadata\ApiProperty|null, serializedName: string|null}>}
     */
    protected function getClassMeta(string $className): array
    {
        if (isset($this->classMetaCache[$className])) {
            return $this->classMetaCache[$className];
        }

        $reflection = new ReflectionClass($className);
        $apiResourceAttrs = $reflection->getAttributes(ApiResource::class);
        $apiResource = $apiResourceAttrs === [] ? null : $apiResourceAttrs[0]->newInstance();

        $props = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            // Static properties are not resource state; consumers read props via `$resource->{$name}`.
            if ($property->isStatic()) {
                continue;
            }

            $apiPropertyAttr = $property->getAttributes(ApiProperty::class)[0] ?? null;
            $serializedNameAttr = $property->getAttributes(SerializedName::class)[0] ?? null;
            $props[$property->getName()] = [
                'apiProperty' => $apiPropertyAttr?->newInstance(),
                'serializedName' => $serializedNameAttr?->newInstance()->getSerializedName(),
            ];
        }

        $this->classMetaCache[$className] = [
            'apiResource' => $apiResource,
            'shortName' => $apiResource?->getShortName(),
            'props' => $props,
        ];

        return $this->classMetaCache[$className];
    }

    /**
     * Injects resolver-based relationships into an already-decoded JSON:API document.
     *
     * @param array<string, mixed> $data
     *
     * @return bool Whether the document was modified.
     */
    public function applyTo(array &$data, Request $request): bool
    {
        $modified = $this->injectResolvedRelationships($request, $data);
        $modified = $this->stripNonReadableFromIncluded($data) || $modified;
        $modified = $this->normalizeIncludedApiPlatformRelationships($data) || $modified;

        return $modified;
    }

    /**
     * Builds relationships and included sections from resource objects resolved
     * by the RelationshipProvider. Uses API Platform's own normalizer to serialize
     * each related resource into proper JSON:API format.
     *
     * @param array<string, mixed> $data
     */
    protected function injectResolvedRelationships(Request $request, array &$data): bool
    {
        $resolvedRelationships = $request->attributes->get(static::REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS, []);

        if (!$resolvedRelationships || !isset($data['data'])) {
            return false;
        }

        $perItemRelationships = $request->attributes->get('_spryker_per_item_relationships', []);

        $modified = false;
        $included = [];
        $seenIncluded = [];

        // Pre-populate seen set from already-included resources to prevent duplicates.
        // Register both the raw ID (which may be an IRI) and the extracted entity ID,
        // because resolver-based resources use entity IDs while AP-native resources
        // may use IRI-based IDs.
        foreach ($data['included'] ?? [] as $existingIncluded) {
            if (!isset($existingIncluded['type'], $existingIncluded['id'])) {
                continue;
            }

            $rawId = (string)$existingIncluded['id'];
            $seenIncluded[sprintf('%s:%s', $existingIncluded['type'], $rawId)] = true;

            $entityId = $this->extractEntityIdFromIri($rawId);

            if ($entityId !== $rawId) {
                $seenIncluded[sprintf('%s:%s', $existingIncluded['type'], $entityId)] = true;
            }
        }

        $mainResourceType = $this->resolveMainResourceType($data['data']);

        foreach ($resolvedRelationships as $relationshipName => $resources) {
            if (!$resources) {
                continue;
            }

            $relationshipRefs = [];

            foreach ($resources as $resource) {
                $normalized = $this->normalizeRelatedResource($resource);

                if (!$normalized) {
                    continue;
                }

                $relationshipRefs[] = [
                    'type' => $normalized['type'],
                    'id' => $normalized['id'],
                ];

                // Deduplicate included resources
                $deduplicationKey = sprintf('%s:%s', $normalized['type'], $normalized['id']);

                if (!isset($seenIncluded[$deduplicationKey])) {
                    $included[] = $normalized;
                    $seenIncluded[$deduplicationKey] = true;
                }
            }

            if (!$relationshipRefs) {
                continue;
            }

            // Nested paths (e.g. wishlist-items.concrete-products) are relationships of
            // included resources, not of the main resource. They are injected into each
            // included item's own relationships by injectNestedRelationshipsIntoIncluded.
            if (str_contains($relationshipName, '.')) {
                continue;
            }

            // Skip self-referential relationships: when a nested include resolves to
            // the same type as the main resource (e.g. concrete-products as a child of
            // bundled-products on a concrete-products endpoint).
            if ($relationshipName === $mainResourceType) {
                continue;
            }

            // Don't overwrite relationships already set by AP-native property population
            if ($this->hasExistingRelationship($data['data'], $relationshipName)) {
                continue;
            }

            $perItemDataForRelationship = $this->findPerItemDataForRelationship($perItemRelationships, $relationshipName);

            $this->attachRelationshipToData($data, $relationshipName, $relationshipRefs, $perItemDataForRelationship);

            $modified = true;
        }

        if ($included) {
            $data['included'] = array_merge($included, $data['included'] ?? []);
            $modified = true;
        }

        $modified = $this->injectNestedRelationshipsIntoIncluded($data, $resolvedRelationships, $perItemRelationships) || $modified;

        if ($modified && isset($data['included']) && is_array($data['included'])) {
            $data['included'] = $this->sortIncludedResources($data['included']);
        }

        return $modified;
    }

    /**
     * Injects nested relationship data (e.g., concrete-products) into their parent included resources
     * (e.g., items), using per-item tracking to correctly associate each child with its parent.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<object>> $resolvedRelationships
     * @param array<string, array<string, array<object>>> $perItemRelationships
     */
    protected function injectNestedRelationshipsIntoIncluded(
        array &$data,
        array $resolvedRelationships,
        array $perItemRelationships,
    ): bool {
        if (!isset($data['included']) || !is_array($data['included'])) {
            return false;
        }

        $modified = false;

        foreach ($resolvedRelationships as $relationshipName => $resources) {
            if (!str_contains($relationshipName, '.')) {
                continue;
            }

            $lastDotPos = (int)strrpos($relationshipName, '.');
            $parentPath = substr($relationshipName, 0, $lastDotPos);
            $parentLeafName = str_contains($parentPath, '.')
                ? substr($parentPath, (int)strrpos($parentPath, '.') + 1)
                : $parentPath;
            $childLeafName = substr($relationshipName, $lastDotPos + 1);

            $perItemData = $this->findPerItemDataForRelationship($perItemRelationships, $relationshipName);

            if ($perItemData === null) {
                continue;
            }

            foreach ($data['included'] as &$includedItem) {
                if (($includedItem['type'] ?? '') !== $parentLeafName) {
                    continue;
                }

                if (isset($includedItem['relationships'][$childLeafName])) {
                    continue;
                }

                $parentId = (string)($includedItem['id'] ?? '');
                $refs = $this->buildRefsForParent($parentId, $perItemData);

                if (!$refs) {
                    continue;
                }

                $includedItem['relationships'][$childLeafName] = ['data' => $refs];
                $modified = true;
            }
        }

        return $modified;
    }

    /**
     * Normalizes a related resource object into JSON:API format.
     * Uses API Platform's serialization chain, with a fallback to manual
     * normalization when AP throws (e.g. missing sub-operation metadata).
     *
     * @return array<string, mixed>|null
     */
    protected function normalizeRelatedResource(object $resource): ?array
    {
        // offsetExists() instead of `??=` so that null results (failed normalization) are memoized too,
        // matching the previous array_key_exists() semantics.
        if ($this->normalizedRelatedResourceCache->offsetExists($resource)) {
            return $this->normalizedRelatedResourceCache[$resource];
        }

        return $this->normalizedRelatedResourceCache[$resource] = $this->doNormalizeRelatedResource($resource);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function doNormalizeRelatedResource(object $resource): ?array
    {
        try {
            $normalized = $this->normalizer->normalize($resource, 'jsonapi', [
                'resource_class' => $resource::class,
            ]);

            if (is_array($normalized) && isset($normalized['data']['type'], $normalized['data']['id'])) {
                // AP normalizer returns IRI-based IDs (e.g. /picking-list-items/uuid).
                // Extract the entity identifier from the IRI so relationship refs use plain UUIDs.
                // If extraction yields an empty string (e.g. AP cannot generate an IRI for
                // collection-only resources with no item-level URI), fall through to manual normalization.
                $entityId = $this->extractEntityIdFromIri((string)$normalized['data']['id']);

                if ($entityId !== '') {
                    $normalized['data']['id'] = $entityId;
                    $this->stripNonReadableAttributes($resource, $normalized['data']);
                    $this->restoreNullAttributes($resource, $normalized['data']);
                    $this->overrideNestedCollectionSelfLink($resource, $normalized['data']);

                    return $normalized['data'];
                }
            }
        } catch (Throwable) {
            // AP normalization failed — fall back to manual extraction
        }

        return $this->normalizeRelatedResourceManually($resource);
    }

    /**
     * Manually builds JSON:API structure from the resource object's public properties.
     *
     * @return array<string, mixed>|null
     */
    protected function normalizeRelatedResourceManually(object $resource): ?array
    {
        $className = $resource::class;
        $meta = $this->getClassMeta($className);
        $shortName = $meta['shortName'];

        if ($shortName === null) {
            return null;
        }

        $identifier = null;
        $attributes = [];

        foreach ($meta['props'] as $name => $propMeta) {
            $value = $resource->{$name};
            $apiProperty = $propMeta['apiProperty'];

            if ($apiProperty !== null) {
                if ($apiProperty->isReadable() === false) {
                    continue;
                }

                if ($apiProperty->isIdentifier()) {
                    $identifier = $value;
                    // Fall through: keep identifier in attributes too, matching the main-resource
                    // serialization path and legacy Glue REST output (id duplicated in attributes).
                }
            }

            // Skip relationship arrays (resource objects)
            if (is_array($value) && $value !== [] && is_object(reset($value))) {
                continue;
            }

            $serializedName = $propMeta['serializedName'] ?? $name;
            $attributes[$serializedName] = $value;
        }

        if ($identifier === null) {
            return null;
        }

        return [
            'type' => $shortName,
            'id' => (string)$identifier,
            'attributes' => $attributes,
            'links' => [
                'self' => $this->buildSelfLink($className, $resource, $shortName, $identifier),
            ],
        ];
    }

    /**
     * Builds the self-link for a resource. For collection-only resources included as nested
     * sub-resources (e.g. abstract-product-prices under abstract-products), uses the first
     * GetCollection operation that has URI variables to construct the correct nested URL.
     * Falls back to the flat {shortName}/{identifier} format.
     *
     * @param class-string $className
     */
    protected function buildSelfLink(string $className, object $resource, string $shortName, mixed $identifier): string
    {
        $apiResource = $this->getClassMeta($className)['apiResource'];

        if ($apiResource === null) {
            return sprintf('%s/%s', $shortName, $identifier);
        }

        foreach ($apiResource->getOperations() ?? [] as $operation) {
            if (!($operation instanceof CollectionOperationInterface)) {
                continue;
            }

            $uriVariables = $operation->getUriVariables();

            if (!$uriVariables) {
                continue;
            }

            $uriTemplate = $operation->getUriTemplate();

            if ($uriTemplate === null) {
                continue;
            }

            $url = $uriTemplate;
            $allResolved = true;

            foreach ($uriVariables as $parameterName => $link) {
                $propertyName = $link->getIdentifiers()[0] ?? $parameterName;

                if (!property_exists($resource, $propertyName)) {
                    $allResolved = false;

                    break;
                }

                $value = $resource->{$propertyName};

                if ($value === null) {
                    $allResolved = false;

                    break;
                }

                $url = str_replace(sprintf('{%s}', $parameterName), (string)$value, $url);
            }

            if ($allResolved) {
                return $url;
            }
        }

        return sprintf('%s/%s', $shortName, $identifier);
    }

    /**
     * When AP generates an IRI for a collection-only resource, it uses the hidden NotExposed operation
     * which produces a flat URL (e.g. /abstract-product-prices/{id}). For resources that are included
     * as nested sub-resources, the self-link should instead point to the nested collection URL
     * (e.g. /abstract-products/{sku}/abstract-product-prices).
     *
     * This method detects such cases and replaces the flat URL with the correct nested one.
     *
     * @param array<string, mixed> $normalizedData
     */
    protected function overrideNestedCollectionSelfLink(object $resource, array &$normalizedData): void
    {
        $currentSelfLink = $normalizedData['links']['self'] ?? null;

        if (!is_string($currentSelfLink)) {
            return;
        }

        $apiResource = $this->getClassMeta($resource::class)['apiResource'];

        if ($apiResource === null) {
            return;
        }

        // Only override for collection-only resources. Resources that have an explicit Get item
        // operation already receive a correct AP-generated self-link and must not be touched.
        foreach ($apiResource->getOperations() ?? [] as $operation) {
            if ($operation instanceof Get) {
                return;
            }
        }

        foreach ($apiResource->getOperations() ?? [] as $operation) {
            if (!($operation instanceof CollectionOperationInterface)) {
                continue;
            }

            $uriVariables = $operation->getUriVariables();

            if (!$uriVariables) {
                continue;
            }

            $uriTemplate = $operation->getUriTemplate();

            if ($uriTemplate === null) {
                continue;
            }

            $url = $uriTemplate;
            $allResolved = true;

            foreach ($uriVariables as $parameterName => $link) {
                $propertyName = $link->getIdentifiers()[0] ?? $parameterName;

                if (!property_exists($resource, $propertyName) || $resource->{$propertyName} === null) {
                    $allResolved = false;

                    break;
                }

                $url = str_replace(sprintf('{%s}', $parameterName), (string)$resource->{$propertyName}, $url);
            }

            if (!$allResolved) {
                continue;
            }

            // Preserve scheme+host from the AP-generated self-link (which uses the ABS_URL strategy)
            $baseUrl = $this->extractBaseUrl($currentSelfLink);
            $normalizedData['links']['self'] = $baseUrl !== null ? $baseUrl . $url : $url;

            return;
        }
    }

    protected function extractBaseUrl(string $url): ?string
    {
        if (!str_starts_with($url, 'http')) {
            return null;
        }

        $parsed = parse_url($url);

        if (!isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        $base = $parsed['scheme'] . '://' . $parsed['host'];

        if (isset($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }

        return $base;
    }

    /**
     * Converts API Platform native to-one IRI-based relationships in included items to
     * to-many plain-ID format, matching legacy Glue REST API behavior expected by tests.
     *
     * @param array<string, mixed> $data
     */
    protected function normalizeIncludedApiPlatformRelationships(array &$data): bool
    {
        if (!isset($data['included']) || !is_array($data['included'])) {
            return false;
        }

        $modified = false;

        foreach ($data['included'] as &$item) {
            if (!isset($item['relationships']) || !is_array($item['relationships'])) {
                continue;
            }

            foreach ($item['relationships'] as &$relData) {
                if (!isset($relData['data']) || !is_array($relData['data'])) {
                    continue;
                }

                // Skip if already in to-many format (indexed array of resource objects)
                if (isset($relData['data'][0])) {
                    continue;
                }

                // Convert to-one (associative array with 'type' key) to to-many array and strip IRI from id
                if (isset($relData['data']['type'])) {
                    $relData['data']['id'] = $this->extractEntityIdFromIri((string)($relData['data']['id'] ?? ''));
                    $relData['data'] = [$relData['data']];
                    $modified = true;
                }
            }
        }

        return $modified;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function stripNonReadableFromIncluded(array &$data): bool
    {
        if (!isset($data['included']) || !is_array($data['included'])) {
            return false;
        }

        $modified = false;
        $nonReadableCache = [];

        foreach ($data['included'] as &$includedItem) {
            if (!isset($includedItem['type'], $includedItem['attributes']) || !is_array($includedItem['attributes'])) {
                continue;
            }

            $type = $includedItem['type'];

            if (!isset($nonReadableCache[$type])) {
                $nonReadableCache[$type] = $this->resolveNonReadableAttributeNames($type);
            }

            foreach ($nonReadableCache[$type] as $attrName) {
                if (array_key_exists($attrName, $includedItem['attributes'])) {
                    unset($includedItem['attributes'][$attrName]);
                    $modified = true;
                }
            }
        }

        return $modified;
    }

    /**
     * @return array<string>
     */
    protected function resolveNonReadableAttributeNames(string $resourceType): array
    {
        $className = $this->resolveResourceClassByShortName($resourceType);

        if ($className === null) {
            return [];
        }

        $nonReadable = [];

        foreach ($this->getClassMeta($className)['props'] as $name => $propMeta) {
            $apiProperty = $propMeta['apiProperty'];

            if ($apiProperty === null || $apiProperty->isReadable() !== false) {
                continue;
            }

            $serializedName = $propMeta['serializedName'] ?? $this->camelToKebabCase($name);
            $nonReadable[] = $serializedName;
        }

        return $nonReadable;
    }

    /**
     * @return class-string|null
     */
    protected function resolveResourceClassByShortName(string $shortName): ?string
    {
        if ($this->resourceClassIndex === null) {
            $this->resourceClassIndex = $this->buildResourceClassIndex();
        }

        return $this->resourceClassIndex[$shortName] ?? null;
    }

    /**
     * @return array<string, class-string>
     */
    protected function buildResourceClassIndex(): array
    {
        $index = [];
        $prefixes = ['Generated\\Api\\Storefront\\', 'Generated\\Api\\Backend\\'];

        foreach (get_declared_classes() as $className) {
            foreach ($prefixes as $prefix) {
                if (!str_starts_with($className, $prefix)) {
                    continue;
                }

                $ref = new ReflectionClass($className);
                $apiResourceAttrs = $ref->getAttributes(ApiResource::class);

                if ($apiResourceAttrs === []) {
                    continue;
                }

                $shortName = $apiResourceAttrs[0]->newInstance()->getShortName();

                if ($shortName !== null) {
                    $index[$shortName] = $className;
                }
            }
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $normalizedData
     */
    protected function stripNonReadableAttributes(object $resource, array &$normalizedData): void
    {
        if (!isset($normalizedData['attributes']) || !is_array($normalizedData['attributes'])) {
            return;
        }

        foreach ($this->getClassMeta($resource::class)['props'] as $name => $propMeta) {
            $apiProperty = $propMeta['apiProperty'];

            if ($apiProperty === null || $apiProperty->isReadable() !== false) {
                continue;
            }

            $serializedName = $propMeta['serializedName'] ?? $this->camelToKebabCase($name);
            unset($normalizedData['attributes'][$serializedName]);
        }
    }

    /**
     * Restores null values that the API Platform normalizer may have coerced
     * to empty strings or empty arrays during JSON:API serialization.
     *
     * @param array<string, mixed> $normalizedData
     */
    protected function restoreNullAttributes(object $resource, array &$normalizedData): void
    {
        if (!isset($normalizedData['attributes']) || !is_array($normalizedData['attributes'])) {
            return;
        }

        foreach ($this->getClassMeta($resource::class)['props'] as $name => $propMeta) {
            if ($resource->{$name} !== null) {
                continue;
            }

            $serializedName = $propMeta['serializedName'] ?? $this->camelToKebabCase($name);

            if (!array_key_exists($serializedName, $normalizedData['attributes'])) {
                continue;
            }

            $normalizedData['attributes'][$serializedName] = null;
        }
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    protected function resolveShortName(ReflectionClass $reflection): ?string
    {
        $apiResourceAttrs = $reflection->getAttributes(ApiResource::class);

        if ($apiResourceAttrs === []) {
            return null;
        }

        $apiResource = $apiResourceAttrs[0]->newInstance();

        return $apiResource->getShortName();
    }

    protected function resolveSerializedName(ReflectionProperty $property): ?string
    {
        $attrs = $property->getAttributes(SerializedName::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance()->getSerializedName();
    }

    /**
     * Attaches relationship linkage data to each resource item in the response.
     * When per-item data is available, each item gets only its own related resources.
     *
     * @param array<string, mixed> $data
     * @param array<array{type: string, id: string}> $relationshipRefs
     * @param array<string, array<object>>|null $perItemData Parent ID → related resources
     */
    protected function attachRelationshipToData(
        array &$data,
        string $relationshipName,
        array $relationshipRefs,
        ?array $perItemData = null,
    ): void {
        if ($this->isSingleResource($data['data'])) {
            $itemId = (string)($data['data']['id'] ?? '');
            $refs = $relationshipRefs;

            if ($perItemData !== null) {
                $perItemRefs = $this->buildRefsForParent($itemId, $perItemData);
                // Fall back to all relationship refs when per-item lookup fails.
                // This handles singleton resources (e.g. checkout, checkout-data) whose
                // JSON:API id is null, so the id-based lookup returns empty even though
                // the refs were resolved for this specific resource.
                $refs = $perItemRefs !== [] ? $perItemRefs : $relationshipRefs;
            }

            $data['data']['relationships'][$relationshipName] = ['data' => $refs];

            return;
        }

        foreach ($data['data'] as &$item) {
            if (!is_array($item)) {
                continue;
            }

            if ($perItemData !== null) {
                $itemId = (string)($item['id'] ?? '');
                $refs = $this->buildRefsForParent($itemId, $perItemData);
            } else {
                $refs = $relationshipRefs;
            }

            $item['relationships'][$relationshipName] = ['data' => $refs];
        }
    }

    /**
     * Builds relationship refs for a specific parent item from per-item data.
     *
     * @param array<string, array<object>> $perItemData
     *
     * @return array<array{type: string, id: string}>
     */
    protected function buildRefsForParent(string $parentId, array $perItemData): array
    {
        $resources = $perItemData[$parentId] ?? [];
        $refs = [];

        foreach ($resources as $resource) {
            $normalized = $this->normalizeRelatedResource($resource);

            if ($normalized !== null) {
                $refs[] = [
                    'type' => $normalized['type'],
                    'id' => $normalized['id'],
                ];
            }
        }

        return $refs;
    }

    /**
     * Finds per-item data for a given relationship name from the per-item relationships collection.
     *
     * @param array<string, array<string, array<object>>> $perItemRelationships Path → parent ID → resources
     *
     * @return array<string, array<object>>|null
     */
    protected function findPerItemDataForRelationship(array $perItemRelationships, string $relationshipName): ?array
    {
        return $perItemRelationships[$relationshipName] ?? null;
    }

    protected function hasExistingRelationship(mixed $data, string $relationshipName): bool
    {
        if ($this->isSingleResource($data)) {
            return isset($data['relationships'][$relationshipName]);
        }

        if (is_array($data) && isset($data[0]['relationships'][$relationshipName])) {
            return true;
        }

        return false;
    }

    protected function extractEntityIdFromIri(string $iri): string
    {
        if (!str_contains($iri, '/')) {
            return $iri;
        }

        $path = parse_url($iri, PHP_URL_PATH);

        if ($path === null || $path === false) {
            return $iri;
        }

        $segments = explode('/', rtrim($path, '/'));

        return end($segments) ?: $iri;
    }

    /**
     * Sorts included resources by `includedSortPriority` from each resource's
     * `#[ApiResource(extraProperties: ['includedSortPriority' => N])]`. Resources
     * with a higher priority appear later in the `included` array, matching the
     * REST API ordering where nested/child includes (abstract-products,
     * concrete-products) come first and direct cart-item-like resources come
     * last. Resources without an explicit priority default to 0. Within the
     * same priority, sort alphabetically by type.
     *
     * @param array<int, array<string, mixed>> $included
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sortIncludedResources(array $included): array
    {
        $priorityIndex = $this->getIncludedSortPriorityIndex();

        usort($included, function (array $a, array $b) use ($priorityIndex): int {
            $aPriority = $priorityIndex[$a['type'] ?? ''] ?? 0;
            $bPriority = $priorityIndex[$b['type'] ?? ''] ?? 0;

            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            return strcmp($a['type'] ?? '', $b['type'] ?? '');
        });

        return $included;
    }

    /**
     * @return array<string, int>
     */
    protected function getIncludedSortPriorityIndex(): array
    {
        if ($this->includedSortPriorityIndex !== null) {
            return $this->includedSortPriorityIndex;
        }

        if ($this->resourceClassIndex === null) {
            $this->resourceClassIndex = $this->buildResourceClassIndex();
        }

        $this->includedSortPriorityIndex = [];

        foreach ($this->resourceClassIndex as $shortName => $resourceClass) {
            $priority = $this->resolveIncludedSortPriority($resourceClass);

            if ($priority !== null) {
                $this->includedSortPriorityIndex[$shortName] = $priority;
            }
        }

        return $this->includedSortPriorityIndex;
    }

    /**
     * @param class-string $resourceClass
     */
    protected function resolveIncludedSortPriority(string $resourceClass): ?int
    {
        try {
            $apiResourceAttrs = (new ReflectionClass($resourceClass))->getAttributes(ApiResource::class);
        } catch (Throwable) {
            return null;
        }

        if ($apiResourceAttrs === []) {
            return null;
        }

        $extraProperties = $apiResourceAttrs[0]->newInstance()->getExtraProperties() ?? [];
        $priority = $extraProperties['includedSortPriority'] ?? null;

        return is_int($priority) ? $priority : null;
    }

    protected function camelToKebabCase(string $value): string
    {
        return strtolower((string)preg_replace('/[A-Z]/', '-$0', $value));
    }

    protected function isSingleResource(mixed $data): bool
    {
        return is_array($data) && isset($data['type']) && array_key_exists('id', $data);
    }

    protected function resolveMainResourceType(mixed $data): string
    {
        if ($this->isSingleResource($data)) {
            return $data['type'] ?? '';
        }

        if (is_array($data) && array_key_exists(0, $data) && is_array($data[0]) && isset($data[0]['type'])) {
            return $data[0]['type'];
        }

        return '';
    }
}
