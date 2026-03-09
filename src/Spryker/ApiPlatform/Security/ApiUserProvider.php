<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Builds ApiUser instances from validated OAuth JWT claims.
 *
 * The user identifier is expected to be a JSON-encoded string containing
 * the claims extracted from the validated JWT by the OauthAuthenticator.
 *
 * @implements \Symfony\Component\Security\Core\User\UserProviderInterface<\Spryker\ApiPlatform\Security\ApiUser>
 */
class ApiUserProvider implements UserProviderInterface
{
    protected const string ROLE_FORMAT = 'ROLE_%s';

    protected const string ERROR_USER_IDENTIFIER_COULD_NOT_BE_DECODED = 'User identifier could not be decoded';

    protected const string ERROR_UNSUPPORTED_USER_CLASS = 'Instances of "%s" are not supported';

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $claims = json_decode($identifier, true);

        if (!is_array($claims) || !isset($claims[ApiUser::CLAIM_USER_ID])) {
            throw new UserNotFoundException(static::ERROR_USER_IDENTIFIER_COULD_NOT_BE_DECODED);
        }

        $roles = $this->mapScopesToRoles($claims[ApiUser::CLAIM_OAUTH_SCOPES] ?? []);

        return new ApiUser(
            $claims[ApiUser::CLAIM_USER_ID],
            $roles,
            $claims[ApiUser::CLAIM_OAUTH_CLIENT_ID] ?? null,
            $claims[ApiUser::CLAIM_OAUTH_ACCESS_TOKEN_ID] ?? null,
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ApiUser) {
            throw new UnsupportedUserException(sprintf(static::ERROR_UNSUPPORTED_USER_CLASS, get_class($user)));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === ApiUser::class || is_subclass_of($class, ApiUser::class);
    }

    /**
     * @param array<string> $scopes
     *
     * @return array<string>
     */
    protected function mapScopesToRoles(array $scopes): array
    {
        $roles = [];

        foreach ($scopes as $scope) {
            $roles[] = sprintf(static::ROLE_FORMAT, strtoupper($scope));
        }

        return $roles;
    }
}
