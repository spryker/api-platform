<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\ApiPlatform\Test;

/**
 * Base test case for Backend API functional tests.
 *
 * Extends {@see AbstractApiTestCase} with the Backend resource paths, base URL and JSON:API
 * headers. See `src/Spryker/ApiPlatform/tests/README.md` for which lane a test belongs in.
 *
 * JSON:API rather than JSON-LD, because that is what the Backend application actually serves:
 * `config/GlueBackend/packages/api_platform.php` registers `jsonapi` first and registers
 * `errorFormats` and `patchFormats` as jsonapi **only**. Defaulting to JSON-LD produced JSON-LD
 * success bodies beside JSON:API error bodies, which no real Back Office client would send or see.
 */
abstract class BackendApiTestCase extends AbstractApiTestCase
{
    protected const string DEFAULT_BASE_URL = 'http://glue-backend.eu.spryker.local';

    protected const string DEFAULT_ACCEPT_HEADER = self::MEDIA_TYPE_JSON_API;

    protected const string DEFAULT_CONTENT_TYPE_HEADER = self::MEDIA_TYPE_JSON_API;

    protected const string API_TYPE = 'Backend';
}
