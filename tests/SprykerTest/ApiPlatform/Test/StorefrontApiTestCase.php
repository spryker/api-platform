<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\ApiPlatform\Test;

/**
 * Base test case for Storefront API functional tests.
 *
 * Extends AbstractApiTestCase with Storefront API specific configuration:
 * - Resource paths pointing to generated Storefront API resources
 * - Default client options including base URL and headers
 *
 * All Storefront API tests should extend this class.
 */
abstract class StorefrontApiTestCase extends AbstractApiTestCase
{
    protected const string DEFAULT_BASE_URL = 'http://glue-storefront.eu.spryker.local';

    protected const string DEFAULT_ACCEPT_HEADER = self::MEDIA_TYPE_JSON_LD;

    protected const string DEFAULT_CONTENT_TYPE_HEADER = self::MEDIA_TYPE_JSON_LD;

    protected const string API_TYPE = 'Storefront';
}
