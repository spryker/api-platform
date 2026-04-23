<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Spryker\ApiPlatform\Security\ApiUserProvider;
use Spryker\ApiPlatform\Security\GlueAuthenticationEntryPoint;
use Spryker\ApiPlatform\Security\OauthAuthenticator;

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
};
