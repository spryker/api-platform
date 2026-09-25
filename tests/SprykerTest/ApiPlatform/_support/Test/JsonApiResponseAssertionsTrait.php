<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributePath;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON:API document decoding, extraction and assertions shared by every module's API end-to-end
 * suite. Mix into a test that extends {@see AbstractApiTestCase}.
 *
 * Scoped to the media type rather than the api type: both {@see BackendApiTestCase} and
 * {@see StorefrontApiTestCase} default to `application/vnd.api+json`, and both applications declare
 * `errorFormats` as jsonapi-only, so every suite on either side reads this same envelope.
 */
trait JsonApiResponseAssertionsTrait
{
    protected const string JSON_API_KEY_DATA = 'data';

    protected const string JSON_API_KEY_INCLUDED = 'included';

    protected const string JSON_API_KEY_ATTRIBUTES = 'attributes';

    protected const string JSON_API_KEY_RELATIONSHIPS = 'relationships';

    protected const string JSON_API_KEY_LINKS = 'links';

    protected const string JSON_API_KEY_SELF = 'self';

    protected const string JSON_API_KEY_ID = 'id';

    protected const string JSON_API_KEY_TYPE = 'type';

    protected const string JSON_API_KEY_ERRORS = 'errors';

    protected const string JSON_API_KEY_CODE = 'code';

    protected const string JSON_API_KEY_STATUS = 'status';

    protected const string JSON_API_KEY_DETAIL = 'detail';

    protected const string JSON_API_KEY_META = 'meta';

    /**
     * @uses \Spryker\ApiPlatform\ResponseTransform\PaginationLinksTransform
     */
    protected const string META_PAGINATION = 'pagination';

    protected const string META_TOTAL_ITEMS = 'totalItems';

    /**
     * @uses \Spryker\ApiPlatform\State\Provider\AbstractProvider::getPagination()
     */
    protected const string PAGINATION_KEY_NUM_FOUND = 'numFound';

    protected const string PAGINATION_KEY_CURRENT_PAGE = 'currentPage';

    protected const string PAGINATION_KEY_MAX_PAGE = 'maxPage';

    protected const string PAGINATION_KEY_CURRENT_ITEMS_PER_PAGE = 'currentItemsPerPage';

    /**
     * The same four keys as the attribute paths `assertResponseAttributes()` takes. The leaf keys
     * above address the decoded `pagination` block; these address it from the resource root, which
     * is how every collection test reaches it.
     */
    protected const string ATTRIBUTE_PAGINATION_NUM_FOUND = 'pagination.numFound';

    protected const string ATTRIBUTE_PAGINATION_CURRENT_PAGE = 'pagination.currentPage';

    protected const string ATTRIBUTE_PAGINATION_MAX_PAGE = 'pagination.maxPage';

    protected const string ATTRIBUTE_PAGINATION_CURRENT_ITEMS_PER_PAGE = 'pagination.currentItemsPerPage';

    /**
     * @uses \Spryker\ApiPlatform\State\Provider\AbstractProvider::buildPaginationTransfer()
     */
    protected const string QUERY_PAGE = 'page';

    protected const string QUERY_LIMIT = 'limit';

    protected const string QUERY_OFFSET = 'offset';

    /**
     * @uses \Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber::ERROR_CODE_VALIDATION
     */
    protected const string RESPONSE_CODE_VALIDATION = '901';

    protected const string LINK_FIRST = 'first';

    protected const string LINK_LAST = 'last';

    protected const string LINK_PREV = 'prev';

    protected const string LINK_NEXT = 'next';

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonApi(Response $response): array
    {
        return (array)json_decode((string)$response->getContent(), true);
    }

    /**
     * The `id` of every resource in a collection document's `data`.
     *
     * @return array<string>
     */
    protected function getResourceIds(Response $response): array
    {
        return array_column($this->getJsonApiMembers($response, static::JSON_API_KEY_DATA), static::JSON_API_KEY_ID);
    }

