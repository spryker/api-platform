<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Closure;
use Generated\Shared\Transfer\OauthAccessTokenValidationRequestTransfer;
use Generated\Shared\Transfer\OauthAccessTokenValidationResponseTransfer;
use Generated\Shared\Transfer\OauthRequestTransfer;
use Generated\Shared\Transfer\OauthResponseTransfer;
use Generated\Shared\Transfer\RevokeRefreshTokenResponseTransfer;
use Spryker\Client\Oauth\OauthClientInterface;

/**
 * One stable OAuth-client instance the test kernel binds everywhere on the first boot, whose
 * behaviour is swapped per test method by replacing its inner {@see $delegate} — the authenticator
 * and identity subscribers capture the injected client once, so rebinding the service cannot reach
 * them. The delegate is always set, defaulting to an "invalid token" stub.
 *
 * Only token *validation* is swapped; token *issuance* goes to the real OAuth client, so a test
 * proves the grant rather than a stub's return value.
 */
class SwappableOauthClient implements OauthClientInterface
{
    protected ?OauthClientInterface $tokenIssuingClient = null;

    /**
     * @param \Closure(): \Spryker\Client\Oauth\OauthClientInterface|null $tokenIssuingClientProvider Resolved on first
     *     use, so a suite that never issues a token pays nothing for it.
     */
    public function __construct(
        protected OauthClientInterface $delegate,
        protected ?Closure $tokenIssuingClientProvider = null,
    ) {
    }

    public function setDelegate(OauthClientInterface $delegate): void
    {
        $this->delegate = $delegate;
    }

    public function processAccessTokenRequest(OauthRequestTransfer $oauthRequestTransfer): OauthResponseTransfer
    {
        return $this->resolveTokenIssuingClient()->processAccessTokenRequest($oauthRequestTransfer);
    }

    public function validateAccessToken(
        OauthAccessTokenValidationRequestTransfer $authAccessTokenValidationRequestTransfer,
    ): OauthAccessTokenValidationResponseTransfer {
        return $this->delegate->validateAccessToken($authAccessTokenValidationRequestTransfer);
    }

    public function validateOauthAccessToken(
        OauthAccessTokenValidationRequestTransfer $authAccessTokenValidationRequestTransfer,
    ): OauthAccessTokenValidationResponseTransfer {
        return $this->delegate->validateOauthAccessToken($authAccessTokenValidationRequestTransfer);
    }

    public function revokeRefreshToken(string $refreshTokenIdentifier, string $customerReference): RevokeRefreshTokenResponseTransfer
    {
        return $this->resolveTokenIssuingClient()->revokeRefreshToken($refreshTokenIdentifier, $customerReference);
    }

    public function revokeAllRefreshTokens(string $customerReference): RevokeRefreshTokenResponseTransfer
    {
        return $this->resolveTokenIssuingClient()->revokeAllRefreshTokens($customerReference);
    }

    protected function resolveTokenIssuingClient(): OauthClientInterface
    {
        if ($this->tokenIssuingClientProvider === null) {
            return $this->delegate;
        }

        return $this->tokenIssuingClient ??= ($this->tokenIssuingClientProvider)();
    }
}
