<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\ApiPlatform\Test;

/**
 * Base test case for Backend API functional tests.
 *
 * Extends AbstractApiTestCase with Backend API specific configuration:
 * - Resource paths pointing to generated Backend API resources
 * - Default client options including base URL and headers
 *
 * All Backend API tests should extend this class.
 */
abstract class BackendApiTestCase extends AbstractApiTestCase
{
    protected const string DEFAULT_BASE_URL = 'http://glue-backend.eu.spryker.local';

    protected const string DEFAULT_ACCEPT_HEADER = self::MEDIA_TYPE_JSON_LD;

    protected const string DEFAULT_CONTENT_TYPE_HEADER = self::MEDIA_TYPE_JSON_LD;

    protected const string API_TYPE = 'Backend';
}
