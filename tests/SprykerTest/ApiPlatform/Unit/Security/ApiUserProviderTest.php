<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Security;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Security\ApiUser;
use Spryker\ApiPlatform\Security\ApiUserProvider;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Security
 * @group ApiUserProviderTest
 * Add your own group annotations below this line
 */
class ApiUserProviderTest extends Unit
{
    protected const string USER_ID = 'e6a1d1d0-1a1a-4a1a-8a1a-1a1a1a1a1a1a';

    protected const string OAUTH_CLIENT_ID = 'backend-api';

    protected const string OAUTH_ACCESS_TOKEN_ID = 'access-token-id';

    protected ApiUnitTester $tester;

    public function testGivenBackOfficeUserScopeWhenLoadingUserThenMapsToBackOfficeRole(): void
    {
        // Arrange
        $identifier = $this->createUserIdentifier(['user', 'back-office-user']);

        // Act
        $apiUser = (new ApiUserProvider())->loadUserByIdentifier($identifier);

        // Assert
        $this->assertContains('ROLE_BACK_OFFICE_USER', $apiUser->getRoles());
        $this->assertContains('ROLE_USER', $apiUser->getRoles());
        $this->assertNotContains('ROLE_MERCHANT_USER', $apiUser->getRoles());
    }

    public function testGivenDashSeparatedMerchantUserScopeWhenLoadingUserThenMapsToUnderscoredRole(): void
    {
        // Arrange
        $identifier = $this->createUserIdentifier(['user', 'merchant-user']);

        // Act
        $apiUser = (new ApiUserProvider())->loadUserByIdentifier($identifier);

        // Assert
        $this->assertContains('ROLE_MERCHANT_USER', $apiUser->getRoles());
        $this->assertNotContains('ROLE_MERCHANT-USER', $apiUser->getRoles());
    }

    public function testGivenMerchantUserScopeWhenLoadingUserThenBackOfficeRoleIsNotGranted(): void
    {
        // Arrange
        $identifier = $this->createUserIdentifier(['user', 'merchant-user']);

        // Act
        $apiUser = (new ApiUserProvider())->loadUserByIdentifier($identifier);

        // Assert
        $this->assertNotContains('ROLE_BACK_OFFICE_USER', $apiUser->getRoles());
        $this->assertContains('ROLE_USER', $apiUser->getRoles());
    }

    public function testGivenGenericUserScopeWhenLoadingUserThenOnlyTheDefaultRoleIsGranted(): void
    {
        // Arrange
        $identifier = $this->createUserIdentifier(['user']);

        // Act
        $apiUser = (new ApiUserProvider())->loadUserByIdentifier($identifier);

        // Assert
        $this->assertSame(['ROLE_USER'], $apiUser->getRoles());
    }

    public function testGivenUnderscoredScopeWhenLoadingUserThenRoleIsUnchanged(): void
    {
        // Arrange
        $identifier = $this->createUserIdentifier(['customer', 'company_user']);

        // Act
        $apiUser = (new ApiUserProvider())->loadUserByIdentifier($identifier);

        // Assert
        $this->assertContains('ROLE_CUSTOMER', $apiUser->getRoles());
        $this->assertContains('ROLE_COMPANY_USER', $apiUser->getRoles());
    }

    public function testGivenAnyScopesWhenLoadingUserThenClaimsAreMappedOntoApiUser(): void
    {
        // Arrange
        $identifier = $this->createUserIdentifier(['merchant-user']);

        // Act
        /** @var \Spryker\ApiPlatform\Security\ApiUser $apiUser */
        $apiUser = (new ApiUserProvider())->loadUserByIdentifier($identifier);

        // Assert
        $this->assertSame(static::USER_ID, $apiUser->getUserIdentifier());
        $this->assertSame(static::OAUTH_CLIENT_ID, $apiUser->getOauthClientId());
        $this->assertSame(static::OAUTH_ACCESS_TOKEN_ID, $apiUser->getOauthAccessTokenId());
        $this->assertContains('ROLE_MERCHANT_USER', $apiUser->getRoles());
    }

    public function testGivenIdentifierWithoutUserIdWhenLoadingUserThenThrowsUserNotFoundException(): void
    {
        // Arrange
        $identifier = (string)json_encode([ApiUser::CLAIM_OAUTH_SCOPES => ['merchant-user']]);

        // Assert
        $this->expectException(UserNotFoundException::class);

        // Act
        (new ApiUserProvider())->loadUserByIdentifier($identifier);
    }

    public function testGivenForeignUserClassWhenRefreshingUserThenThrowsUnsupportedUserException(): void
    {
        // Arrange
        $userStub = $this->createForeignUser();

        // Assert
        $this->expectException(UnsupportedUserException::class);

        // Act
        (new ApiUserProvider())->refreshUser($userStub);
    }

    /**
     * @param array<string> $scopes
     */
    protected function createUserIdentifier(array $scopes): string
    {
        return (string)json_encode([
            ApiUser::CLAIM_USER_ID => static::USER_ID,
            ApiUser::CLAIM_OAUTH_CLIENT_ID => static::OAUTH_CLIENT_ID,
            ApiUser::CLAIM_OAUTH_ACCESS_TOKEN_ID => static::OAUTH_ACCESS_TOKEN_ID,
            ApiUser::CLAIM_OAUTH_SCOPES => $scopes,
        ]);
    }

    protected function createForeignUser(): UserInterface
    {
        return new class implements UserInterface {
            /**
             * @return array<string>
             */
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'foreign';
            }
        };
    }
}