    /**
     * The `id` of an item document, where `data` is one resource object. The counterpart of
     * {@see static::getResourceIds()}, which reads a collection document.
     */
    protected function getResourceId(Response $response): ?string
    {
        $data = (array)($this->decodeJsonApi($response)[static::JSON_API_KEY_DATA] ?? []);
        $id = $data[static::JSON_API_KEY_ID] ?? null;

        return $id === null ? null : (string)$id;
    }

    /**
     * The `id` of every resource delivered in `included` by an `?include=` request.
     *
     * @return array<string>
     */
    protected function getIncludedIds(Response $response): array
    {
        return array_column($this->getJsonApiMembers($response, static::JSON_API_KEY_INCLUDED), static::JSON_API_KEY_ID);
    }

    /**
     * Every compounded resource of one type, for a write response that carries the resource it
     * changed as `data` and the resources it touched as `included`.
     *
     * @return list<array<string, mixed>>
     */
    protected function findIncludedResources(Response $response, string $resourceType): array
    {
        $includedResources = [];

        foreach ($this->getJsonApiMembers($response, static::JSON_API_KEY_INCLUDED) as $includedResource) {
            if ($includedResource[static::JSON_API_KEY_TYPE] !== $resourceType) {
                continue;
            }

            $includedResources[] = $includedResource;
        }

        return $includedResources;
    }

    /**
     * The first compounded resource of one type. Fails when the document carries none, because a
     * test reading one has already established that the write produced it.
     *
     * @return array<string, mixed>
     */
    protected function findIncludedResource(Response $response, string $resourceType): array
    {
        foreach ($this->getJsonApiMembers($response, static::JSON_API_KEY_INCLUDED) as $includedResource) {
            if ($includedResource[static::JSON_API_KEY_TYPE] === $resourceType) {
                return $includedResource;
            }
        }

        $this->fail(sprintf('No included "%s" resource in: %s', $resourceType, (string)$response->getContent()));
    }

    /**
     * The `id` of the first compounded resource of one type - a cart item's group key, for one.
     */
    protected function readIncludedResourceId(Response $response, string $resourceType): string
    {
        return (string)$this->findIncludedResource($response, $resourceType)[static::JSON_API_KEY_ID];
    }

    /**
     * @return array<string>
     */
    protected function getErrorCodes(Response $response): array
    {
        return array_column($this->getJsonApiMembers($response, static::JSON_API_KEY_ERRORS), static::JSON_API_KEY_CODE);
    }

    /**
     * @return array<string>
     */
    protected function getErrorDetails(Response $response): array
    {
        return array_column($this->getJsonApiMembers($response, static::JSON_API_KEY_ERRORS), static::JSON_API_KEY_DETAIL);
    }

    /**
     * The attributes of an item document, where `data` is one resource object.
     *
     * @return array<string, mixed>
     */
    protected function getResourceAttributes(Response $response): array
    {
        $data = (array)($this->decodeJsonApi($response)[static::JSON_API_KEY_DATA] ?? []);

        return (array)($data[static::JSON_API_KEY_ATTRIBUTES] ?? []);
    }

    /**
     * The attributes of the first resource of a collection document, where `data` is a list.
     * Storefront resources put collection metadata such as pagination on the first member, so this is
     * also how their `pagination` block is reached. Backend resources report it as top-level
     * `meta.pagination` instead.
     *
     * @return array<string, mixed>
     */
    protected function getFirstResourceAttributes(Response $response): array
    {
        $members = $this->getJsonApiMembers($response, static::JSON_API_KEY_DATA);

        return (array)($members[0][static::JSON_API_KEY_ATTRIBUTES] ?? []);
    }

    /**
     * A top-level list member — `data` of a collection document, `included`, or `errors`. Absent on
     * an error response for `data` and on a success response for `errors`, so every extraction above
     * tolerates a missing key instead of failing on the shape of the document it was handed.
     *
     * @return array<array<string, mixed>>
     */
    protected function getJsonApiMembers(Response $response, string $key): array
    {
        $members = $this->decodeJsonApi($response)[$key] ?? [];

        return is_array($members) ? $members : [];
    }

