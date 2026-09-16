<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Request;

/**
 * Single source of truth for the `Request::$attributes` keys read and written across the API
 * applications. Reference these constants instead of repeating the literals — the keys are a
 * contract between components that never call each other, so a typo in one of them fails
 * silently as a missing attribute rather than as an error.
 *
 * The `_api_*` keys are owned by API Platform and mirrored here for discoverability; the rest
 * are Spryker's own.
 */
class RequestAttribute
{
    /**
     * The resolved locale name (e.g. `de_DE`). Also what Symfony itself reads for `$request->getLocale()`.
     */
    public const string LOCALE = '_locale';

    /**
     * @see \Generated\Shared\Transfer\LocaleTransfer
     */
    public const string LOCALE_TRANSFER = 'LocaleTransfer';

    /**
     * @see \Generated\Shared\Transfer\StoreTransfer
     */
    public const string STORE_TRANSFER = 'StoreTransfer';

    /**
     * @see \Generated\Shared\Transfer\CustomerTransfer
     */
    public const string CUSTOMER_TRANSFER = 'CustomerTransfer';

    /**
     * Set by API Platform once a request is matched to a resource. Its presence is the canonical
     * test for "this request is handled by API Platform".
     */
    public const string API_RESOURCE_CLASS = '_api_resource_class';

    /**
     * @see \ApiPlatform\Metadata\Operation
     */
    public const string API_OPERATION = '_api_operation';

    public const string API_OPERATION_NAME = '_api_operation_name';

    public const string API_URI_VARIABLES = '_api_uri_variables';

    public const string API_INCLUDED = '_api_included';

    /**
     * Marks a request forwarded by the GlueApplication proxy to the API Platform kernel.
     */
    public const string API_PLATFORM_REQUEST = 'api-platform-request';

    /**
     * Relationships resolved for the current request, keyed by relationship name.
     */
    public const string RESOLVED_RELATIONSHIPS = '_spryker_resolved_relationships';

    /**
     * Relationships resolved per collection item, keyed by item identifier.
     */
    public const string PER_ITEM_RELATIONSHIPS = '_spryker_per_item_relationships';

    /**
     * @see \Generated\Shared\Transfer\PaginationTransfer
     */
    public const string PAGINATION = '_spryker_api_platform_pagination';

    public const string CUSTOMER_ACCESS_DENIED = '_customer_access_denied';
}
