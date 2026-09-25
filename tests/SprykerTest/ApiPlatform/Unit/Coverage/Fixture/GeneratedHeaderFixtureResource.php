<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

/**
 * Reproduces the `Source schema files:` header a generated resource class carries, so
 * {@see \Spryker\ApiPlatform\Contract\Coverage\SchemaSourceResolver} is testable without generating.
 * The resolver scans the whole file for the line format, so the block does not have to be the file's
 * first docblock — which lets this fixture keep the standard Spryker header above it.
 *
 * Source schema files:
 * - /application/root/src/Spryker/StoresApi/resources/api/storefront/stores.resource.yml
 * - /application/root/src/Spryker/Store/resources/api/storefront/stores.resource.yml
 *
 * Validation schema files:
 * - /application/root/src/Spryker/StoresApi/resources/api/storefront/stores.validation.yml
 */
class GeneratedHeaderFixtureResource
{
}
