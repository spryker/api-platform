<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * Validates JSON:API request body for type field presence and correctness.
 * Also validates trailing-slash resource-ID-missing requests.
 */
class JsonApiRequestValidatorSubscriber implements EventSubscriberInterface
{
    protected const string EMPTY_SEGMENT_PLACEHOLDER = '__empty__';

    protected const string ERROR_DETAIL_INVALID_TYPE = 'Invalid type.';

    protected const string ERROR_DETAIL_POST_DATA_INVALID = 'Post data is invalid.';

    protected const string ERROR_DETAIL_RESOURCE_ID_NOT_SPECIFIED = 'Resource id is not specified.';

    public function __construct(
        protected RouterInterface $router,
        protected ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
    }

    /**
     * @return array<string, array<int, array{string, int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequestTrailingSlash', 33],
                ['onKernelRequestTypeValidation', 1],
            ],
        ];
    }

    /**
     * Runs before the router (priority 33 > 32) to catch trailing-slash requests
     * like PATCH /carts/ or DELETE /carts/ before Symfony returns HTML 404.
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     *
     * @return void
     */
    public function onKernelRequestTrailingSlash(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $method = $request->getMethod();
        $pathInfo = $request->getPathInfo();

        if (in_array($method, ['PATCH', 'DELETE'], true) && $this->isMissingResourceId($pathInfo)) {
            // Only throw for API Platform routes; legacy Glue endpoints
            // handle missing resource IDs through their own routing/error logic.
            if ($this->matchesApiPlatformResource($request, $pathInfo)) {
                throw new GlueApiException(
                    Response::HTTP_BAD_REQUEST,
                    '',
                    static::ERROR_DETAIL_RESOURCE_ID_NOT_SPECIFIED,
                );
            }
        }

        // Normalize trailing slashes for GET/POST so routes match with or without trailing slash.
        // PATCH/DELETE trailing slashes are already handled above (Resource id not specified).
        if (!in_array($method, ['PATCH', 'DELETE'], true) && str_ends_with($pathInfo, '/') && strlen($pathInfo) > 1) {
            if (!$this->normalizeTrailingSlash($request)) {
                // When normalizeTrailingSlash could not match a collection/post route
                // (e.g. GET /abstract-products/), pre-route with an empty identifier
                // so the provider can throw its own module-specific error.
                if ($method === 'GET' && $this->isSimpleResourcePath($pathInfo)) {
                    $this->preRouteTrailingSlashGetRequest($request, $pathInfo);
                }
            }
        }

        // Handle URLs with empty path segments (e.g. /customers//carts or /customers//addresses/uuid)
        // which indicate a missing required parameter
        if ($this->hasEmptyPathSegment($pathInfo)) {
            // PATCH/DELETE with an empty path segment means a resource ID is missing.
            // Throw 400 for both API Platform routes and legacy Glue endpoints.
            if (in_array($method, ['PATCH', 'DELETE'], true)) {
                throw new GlueApiException(
                    Response::HTTP_BAD_REQUEST,
                    '',
                    static::ERROR_DETAIL_RESOURCE_ID_NOT_SPECIFIED,
                );
            }

            // For GET/POST: if the normalized URL matches an API Platform route,
            // pre-route the request so the provider handles validation and throws its own error.
            if ($this->preRouteEmptySegmentRequest($request, $pathInfo)) {
                return;
            }

            // No API Platform route matched. Let the legacy Glue router handle the path with //
            // directly — the UriParser correctly produces an empty-string resource ID from //,
            // which the controller then handles to return the appropriate JSON error.
            return;
        }

        // Handle URLs where nginx merge_slashes normalized // to /,
        // resulting in consecutive resource-name segments (e.g. /carts/shared-carts
        // from /carts//shared-carts). Try inserting an empty segment placeholder
        // between adjacent resource-name segments and pre-route if a match is found.
        if ($this->preRouteMergedSlashesRequest($request, $pathInfo)) {
            return;
        }
    }

