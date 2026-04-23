<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Normalizes relationship keys from camelCase to kebab-case and converts
 * IRI-based IDs to entity identifiers in relationships and included resources.
 */
class JsonApiRelationshipNormalizerSubscriber implements EventSubscriberInterface
{
    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -257],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type') ?? '';

        if (!str_starts_with($contentType, static::CONTENT_TYPE_JSON_API)) {
            return;
        }

        $content = $response->getContent();

        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return;
        }

        $modified = $this->normalizeDataRelationships($data);
        $modified = $this->normalizeIncludedResources($data) || $modified;

        if ($modified) {
            $response->setContent((string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizeDataRelationships(array &$data): bool
    {
        if (!isset($data['data']) || !is_array($data['data'])) {
            return false;
        }

        $modified = false;

        if ($this->isSingleResource($data['data'])) {
            $modified = $this->normalizeResourceRelationships($data['data']);
        } else {
            foreach ($data['data'] as &$resource) {
                if (is_array($resource)) {
                    $modified = $this->normalizeResourceRelationships($resource) || $modified;
                }
            }
        }

        return $modified;
    }

    /**
     * @param array<string, mixed> $resource
     */
    protected function normalizeResourceRelationships(array &$resource): bool
    {
        if (!isset($resource['relationships']) || !is_array($resource['relationships'])) {
            return false;
        }

        $modified = false;
        $normalized = [];

        foreach ($resource['relationships'] as $key => $relationship) {
            $kebabKey = $this->camelToKebabCase($key);

            if ($kebabKey !== $key) {
                $modified = true;
            }

            if (isset($relationship['data']) && is_array($relationship['data'])) {
                $modified = $this->normalizeRelationshipIds($relationship['data']) || $modified;
            }

            $normalized[$kebabKey] = $relationship;
        }

        $resource['relationships'] = $normalized;

        return $modified;
    }

    /**
     * @param array<int|string, mixed> $relationshipData
     */
    protected function normalizeRelationshipIds(array &$relationshipData): bool
    {
        $modified = false;

        // Single relationship: {type, id}
        if (isset($relationshipData['type'], $relationshipData['id'])) {
            $entityId = $this->extractEntityIdFromIri((string)$relationshipData['id']);

            if ($entityId !== (string)$relationshipData['id']) {
                $relationshipData['id'] = $entityId;
                $modified = true;
            }

            return $modified;
        }

        // To-many relationship: [{type, id}, ...]
        foreach ($relationshipData as &$entry) {
            if (!is_array($entry) || !isset($entry['id'])) {
                continue;
            }

            $entityId = $this->extractEntityIdFromIri((string)$entry['id']);

            if ($entityId !== (string)$entry['id']) {
                $entry['id'] = $entityId;
                $modified = true;
            }
        }

        return $modified;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizeIncludedResources(array &$data): bool
    {
        if (!isset($data['included']) || !is_array($data['included'])) {
            return false;
        }

        $modified = false;

        foreach ($data['included'] as &$resource) {
            if (!is_array($resource) || !isset($resource['id'])) {
                continue;
            }

            $entityId = $this->extractEntityIdFromIri((string)$resource['id']);

            if ($entityId !== (string)$resource['id']) {
                $resource['id'] = $entityId;
                $modified = true;
            }

            if (!isset($resource['links']['self'])) {
                $resource['links']['self'] = sprintf(
                    '%s/%s',
                    $resource['type'] ?? '',
                    $entityId,
                );
                $modified = true;
            }
        }

        return $modified;
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

    protected function camelToKebabCase(string $value): string
    {
        return strtolower((string)preg_replace('/[A-Z]/', '-$0', $value));
    }

    protected function isSingleResource(mixed $data): bool
    {
        return is_array($data) && isset($data['type']) && array_key_exists('id', $data);
    }
}
