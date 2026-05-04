<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Generated\Shared\Transfer\OauthAccessTokenValidationRequestTransfer;
use JsonException;
use Spryker\ApiPlatform\Security\Resolver\IdentityResolver;
use Spryker\Client\Oauth\OauthClientInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the authenticated identity (raw OAuth claims) after the Symfony firewall
 * and stores the result on the request as `_oauth_identity_claims`. Domain-specific
 * subscribers in consumer modules ({@see CustomersRestApi}, {@see CompanyUsersRestApi})
 * read these raw claims and build their domain transfers ({@see CustomerTransfer},
 * {@see CompanyUserTransfer}) at lower priorities.
 *
 * On stateless lazy firewalls the security TokenStorage is empty during `kernel.request`
 * (authenticators only run when `is_granted(...)` is evaluated, later in the API Platform
 * security expression phase — too late for downstream Providers). To make identity available
 * during the same request lifecycle, this subscriber additionally validates the bearer
 * token via {@see OauthClientInterface} and reads the JWT claims directly.
 */
class IdentityRequestSubscriber implements EventSubscriberInterface
{
    public const string ATTRIBUTE_OAUTH_IDENTITY_CLAIMS = '_oauth_identity_claims';

    protected const int PRIORITY_AFTER_FIREWALL = 7;

    protected const string AUTHORIZATION_HEADER = 'Authorization';

    protected const string BEARER_PREFIX = 'Bearer ';

    protected const string TOKEN_TYPE_BEARER = 'Bearer';

    public function __construct(
        protected IdentityResolver $identityResolver,
        protected ?OauthClientInterface $oauthClient = null,
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', static::PRIORITY_AFTER_FIREWALL],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Try the security TokenStorage first (works for non-lazy or already-auth'd requests).
        $userData = $this->identityResolver->resolve();

        // Fallback: on stateless lazy firewalls, TokenStorage is empty during kernel.request
        // because authenticators only run when `is_granted(...)` is evaluated (later, during
        // API Platform security expression processing — too late for downstream providers).
        // Validate the bearer token directly via OauthClient to populate identity now.
        if ($userData === null && $this->oauthClient !== null) {
            $userData = $this->resolveUserDataFromBearerToken($request);
        }

        if ($userData === null) {
            return;
        }

        $request->attributes->set(static::ATTRIBUTE_OAUTH_IDENTITY_CLAIMS, $userData);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveUserDataFromBearerToken(Request $request): ?array
    {
        if ($this->oauthClient === null) {
            return null;
        }

        $header = $request->headers->get(static::AUTHORIZATION_HEADER);

        if ($header === null || !str_starts_with($header, static::BEARER_PREFIX)) {
            return null;
        }

        $accessToken = substr($header, strlen(static::BEARER_PREFIX));

        $validationRequest = (new OauthAccessTokenValidationRequestTransfer())
            ->setAccessToken($accessToken)
            ->setType(static::TOKEN_TYPE_BEARER);

        $validationResponse = $this->oauthClient->validateOauthAccessToken($validationRequest);

        if (!$validationResponse->getIsValid()) {
            return null;
        }

        $oauthUserId = $validationResponse->getOauthUserId();

        if ($oauthUserId === null || $oauthUserId === '') {
            return null;
        }

        try {
            $userData = json_decode($oauthUserId, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($userData) ? $userData : null;
    }
}