    /**
     * Runs after routing (priority 1) to validate JSON:API type field in the request body.
     * Only runs for API Platform routes (where _api_operation is set by the router).
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     *
     * @return void
     */
    public function onKernelRequestTypeValidation(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only validate on API Platform routes
        if ($request->attributes->get('_api_operation') === null && $request->attributes->get('_api_resource_class') === null) {
            return;
        }

        $method = $request->getMethod();

        if (!in_array($method, ['POST', 'PATCH'], true)) {
            return;
        }

        $content = $request->getContent();

        // Empty string or empty JSON array — action-style POST that sends no body (e.g. start-picking).
        if ($content === '' || $content === '[]') {
            return;
        }

        $body = json_decode((string)$content, true);

        if (!is_array($body) || !isset($body['data'])) {
            throw new GlueApiException(
                Response::HTTP_BAD_REQUEST,
                '',
                static::ERROR_DETAIL_POST_DATA_INVALID,
            );
        }

        $data = $body['data'];

        // Support collection PATCH/POST where the body contains an array of resources (data: [...])
        $firstItem = array_is_list($data) && isset($data[0]) ? $data[0] : $data;

        if (!array_key_exists('type', $firstItem)) {
            throw new GlueApiException(
                Response::HTTP_BAD_REQUEST,
                '',
                static::ERROR_DETAIL_POST_DATA_INVALID,
            );
        }

        $acceptedTypes = $this->resolveAcceptedTypes($request);

        if ($acceptedTypes !== [] && !in_array($firstItem['type'], $acceptedTypes, true)) {
            throw new GlueApiException(
                Response::HTTP_BAD_REQUEST,
                '',
                static::ERROR_DETAIL_INVALID_TYPE,
            );
        }

        // Sanitize empty strings to null for non-string typed properties
        // so the Symfony validator can report proper errors instead of the
        // serializer throwing a raw denormalization exception.
        $this->sanitizeRequestBody($request, $body);
    }

