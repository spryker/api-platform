<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Spryker\ApiPlatform\EventSubscriber\IdentityRequestSubscriber;
use Spryker\ApiPlatform\Security\ApiUserProvider;
use Spryker\ApiPlatform\Security\GlueAuthenticationEntryPoint;
use Spryker\ApiPlatform\Security\OauthAuthenticator;
use Spryker\ApiPlatform\Security\Resolver\IdentityResolver;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ApiUserProvider::class)
        ->public();

    $services->set(OauthAuthenticator::class)
        ->public();

    $services->set(GlueAuthenticationEntryPoint::class)
        ->public();

    // Resolves authenticated identity (raw OAuth claims) and stores them on the request
    // as `_oauth_identity_claims`. Domain-specific subscribers (CustomersRestApi,
    // CompanyUsersRestApi) read these claims to build CustomerTransfer / CompanyUserTransfer.
    $services->set(IdentityResolver::class);
    $services->set(IdentityRequestSubscriber::class);
};
