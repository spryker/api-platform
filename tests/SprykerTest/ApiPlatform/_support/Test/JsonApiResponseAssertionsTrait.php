<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

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
     * attribute.
     */
    protected function assertValidationFailedForAttribute(Response $response, string $attribute): void
    {
        $body = (string)$response->getContent();

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $body);
        $this->assertStringContainsString($attribute, $body, sprintf('Expected a validation violation for "%s". Body: %s', $attribute, $body));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function encodeJsonApiBody(string $type, array $attributes, ?string $id = null): string
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
     * The document-level `self` link, the one outside `data`.
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

    protected function assertJsonApiError(Response $response, int $expectedStatus, string $expectedCode, string $expectedDetail): void
    {
        $this->assertSame($expectedStatus, $response->getStatusCode());

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
}
