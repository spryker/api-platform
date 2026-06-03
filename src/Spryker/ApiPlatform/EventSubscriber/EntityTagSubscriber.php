<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use JsonException;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\Client\EntityTag\EntityTagClientInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Activates ETag/If-Match handling for API Platform operations declared with the
 * `extraProperties` flags below in their resource YAML:
 *
 * - `entityTag: read` — `GET` reads the stored ETag (or lazy-writes one from the response payload).
 * - `entityTag: write` — `POST`/`PATCH` overwrite the stored ETag with a fresh hash of the response payload.
 * - `ifMatchRequired: true` — the request must carry a matching `If-Match` header (HTTP 428 / 412).
 *
 * Header emission is delegated to {@see ETagResponseSubscriber}, which converts the
 * `_etag` request attribute into the `ETag` response header.
 *
 * @deprecated Use {@link \Spryker\Glue\EntityTagsRestApi\Api\Storefront\Listener\EntityTagStorefrontListener} instead.
 */
class EntityTagSubscriber implements EventSubscriberInterface
{
    /**
     * Matches {@see ETagResponseSubscriber::REQUEST_ATTRIBUTE_ETAG} so the response header is emitted automatically.
     */
    protected const string REQUEST_ATTRIBUTE_ETAG = '_etag';

    protected const string REQUEST_ATTRIBUTE_API_OPERATION = '_api_operation';

    protected const string REQUEST_ATTRIBUTE_API_OPERATION_NAME = '_api_operation_name';

    protected const string REQUEST_ATTRIBUTE_API_RESOURCE_CLASS = '_api_resource_class';

    protected const string REQUEST_ATTRIBUTE_API_URI_VARIABLES = '_api_uri_variables';

    protected const string REQUEST_ATTRIBUTE_ROUTE_PARAMS = '_route_params';

    protected const string EXTRA_PROPERTY_ENTITY_TAG = 'entityTag';

    protected const string EXTRA_PROPERTY_IF_MATCH_REQUIRED = 'ifMatchRequired';

    protected const string ENTITY_TAG_MODE_READ = 'read';

    protected const string ENTITY_TAG_MODE_WRITE = 'write';

    protected const string HEADER_IF_MATCH = 'If-Match';

    /**
     * Populated by {@see IdentityRequestSubscriber} (priority 7) on every authenticated request.
     */
    protected const string REQUEST_ATTRIBUTE_OAUTH_IDENTITY_CLAIMS = '_oauth_identity_claims';

    /**
     * @see \Spryker\Glue\EntityTagsRestApi\EntityTagsRestApiConfig::RESPONSE_CODE_IF_MATCH_HEADER_MISSING
     */
    protected const string RESPONSE_CODE_IF_MATCH_HEADER_MISSING = '005';

    /**
     * @see \Spryker\Glue\EntityTagsRestApi\EntityTagsRestApiConfig::RESPONSE_CODE_IF_MATCH_HEADER_INVALID
     */
    protected const string RESPONSE_CODE_IF_MATCH_HEADER_INVALID = '006';

    /**
     * @see \Spryker\Glue\EntityTagsRestApi\EntityTagsRestApiConfig::RESPONSE_DETAIL_IF_MATCH_HEADER_MISSING
     */
    protected const string RESPONSE_DETAIL_IF_MATCH_HEADER_MISSING = 'If-Match header is missing.';

    /**
     * @see \Spryker\Glue\EntityTagsRestApi\EntityTagsRestApiConfig::RESPONSE_DETAIL_IF_MATCH_HEADER_INVALID
     */
    protected const string RESPONSE_DETAIL_IF_MATCH_HEADER_INVALID = 'If-Match header value is invalid.';

    /**
     * Runs after API Platform's `DenyAccessListener` (priority 3) so 401/403 responses are not
     * shadowed by 412/428 from this subscriber, and still well before the controller.
     */
    protected const int PRIORITY_REQUEST = 1;

    /**
     * Runs before {@see ETagResponseSubscriber} (priority 0) so the `_etag` attribute is in place when it emits the header.
     */
    protected const int PRIORITY_RESPONSE = 4;

