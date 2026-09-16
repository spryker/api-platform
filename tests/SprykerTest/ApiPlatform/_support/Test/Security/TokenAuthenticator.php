<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TokenAuthenticator extends AbstractAuthenticator
{
    protected CustomerProvider $customerProvider;

    public function __construct(CustomerProvider $customerProvider)
    {
        $this->customerProvider = $customerProvider;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authorizationHeader = $request->headers->get('Authorization');

        if ($authorizationHeader === null) {
            throw new AuthenticationException('No authorization header found');
        }

        $token = $this->extractToken($authorizationHeader);

        return new SelfValidatingPassport(
            new UserBadge($token, function (string $userIdentifier) {
                return $this->customerProvider->loadUserByIdentifier($userIdentifier);
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response('Authentication failed', Response::HTTP_UNAUTHORIZED);
    }

    protected function extractToken(string $authorizationHeader): string
    {
        if (!str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new AuthenticationException('Invalid authorization header format');
        }

        return substr($authorizationHeader, 7);
    }
}
