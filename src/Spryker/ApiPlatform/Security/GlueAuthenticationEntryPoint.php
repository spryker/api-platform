<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Returns a Glue-compatible 403 response when an unauthenticated user
 * accesses a resource that requires authentication.
 */
class GlueAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    protected const string ERROR_CODE_MISSING_ACCESS_TOKEN = '002';

    protected const string ERROR_DETAIL_MISSING_ACCESS_TOKEN = 'Missing access token.';

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            [
                'errors' => [
                    [
                        'code' => static::ERROR_CODE_MISSING_ACCESS_TOKEN,
                        'status' => Response::HTTP_FORBIDDEN,
                        'detail' => static::ERROR_DETAIL_MISSING_ACCESS_TOKEN,
                    ],
                ],
            ],
            Response::HTTP_FORBIDDEN,
            ['Content-Type' => static::CONTENT_TYPE_JSON_API],
        );
    }
}
