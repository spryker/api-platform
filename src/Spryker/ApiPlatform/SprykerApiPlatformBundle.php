<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform;

use Spryker\ApiPlatform\DependencyInjection\Compiler\ApiResourceServiceRegistrationPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * API Platform Bundle
 *
 * This bundle provides schema-based API Platform resource generation capabilities as well as code that is need to integrate
 * the APiPlatform into Spryker.
 *
 * Resources can be defined using YAML schemas and are automatically generated
 * into PHP classes with full API Platform attribute support.
 *
 * @see https://docs.spryker.com/api-resource-generator TODO
 */
class SprykerApiPlatformBundle extends Bundle
{
    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(
            new ApiResourceServiceRegistrationPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            50,
        );
    }
}
