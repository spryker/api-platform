<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Security\Resolver;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Security\ApiUser;
use Spryker\ApiPlatform\Security\Resolver\IdentityResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Security
 * @group Resolver
 * @group IdentityResolverTest
 * Add your own group annotations below this line
 * @group SecurityAuthorization
 */
class IdentityResolverTest extends Unit
{
    public function testGivenAuthenticatedApiUserWithClaimsWhenResolveIsCalledThenClaimsAreReturned(): void
    {
        // Arrange
        $tokenStorage = new TokenStorage();
        $apiUser = new ApiUser('user-uuid-1', ['ROLE_BACK_OFFICE_USER'], null, null, ['id_user' => 7, 'user_reference' => 'user-uuid-1']);
        $tokenStorage->setToken(new PostAuthenticationToken($apiUser, 'main', $apiUser->getRoles()));

        // Act
        $claims = (new IdentityResolver($tokenStorage))->resolve();

        // Assert
        $this->assertSame(['id_user' => 7, 'user_reference' => 'user-uuid-1'], $claims);
    }

    public function testGivenNoTokenWhenResolveIsCalledThenNullIsReturned(): void
    {
        // Act
        $claims = (new IdentityResolver(new TokenStorage()))->resolve();

        // Assert
        $this->assertNull($claims);
    }
}