    /**
     * A validation failure answers 422 and names the attribute it rejected. Which of an attribute's
     * constraints fired is not part of the response contract, so the assertion stops at the
     * attribute — the rule under test is declared by
     * {@see \Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation}.
     */
    protected function assertValidationFailedForAttribute(Response $response, string $attribute): void
    {
        $body = (string)$response->getContent();

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $body);
        $this->assertStringContainsString($attribute, $body, sprintf('Expected a validation violation for "%s". Body: %s', $attribute, $body));
    }

    /**
     * Public for the same reason as {@see \SprykerTest\ApiPlatform\Test\AbstractApiTestCase::handleApiRequest()}:
     * {@see \SprykerTest\ApiPlatform\Helper\ApiRequestHelper} forwards it to the actor, and that is
     * the only caller. A test goes through the actor; another helper goes through
     * {@see \SprykerTest\ApiPlatform\Helper\ApiRequestHelperTrait}.
     *
     * @param array<string, mixed> $attributes
     */
    public function encodeJsonApiBody(string $type, array $attributes, ?string $id = null): string
    {
        $data = [
            static::JSON_API_KEY_TYPE => $type,
            static::JSON_API_KEY_ATTRIBUTES => $attributes,
        ];

        if ($id !== null) {
            $data[static::JSON_API_KEY_ID] = $id;
        }

        return (string)json_encode([static::JSON_API_KEY_DATA => $data]);
    }

    /**
     * The document-level `self` link, the one outside `data`, which
     * {@see \Spryker\ApiPlatform\Contract\Envelope\JsonApiEnvelopeVerifier} does not demand.
     *
     * Asserted through `str_ends_with()` rather than `assertStringEndsWith()`, whose suffix
     * parameter is typed `non-empty-string`: an empty expectation would otherwise hold vacuously,
     * and the annotation that would satisfy static analysis is rejected by the doc-block sniff.
     */
    protected function assertJsonApiDocumentSelfLink(Response $response, string $expectedPath): void
    {
        $links = $this->decodeJsonApi($response)[static::JSON_API_KEY_LINKS] ?? [];

        $this->assertIsArray($links, (string)$response->getContent());
        $this->assertNotSame('', $expectedPath, 'An empty expected path would assert nothing.');

        $selfLink = (string)($links[static::JSON_API_KEY_SELF] ?? '');
        $this->assertTrue(
            str_ends_with($selfLink, $expectedPath),
            sprintf('Expected the document self link "%s" to end with "%s".', $selfLink, $expectedPath),
        );
    }

    /**
     * @param string|null $expectedCode The `code` member, or null for the error arms that answer
     *   without one - the framework's own 404 and the request-validator's rejections do.
     */
    protected function assertJsonApiError(Response $response, int $expectedStatus, ?string $expectedCode, string $expectedDetail): void
    {
        $this->assertSame($expectedStatus, $response->getStatusCode(), (string)$response->getContent());

        $payload = $this->decodeJsonApi($response);
        $error = $payload[static::JSON_API_KEY_ERRORS][0] ?? [];

        $this->assertSame($expectedCode, $error[static::JSON_API_KEY_CODE] ?? null);
        $this->assertSame($expectedStatus, $error[static::JSON_API_KEY_STATUS] ?? null);
        $this->assertSame($expectedDetail, $error[static::JSON_API_KEY_DETAIL] ?? null);
    }

    /**
     * Reports the body on failure, which is the difference that matters when a test fails: the
     * status alone does not say which error the resource answered with.
     */
    protected function assertRespondsWithStatus(Response $response, int $expectedStatus): void
    {
        $this->assertSame($expectedStatus, $response->getStatusCode(), (string)$response->getContent());
    }

    /**
     * The looser counterpart of {@see static::assertJsonApiError()}: it accepts the code anywhere in
     * `errors` and says nothing about the detail text. Use it when a rejection legitimately carries
     * several errors, or when the detail is a glossary key the test should not pin down. Prefer
     * `assertJsonApiError()` whenever the response is a single error whose text is part of the
     * contract.
     */
    protected function assertRespondsWithErrorCode(Response $response, int $expectedStatus, string $expectedCode): void
    {
        $body = (string)$response->getContent();

        $this->assertSame($expectedStatus, $response->getStatusCode(), $body);
        $this->assertContains($expectedCode, $this->getErrorCodes($response), $body);
    }

    /**
     * @uses \Spryker\ApiPlatform\ResponseTransform\PaginationLinksTransform::applyTo()
     *
     * @return array<string, mixed>
     */
    protected function getMetaPagination(Response $response): array
    {
        $meta = (array)($this->decodeJsonApi($response)[static::JSON_API_KEY_META] ?? []);

        $this->assertIsArray($meta[static::META_PAGINATION] ?? null, 'A backend collection must carry top-level meta.pagination.');
        $this->assertArrayNotHasKey(static::META_TOTAL_ITEMS, $meta, 'The page-count-only totalItems must not be emitted next to meta.pagination.');

        return (array)$meta[static::META_PAGINATION];
    }

    /**
     * @return array<string, string>
     */
    protected function getLinks(Response $response): array
    {
        return (array)($this->decodeJsonApi($response)[static::JSON_API_KEY_LINKS] ?? []);
    }

    protected function assertNoPaginationInsideMembers(Response $response): void
    {
        foreach ($this->getJsonApiMembers($response, static::JSON_API_KEY_DATA) as $member) {
            $this->assertArrayNotHasKey(
                static::META_PAGINATION,
                (array)($member[static::JSON_API_KEY_ATTRIBUTES] ?? []),
                'Collection members must not carry a pagination attribute.',
            );
        }
    }

    /**
     * Compares only the attributes named in the expectation and ignores the rest, so a resource
     * gaining an unrelated attribute does not break every test that reads it.
     *
     * @param array<string, mixed> $expectedAttributes
     * @param array<string, mixed> $actualAttributes
     */
    protected function assertAttributesMatch(array $expectedAttributes, array $actualAttributes, string $message = ''): void
    {
        $comparable = array_intersect_key($actualAttributes, $expectedAttributes);

        ksort($expectedAttributes);
        ksort($comparable);

        $this->assertSame($expectedAttributes, $comparable, $message);
    }

    /**
     * Asserts the response carries each named attribute with the expected value, and records the
     * path so {@see \Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeRecorder} can hold a
     * `#[CoversApiRequiredResponseAttributes]` test to the schema-derived truth.
     *
     * @param array<string, mixed> $expectedByPath e.g. `['customers[0].firstName' => $customerTransfer->getFirstName()]`
     */
    protected function assertResponseAttributes(Response $response, array $expectedByPath): void
    {
        $document = $this->decodeJsonApi($response);
        foreach ($expectedByPath as $path => $expected) {
            $actual = $this->readResponseAttribute($document, $path);
            $this->assertSame($expected, $actual, sprintf(
                '%s: expected %s, got %s',
                $path,
                json_encode($expected, JSON_THROW_ON_ERROR),
                json_encode($actual, JSON_THROW_ON_ERROR),
            ));
            $this->responseAttributeRecorder?->record($path);
        }
    }

    /**
     * Only for values the server mints and the test cannot know (uuid, createdAt), and for a nested
     * object the truth carries as a presence path. Still recorded, so it counts towards the same
     * coverage as {@see static::assertResponseAttributes()} — prefer that one whenever the test
     * created the fixture the value comes from.
     *
     * @param array<string> $paths
     */
    protected function assertResponseAttributesPresent(Response $response, array $paths): void
    {
        $document = $this->decodeJsonApi($response);
        foreach ($paths as $path) {
            $this->assertNotNull($this->readResponseAttribute($document, $path), sprintf('%s is null', $path));
            $this->responseAttributeRecorder?->record($path);
        }
    }

    /**
     * The {@see static::assertResponseAttributes()} of a write that answers its parent resource:
     * the operation's own attributes travel in the compound document's `included` bag, so they are
     * read from there and recorded against the same coverage.
     *
     * @param array<string, mixed> $expectedByPath
     */
    protected function assertIncludedResourceAttributes(Response $response, string $resourceType, array $expectedByPath): void
    {
        $document = $this->decodeJsonApi($response);
        foreach ($expectedByPath as $path => $expected) {
            $actual = $this->readIncludedResponseAttribute($document, $resourceType, $path);
            $this->assertSame($expected, $actual, sprintf(
                '%s %s: expected %s, got %s',
                $resourceType,
                $path,
                json_encode($expected, JSON_THROW_ON_ERROR),
                json_encode($actual, JSON_THROW_ON_ERROR),
            ));
            $this->responseAttributeRecorder?->record($path);
        }
    }

    /**
     * The {@see static::assertResponseAttributesPresent()} twin of
     * {@see static::assertIncludedResourceAttributes()}, under the same rule: only for a value the
     * server mints and for a nested object the truth carries as a presence path.
     *
     * @param array<string> $paths
     */
    protected function assertIncludedResourceAttributesPresent(Response $response, string $resourceType, array $paths): void
    {
        $document = $this->decodeJsonApi($response);
        foreach ($paths as $path) {
            $this->assertNotNull(
                $this->readIncludedResponseAttribute($document, $resourceType, $path),
                sprintf('%s %s is null', $resourceType, $path),
            );
            $this->responseAttributeRecorder?->record($path);
        }
    }

    /**
     * Resolves one response attribute path against a decoded document: a collection document
     * selects the member the path names, an item document has only one resource object and ignores
     * the selector, and the remaining segments walk that resource's `attributes`.
     *
     * A wildcard path is rejected up front and by name: it is the form the coverage truth set
     * speaks, so copying one into an assertion is the obvious mistake to make.
     *
     * @param array<string, mixed> $document
     */
    protected function readResponseAttribute(array $document, string $path): mixed
    {
        $this->assertTrue(ResponseAttributePath::isValid($path), sprintf('"%s" is not a valid response attribute path', $path));
        $this->assertFalse(ResponseAttributePath::isWildcard($path), sprintf(
            '"%s" is a truth path; assert a concrete member, e.g. %s',
            $path,
            ResponseAttributePath::concreteMemberPath($path),
        ));
        $data = $document[static::JSON_API_KEY_DATA] ?? null;
        $this->assertIsArray($data, 'response has no "data" member');
        $member = array_is_list($data) ? ($data[ResponseAttributePath::memberIndex($path)] ?? null) : $data;

        return $this->walkResourceAttributes($member, $path);
    }

    /**
     * The same resolution against a resource in the compound document's `included` bag rather than
     * against `data`. A write that answers its parent - `POST /carts/{cartId}/items` answers the
     * cart, with the item included - carries the operation's own response attributes there, so this
     * is how those are read back.
     *
     * @param array<string, mixed> $document
     */
    protected function readIncludedResponseAttribute(array $document, string $resourceType, string $path): mixed
    {
        $this->assertTrue(ResponseAttributePath::isValid($path), sprintf('"%s" is not a valid response attribute path', $path));
        $this->assertFalse(ResponseAttributePath::isWildcard($path), sprintf(
            '"%s" is a truth path; assert a concrete member, e.g. %s',
            $path,
            ResponseAttributePath::concreteMemberPath($path),
        ));
        $members = [];
        foreach ((array)($document[static::JSON_API_KEY_INCLUDED] ?? []) as $includedResource) {
            if (is_array($includedResource) && ($includedResource[static::JSON_API_KEY_TYPE] ?? null) === $resourceType) {
                $members[] = $includedResource;
            }
        }

        $this->assertNotSame([], $members, sprintf('%s: no included "%s" resource in the response', $path, $resourceType));

        return $this->walkResourceAttributes($members[ResponseAttributePath::memberIndex($path)] ?? null, $path);
    }

    /**
     * @param array<string, mixed>|null $member
     */
    protected function walkResourceAttributes(?array $member, string $path): mixed
    {
        $this->assertIsArray($member, sprintf('%s: no resource object at member %d', $path, ResponseAttributePath::memberIndex($path)));
        $cursor = $member[static::JSON_API_KEY_ATTRIBUTES] ?? null;
        foreach (ResponseAttributePath::segments($path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                $this->fail(sprintf('%s: segment "%s" is missing from the response', $path, (string)$segment));
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