    public function __construct(
        protected EntityTagClientInterface $entityTagClient,
        protected ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', static::PRIORITY_REQUEST],
            KernelEvents::RESPONSE => ['onKernelResponse', static::PRIORITY_RESPONSE],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $operation = $this->getOperation($request);

        if ($operation === null) {
            return;
        }

        $extraProperties = $operation->getExtraProperties();

        if (($extraProperties[static::EXTRA_PROPERTY_IF_MATCH_REQUIRED] ?? false) !== true) {
            return;
        }

        // API Platform 4 evaluates the security expression (`is_granted(...)`) inside the State Provider
        // chain via `AccessCheckerProvider` — i.e. *after* `kernel.request`. If we throw 412/428 here for
        // unauthenticated requests, we shadow the 401/403 the security stack would have produced.
        // Skip the check when no identity has been resolved by `IdentityRequestSubscriber` and let the
        // downstream security stack respond.
        if ($request->attributes->get(static::REQUEST_ATTRIBUTE_OAUTH_IDENTITY_CLAIMS) === null) {
            return;
        }

        $headerValue = $request->headers->get(static::HEADER_IF_MATCH);

        if ($headerValue === null || $headerValue === '') {
            throw new GlueApiException(
                Response::HTTP_PRECONDITION_REQUIRED,
                static::RESPONSE_CODE_IF_MATCH_HEADER_MISSING,
                static::RESPONSE_DETAIL_IF_MATCH_HEADER_MISSING,
            );
        }

        $resourceName = $operation->getShortName();
        $resourceId = $this->resolveResourceIdFromUri($request);

        if ($resourceName === null || $resourceId === null) {
            return;
        }

        $storedEntityTag = $this->entityTagClient->read($resourceName, $resourceId);

        if ($storedEntityTag === null || trim($headerValue, '"') !== $storedEntityTag) {
            throw new GlueApiException(
                Response::HTTP_PRECONDITION_FAILED,
                static::RESPONSE_CODE_IF_MATCH_HEADER_INVALID,
                static::RESPONSE_DETAIL_IF_MATCH_HEADER_INVALID,
            );
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $operation = $this->getOperation($request);

        if ($operation === null) {
            return;
        }

        $mode = $operation->getExtraProperties()[static::EXTRA_PROPERTY_ENTITY_TAG] ?? null;

        if ($mode !== static::ENTITY_TAG_MODE_READ && $mode !== static::ENTITY_TAG_MODE_WRITE) {
            return;
        }

        $response = $event->getResponse();

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return;
        }

        $payload = $this->decodeJsonApiPayload((string)$response->getContent());

        if ($payload === null) {
            return;
        }

        $resourceName = $operation->getShortName();
        $resourceId = (string)$payload['id'];
        $attributes = is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [];

        if ($resourceName === null || $resourceId === '') {
            return;
        }

        $entityTag = $mode === static::ENTITY_TAG_MODE_READ
            ? ($this->entityTagClient->read($resourceName, $resourceId)
                ?? $this->entityTagClient->write($resourceName, $resourceId, $attributes))
            : $this->entityTagClient->write($resourceName, $resourceId, $attributes);

        $request->attributes->set(static::REQUEST_ATTRIBUTE_ETAG, $entityTag);
    }

    protected function getOperation(Request $request): ?Operation
    {
        $operation = $request->attributes->get(static::REQUEST_ATTRIBUTE_API_OPERATION);

        if ($operation instanceof Operation) {
            return $operation;
        }

        // `_api_operation` may not be populated yet on the early `kernel.request` priority
        // we run at — fall back to resolving it from the metadata factory using the route
        // attributes set by Symfony Router (`_api_resource_class` + `_api_operation_name`).
        $resourceClass = $request->attributes->get(static::REQUEST_ATTRIBUTE_API_RESOURCE_CLASS);
        $operationName = $request->attributes->get(static::REQUEST_ATTRIBUTE_API_OPERATION_NAME);

        if (!is_string($resourceClass) || !is_string($operationName)) {
            return null;
        }

        try {
            return $this->resourceMetadataCollectionFactory->create($resourceClass)->getOperation($operationName);
        } catch (Throwable) {
            return null;
        }
    }

    protected function resolveResourceIdFromUri(Request $request): ?string
    {
        // `_api_uri_variables` is set by an API Platform listener that runs later in the kernel.request
        // pipeline — it is unavailable at our priority. Fall back to `_route_params`, which Symfony Router
        // populates at priority 32 (before us) with the same key/value pairs from the matched route.
        foreach ([static::REQUEST_ATTRIBUTE_API_URI_VARIABLES, static::REQUEST_ATTRIBUTE_ROUTE_PARAMS] as $attribute) {
            $variables = $request->attributes->get($attribute);

            if (!is_array($variables) || $variables === []) {
                continue;
            }

            foreach ($variables as $name => $value) {
                if (str_starts_with((string)$name, '_')) {
                    continue;
                }

                if (is_scalar($value) && (string)$value !== '') {
                    return (string)$value;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJsonApiPayload(string $body): ?array
    {
        if ($body === '') {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
            return null;
        }

        if (!isset($decoded['data']['id']) || !is_scalar($decoded['data']['id'])) {
            return null;
        }

        return $decoded['data'];
    }
}