    /**
     * Converts empty string values to null for properties that have non-string types
     * in the target resource class. This prevents deserialization TypeErrors and allows
     * validation constraints (NotBlank, Type, etc.) to generate proper error messages.
     *
     * @param array<string, mixed> $body
     *
     * @return void
     */
    protected function sanitizeRequestBody(Request $request, array $body): void
    {
        $resourceClass = $request->attributes->get('_api_resource_class');

        if ($resourceClass === null || !class_exists($resourceClass) || !isset($body['data']['attributes'])) {
            return;
        }

        $attributes = $body['data']['attributes'];
        $modified = false;

        foreach ($attributes as $key => $value) {
            if ($value !== '') {
                continue;
            }

            try {
                $property = new ReflectionProperty($resourceClass, $key);
                $propertyType = $property->getType();

                if ($propertyType instanceof ReflectionNamedType && !in_array($propertyType->getName(), ['string', 'mixed'], true)) {
                    $body['data']['attributes'][$key] = null;
                    $modified = true;
                }
            } catch (ReflectionException) {
            }
        }

        if ($modified) {
            $request->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                (string)json_encode($body),
            );
        }
    }

    /**
     * Pre-routes the request to the route that matches the path without trailing slash.
     * Setting route attributes (including _controller) causes Symfony's RouterListener
     * to skip re-routing, so the trailing slash is effectively ignored.
     */

    /**
     * @return bool True if the normalized path matched a valid route and pre-routing was applied.
     */
    protected function normalizeTrailingSlash(Request $request): bool
    {
        $pathInfo = $request->getPathInfo();
        $normalized = rtrim($pathInfo, '/');

        if ($normalized === '' || $normalized === $pathInfo) {
            return false;
        }

        $this->router->getContext()->fromRequest($request);

        try {
            $match = $this->router->match($normalized);
        } catch (ResourceNotFoundException) {
            return false;
        }

        if (!isset($match['_api_resource_class'])) {
            return false;
        }

        foreach ($match as $key => $value) {
            $request->attributes->set($key, $value);
        }

        // Override the cached pathInfo so API Platform uses the normalized
        // path for URI variable extraction instead of the trailing-slash path.
        $reflection = new ReflectionProperty(Request::class, 'pathInfo');
        $reflection->setValue($request, $normalized);

        // Resolve and set the API Platform operation so the provider/processor
        // receives the correct operation type (e.g., GetCollection vs Get).
        $resourceClass = $match['_api_resource_class'];
        $operationName = $match['_api_operation_name'] ?? null;

        if ($operationName !== null) {
            try {
                $metadata = $this->resourceMetadataCollectionFactory->create($resourceClass);
                $request->attributes->set('_api_operation', $metadata->getOperation($operationName));
            } catch (Throwable) {
            }
        }

        return true;
    }

    /**
     * Normalizes empty path segments and pre-routes the request to the matched API Platform provider.
     * Sets route attributes directly on the Request so Symfony's RouterListener skips routing.
     * Empty URI variables are set to null so providers can validate and throw their own errors.
     */
    protected function preRouteEmptySegmentRequest(Request $request, string $pathInfo): bool
    {
        $normalized = preg_replace('#/{2,}#', '/' . static::EMPTY_SEGMENT_PLACEHOLDER . '/', $pathInfo);
        $normalized = rtrim((string)$normalized, '/');

        if ($normalized === '' || $normalized === $pathInfo) {
            return false;
        }

        // Set router context from request so the method matches
        // because this subscriber runs before RouterListener sets the context.
        $this->router->getContext()->fromRequest($request);

        try {
            $match = $this->router->match($normalized);
        } catch (ResourceNotFoundException) {
            return false;
        } catch (Throwable) {
            return false;
        }

        if (!isset($match['_api_resource_class'])) {
            return false;
        }

        // Pre-set route attributes, replacing empty-segment placeholders with empty string
        // so providers receive empty URI variables and can throw appropriate errors
        foreach ($match as $key => $value) {
            $request->attributes->set(
                $key,
                $value === static::EMPTY_SEGMENT_PLACEHOLDER ? '' : $value,
            );
        }

        return true;
    }

    /**
     * Collapses consecutive slashes in the request pathInfo so the legacy
     * Glue router can parse the URL. Without this, UriParser::splitPath()
     * produces empty-string segments from //, which don't match any resource plugin.
     *
     * @return void
     */
    protected function collapseConsecutiveSlashes(Request $request): void
    {
        $pathInfo = $request->getPathInfo();
        $normalized = preg_replace('#/{2,}#', '/', $pathInfo);

        if ($normalized === $pathInfo) {
            return;
        }

        $reflection = new ReflectionProperty(Request::class, 'pathInfo');
        $reflection->setValue($request, $normalized);
    }

    protected function hasEmptyPathSegment(string $pathInfo): bool
    {
        return str_contains($pathInfo, '//');
    }

    /**
     * Detects when a PATCH/DELETE request is missing the resource identifier.
     * Covers trailing-slash cases (e.g. PATCH /carts/), nested collection
     * endpoints without an ID (e.g. PATCH /customers/{ref}/addresses),
     * and top-level resource paths without an ID (e.g. PATCH /shared-carts).
     */
    protected function isMissingResourceId(string $pathInfo): bool
    {
        $trimmed = rtrim($pathInfo, '/');
        $segments = array_values(array_filter(explode('/', $trimmed), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return false;
        }

        $lastSegment = end($segments);

        // Trailing slash: /carts/ or /customers/{ref}/addresses/
        if (str_ends_with($pathInfo, '/') && !$this->looksLikeUuid($lastSegment)) {
            return true;
        }

        // Top-level resource without ID: PATCH /shared-carts or DELETE /carts
        if (count($segments) === 1 && $this->looksLikeResourceName($lastSegment)) {
            return true;
        }

        // Nested collection without trailing slash: /customers/{ref}/addresses
        // When the last segment is a resource name and the previous segment is
        // an identifier, the request targets a collection without a specific ID
        $previousSegment = $segments[count($segments) - 2] ?? '';

        if (count($segments) >= 3 && $this->looksLikeResourceName($lastSegment) && !$this->looksLikeResourceName($previousSegment)) {
            return true;
        }

        return false;
    }

    protected function looksLikeResourceName(string $value): bool
    {
        return (bool)preg_match('/^[a-z][a-z-]*$/', $value);
    }

    protected function looksLikeUuid(string $value): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * Returns a list of accepted type values for the current route.
     * Only the operation's shortName is accepted as a valid type.
     *
     * @return array<string>
     */
    protected function resolveAcceptedTypes(Request $request): array
    {
        $operation = $request->attributes->get('_api_operation');

        if (is_object($operation) && method_exists($operation, 'getShortName')) {
            $shortName = $operation->getShortName();

            if ($shortName !== null) {
                return [$shortName];
            }
        }

        // Fallback: resolve shortName from the resource metadata collection
        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');

        if ($resourceClass === null) {
            return [];
        }

        try {
            $metadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);
            $resolvedOperation = $metadataCollection->getOperation($operationName);
            $shortName = $resolvedOperation->getShortName();

            if ($shortName !== null) {
                return [$shortName];
            }
        } catch (Throwable) {
            // Metadata not available for this resource class
        }

        return [];
    }

    /**
     * Checks if the path is a simple /{resource-name}/ pattern (single segment + trailing slash).
     */
    protected function isSimpleResourcePath(string $pathInfo): bool
    {
        $trimmed = rtrim($pathInfo, '/');
        $segments = array_values(array_filter(explode('/', $trimmed), static fn (string $segment): bool => $segment !== ''));

        return count($segments) === 1 && $this->looksLikeResourceName($segments[0]);
    }

    /**
     * Pre-routes a GET /{resource}/ request by trying to match /{resource}/{placeholder}
     * as a Get operation. Sets the identifier to empty string so the provider throws its own error.
     */
    protected function preRouteTrailingSlashGetRequest(Request $request, string $pathInfo): bool
    {
        $trimmed = rtrim($pathInfo, '/');
        $testPath = $trimmed . '/' . static::EMPTY_SEGMENT_PLACEHOLDER;

        try {
            $match = $this->router->match($testPath);
        } catch (ResourceNotFoundException) {
            return false;
        }

        if (!isset($match['_api_resource_class'])) {
            return false;
        }

        foreach ($match as $key => $value) {
            $request->attributes->set(
                $key,
                $value === static::EMPTY_SEGMENT_PLACEHOLDER ? '' : $value,
            );
        }

        return true;
    }

    /**
     * Checks whether the given path resolves to an API Platform resource.
     * Tries the actual request method first, then falls back to GET, since
     * some resources only have PATCH/DELETE operations without a GET route.
     * Legacy Glue routes never set _api_resource_class, so they return false.
     */
    protected function matchesApiPlatformResource(Request $request, string $pathInfo): bool
    {
        // Normalize the path: replace consecutive slashes with placeholder,
        // append placeholder if the path ends with a resource name (missing ID)
        $normalized = preg_replace('#/{2,}#', '/' . static::EMPTY_SEGMENT_PLACEHOLDER . '/', $pathInfo);
        $normalized = rtrim((string)$normalized, '/');

        $segments = array_values(array_filter(
            explode('/', $normalized),
            static fn (string $segment): bool => $segment !== '',
        ));

        $lastSegment = end($segments);

        if ($lastSegment !== false && $this->looksLikeResourceName($lastSegment)) {
            $normalized .= '/' . static::EMPTY_SEGMENT_PLACEHOLDER;
        }

        $this->router->getContext()->fromRequest($request);

        $originalMethod = $this->router->getContext()->getMethod();

        // Try the actual request method first, then fall back to GET
        foreach ([$originalMethod, 'GET'] as $method) {
            $this->router->getContext()->setMethod($method);

            try {
                $match = $this->router->match($normalized);
            } catch (Throwable) {
                continue;
            }

            $this->router->getContext()->setMethod($originalMethod);

            return isset($match['_api_resource_class']);
        }

        $this->router->getContext()->setMethod($originalMethod);

        return false;
    }

    /**
     * Detects paths where nginx merge_slashes collapsed // into /,
     * resulting in consecutive resource-name segments (e.g. /carts/shared-carts
     * from original /carts//shared-carts). Tries inserting an empty-segment
     * placeholder between adjacent resource-name pairs and pre-routes if a match is found.
     */
    protected function preRouteMergedSlashesRequest(Request $request, string $pathInfo): bool
    {
        $trimmed = rtrim($pathInfo, '/');
        $segments = array_values(array_filter(explode('/', $trimmed), static fn (string $segment): bool => $segment !== ''));

        if (count($segments) < 2) {
            return false;
        }

        // Ensure the router context reflects the current request method
        // because this subscriber runs before RouterListener sets the context.
        $this->router->getContext()->fromRequest($request);

        // If the path already matches a route, do not modify it.
        // This prevents false positives where a resource ID happens to look like
        // a resource name (e.g. "fake", "default", "mine").
        try {
            $this->router->match($trimmed);

            return false;
        } catch (Throwable) {
            // No route matched — proceed with merged-slash detection
        }

        // Find pairs of consecutive resource-name segments
        $segmentCount = count($segments);

        for ($i = 0; $i < $segmentCount - 1; $i++) {
            if (!$this->looksLikeResourceName($segments[$i]) || !$this->looksLikeResourceName($segments[$i + 1])) {
                continue;
            }

            // Insert placeholder between the consecutive resource-name segments
            $expanded = $segments;
            array_splice($expanded, $i + 1, 0, [static::EMPTY_SEGMENT_PLACEHOLDER]);
            $testPath = '/' . implode('/', $expanded);

            try {
                $match = $this->router->match($testPath);
            } catch (ResourceNotFoundException | MethodNotAllowedException) {
                // No route matched with the placeholder inserted. Determine if
                // segments[i+1] is a real sub-resource type or just an ID that
                // happens to look like a resource name (e.g. "fake-uuid").
                // Restore // only when we're confident a merged slash was collapsed:
                // - segments[i+2] is a resource name (alternating type/id/type pattern)
                // - OR the original path also doesn't match any Symfony route
                //   (meaning it's a legacy Glue URL, and the sub-resource might need //)
                //   AND segments[i+1] has at least one hyphen (real resource names are
                //   typically hyphenated multi-word, while IDs are single words or UUIDs)
                $isLikelySubResource = isset($segments[$i + 2]) && $this->looksLikeResourceName($segments[$i + 2]);

                if (!$isLikelySubResource && str_contains($segments[$i + 1], '-') && strlen($segments[$i + 1]) > 5) {
                    $isLikelySubResource = true;
                }

                if ($isLikelySubResource) {
                    // Verify the restored path actually matches a route before modifying the request.
                    // Without this check, old REST API paths where IDs happen to match the
                    // resource-name pattern (e.g. "mywishlist") get corrupted by a false positive.
                    $restoredSegments = $segments;
                    array_splice($restoredSegments, $i + 1, 0, ['']);
                    $restoredPath = '/' . implode('/', $restoredSegments);

                    try {
                        $this->router->match($restoredPath);
                        $this->restoreEmptyPathSegment($request, $segments, $i);
                    } catch (ResourceNotFoundException | MethodNotAllowedException) {
                        // Restored path doesn't match either — the original path was correct.
                    }
                }

                return false;
            }

            if (!isset($match['_api_resource_class'])) {
                // Route matched a legacy Glue endpoint, not API Platform.
                // Restore the empty segment so GlueRouter parses it correctly.
                $this->restoreEmptyPathSegment($request, $segments, $i);

                return false;
            }

            foreach ($match as $key => $value) {
                $request->attributes->set(
                    $key,
                    $value === static::EMPTY_SEGMENT_PLACEHOLDER ? '' : $value,
                );
            }

            return true;
        }

        return false;
    }

    /**
     * Restores an empty path segment between consecutive resource-name segments
     * that nginx merge_slashes collapsed. The legacy GlueRouter's UriParser needs
     * the // to correctly pair resource types with their identifiers.
     *
     * @param array<string> $segments
     *
     * @return void
     */
    protected function restoreEmptyPathSegment(Request $request, array $segments, int $insertAtIndex): void
    {
        array_splice($segments, $insertAtIndex + 1, 0, ['']);
        $restoredPath = '/' . implode('/', $segments);

        $reflection = new ReflectionProperty(Request::class, 'pathInfo');
        $reflection->setValue($request, $restoredPath);
    }
}
