<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Represents an authenticated API user mapped from OAuth JWT claims.
 */
class ApiUser implements UserInterface
{
    protected const string ROLE_USER = 'ROLE_USER';

    // Claim keys used in the JSON-encoded user identifier shared between OauthAuthenticator and ApiUserProvider
    public const string CLAIM_USER_ID = 'user_id';

    public const string CLAIM_OAUTH_CLIENT_ID = 'oauth_client_id';

    public const string CLAIM_OAUTH_ACCESS_TOKEN_ID = 'oauth_access_token_id';

    public const string CLAIM_OAUTH_SCOPES = 'oauth_scopes';

    /**
     * @param array<string> $roles
     */
    public function __construct(
        protected readonly string $userIdentifier,
        protected readonly array $roles = [],
        protected readonly ?string $oauthClientId = null,
        protected readonly ?string $oauthAccessTokenId = null,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // Symfony requires at least ROLE_USER for authenticated users
        if (!in_array(static::ROLE_USER, $roles, true)) {
            $roles[] = static::ROLE_USER;
        }

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getOauthClientId(): ?string
    {
        return $this->oauthClientId;
    }

    public function getOauthAccessTokenId(): ?string
    {
        return $this->oauthAccessTokenId;
    }
}
