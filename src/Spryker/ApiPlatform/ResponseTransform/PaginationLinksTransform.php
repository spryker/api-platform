<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\ResponseTransform;

use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\Request;

/**
 * Adds JSON:API pagination links (first, last, prev, next) to responses
 * that contain pagination metadata in the resource attributes.
 *
 * This supports resources like catalog-search that handle pagination internally
 * via the search client rather than through API Platform's built-in pagination.
 */
class PaginationLinksTransform
{
    public const string REQUEST_ATTRIBUTE_PAGINATION = RequestAttribute::PAGINATION;

    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    protected const string PAGINATION_PARAM_OFFSET = 'offset';

    protected const string PAGINATION_PARAM_LIMIT = 'limit';

    protected const string PAGINATION_PARAM_PAGE = 'page';

    protected const string DOCUMENT_SECTION_DATA = 'data';

    protected const string DOCUMENT_SECTION_INCLUDED = 'included';

    protected const string ATTRIBUTE_PAGINATION = 'pagination';

    protected const string DOCUMENT_SECTION_META = 'meta';

    protected const string DOCUMENT_SECTION_LINKS = 'links';

    protected const string META_PAGINATION = 'pagination';

    protected const string META_TOTAL_ITEMS = 'totalItems';

    protected const string PAGINATION_KEY_CURRENT_PAGE = 'currentPage';

    protected const string PAGINATION_KEY_MAX_PAGE = 'maxPage';

    /**
     * Adds pagination links to an already-decoded JSON:API document, if it carries pagination
     * metadata on the first resource.
     *
     * @param array<string, mixed> $data
     *
     * @return bool Whether the document was modified.
     */
    public function applyTo(array &$data, Request $request): bool
    {
        $modified = $this->stripNullPaginationAttribute($data);

        $requestPagination = $request->attributes->get(static::REQUEST_ATTRIBUTE_PAGINATION);
        if (is_array($requestPagination) && $this->isCollectionDocument($data)) {
            unset($data[static::DOCUMENT_SECTION_META][static::META_TOTAL_ITEMS]);
            $data[static::DOCUMENT_SECTION_META][static::META_PAGINATION] = $requestPagination;
            $this->addPaginationLinks($data, $request, $requestPagination);

            return true;
        }

        $pagination = $data[static::DOCUMENT_SECTION_DATA][0]['attributes'][static::ATTRIBUTE_PAGINATION] ?? null;

        if (!is_array($pagination)) {
            return $modified;
        }

        return $this->addPaginationLinks($data, $request, $pagination) || $modified;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function isCollectionDocument(array $data): bool
    {
        return isset($data[static::DOCUMENT_SECTION_DATA])
            && is_array($data[static::DOCUMENT_SECTION_DATA])
            && array_is_list($data[static::DOCUMENT_SECTION_DATA]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $pagination
     */
    protected function addPaginationLinks(array &$data, Request $request, array $pagination): bool
    {
        if (!isset($pagination[static::PAGINATION_KEY_CURRENT_PAGE], $pagination[static::PAGINATION_KEY_MAX_PAGE])) {
            return false;
        }

        $currentPage = (int)$pagination[static::PAGINATION_KEY_CURRENT_PAGE];
        $maxPage = (int)$pagination[static::PAGINATION_KEY_MAX_PAGE];

        // Skip only when there is no result page at all. For a single-page result (maxPage == 1)
        // JSON:API still requires `first` and `last` links (they coincide with `self`).
        if ($maxPage < 1) {
            return false;
        }

        $itemsPerPage = $this->resolveItemsPerPage($request, $pagination);

        $data[static::DOCUMENT_SECTION_LINKS]['first'] = $this->buildPaginationLink($request, 0, $itemsPerPage);
        $data[static::DOCUMENT_SECTION_LINKS]['last'] = $this->buildPaginationLink($request, ($maxPage - 1) * $itemsPerPage, $itemsPerPage);

        if ($currentPage > 1) {
            $data[static::DOCUMENT_SECTION_LINKS]['prev'] = $this->buildPaginationLink($request, ($currentPage - 2) * $itemsPerPage, $itemsPerPage);
        }

        if ($currentPage < $maxPage) {
            $data[static::DOCUMENT_SECTION_LINKS]['next'] = $this->buildPaginationLink($request, $currentPage * $itemsPerPage, $itemsPerPage);
        }

        return true;
    }

    /**
     * Removes a null-valued `pagination` attribute from every resource object in the document
     * (the primary `data` — single object or collection — and each `included` resource). A
     * populated `pagination` (real collection metadata) is left untouched. Returns whether any
     * key was removed.
     *
     * @param array<string, mixed> $data
     */
    protected function stripNullPaginationAttribute(array &$data): bool
    {
        $modified = false;

        foreach ([static::DOCUMENT_SECTION_DATA, static::DOCUMENT_SECTION_INCLUDED] as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                continue;
            }

            // `data` may be a single resource object (item endpoint) or a collection.
            $isCollection = array_is_list($data[$section]);
            $resources = $isCollection ? $data[$section] : [$data[$section]];

            foreach ($resources as $index => $resource) {
                if (!is_array($resource) || !is_array($resource['attributes'] ?? null)) {
                    continue;
                }
                if (!array_key_exists(static::ATTRIBUTE_PAGINATION, $resource['attributes']) || $resource['attributes'][static::ATTRIBUTE_PAGINATION] !== null) {
                    continue;
                }

                unset($resource['attributes'][static::ATTRIBUTE_PAGINATION]);
                $modified = true;

                if ($isCollection) {
                    $data[$section][$index] = $resource;
                } else {
                    $data[$section] = $resource;
                }
            }
        }

        return $modified;
    }

    protected function resolveItemsPerPage(Request $request, mixed $pagination): int
    {
        $pageParam = $request->query->all()[static::PAGINATION_PARAM_PAGE] ?? null;

        if (is_array($pageParam) && isset($pageParam[static::PAGINATION_PARAM_LIMIT])) {
            return (int)$pageParam[static::PAGINATION_PARAM_LIMIT];
        }

        return (int)($pagination['currentItemsPerPage'] ?? $pagination['config']['defaultItemsPerPage'] ?? 12);
    }

    protected function buildPaginationLink(Request $request, int $offset, int $limit): string
    {
        $baseUrl = $request->getSchemeAndHttpHost() . $request->getPathInfo();
        $queryParams = $request->query->all();

        // Replace the page parameter with offset/limit format
        $queryParams[static::PAGINATION_PARAM_PAGE] = [
            static::PAGINATION_PARAM_LIMIT => $limit,
            static::PAGINATION_PARAM_OFFSET => $offset,
        ];

        return $baseUrl . '?' . urldecode(http_build_query($queryParams, '', '&'));
    }
}
