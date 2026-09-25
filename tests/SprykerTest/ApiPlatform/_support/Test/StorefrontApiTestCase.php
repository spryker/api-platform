<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\ApiPlatform\Test;

/**
 * Base test case for Storefront API functional tests.
 *
 * Extends {@see AbstractApiTestCase} with the Storefront resource paths, base URL and JSON:API
 * headers. See `src/Spryker/ApiPlatform/tests/README.md` for which lane a test belongs in.
 */
abstract class StorefrontApiTestCase extends AbstractApiTestCase
{
    protected const string DEFAULT_BASE_URL = 'http://glue-storefront.eu.spryker.local';

    protected const string DEFAULT_ACCEPT_HEADER = self::MEDIA_TYPE_JSON_API;

    protected const string DEFAULT_CONTENT_TYPE_HEADER = self::MEDIA_TYPE_JSON_API;

    protected const string API_TYPE = 'Storefront';
}
