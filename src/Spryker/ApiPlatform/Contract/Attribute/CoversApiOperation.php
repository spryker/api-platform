<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Attribute;

use Attribute;

/**
 * Declares the API Platform operation a test method asserts on, by HTTP verb and OpenAPI
 * uriTemplate (e.g. `/wishlists/{wishlistUuid}/wishlist-items`) — the one operation under test, not
 * the arrange requests. The optional status names a declared error response; without it the
 * declaration covers the operation's success response. The optional code narrows an error response
 * to one of the codes its status declares under `openapiContext.responses.<status>.codes`, and then
 * covers that status as well. The optional scenario says which situation the error response stands
 * for, e.g. {@see Scenario::FOREIGN_OWNER} for an authenticated caller addressing someone else's
 * resource.
 *
 * The runtime verifier fails the test if a declared operation is never dispatched; asserting the
 * response status stays the test body's job.
 */
#[\Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class CoversApiOperation
{
    public function __construct(
        public string $verb,
        public string $uriTemplate,
        public ?int $status = null,
        public ?string $code = null,
        public ?Scenario $scenario = null,
    ) {
    }
}
