<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\ApiPlatform\Helper;

use Spryker\Glue\GlueBackendApiApplication\GlueBackendApiApplicationFactory;
use SprykerTest\Service\Container\Helper\ContainerHelper;
use SprykerTest\Shared\Testify\Helper\BootstrapHelper;
use SprykerTest\Shared\Testify\Helper\DependencyHelper;
use SprykerTest\Shared\Testify\Helper\LocatorHelper;

/**
 * Wires the Backend API logic tier: providers and processors resolved from the container with
 * only the named collaborators stubbed. No database, no HTTP.
 */
class BackendApiLogicHelper extends AbstractApiSuiteHelper
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [
        'application' => 'GLUE_BACKEND',
        'applicationPluginProvider' => [
            'class' => GlueBackendApiApplicationFactory::class,
            'method' => 'getApplicationPlugins',
        ],
        'projectNamespaces' => [],
        'environmentModule' => null,
    ];

    /**
     * @return array<string>
     */
    protected function getModulesBeforeEnvironment(): array
    {
        return [
            'Asserts',
            BootstrapHelper::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getModulesAfterEnvironment(): array
    {
        return [
            LocatorHelper::class,
            DependencyHelper::class,
            ContainerHelper::class,
            ApiProcessorProviderHelper::class,
        ];
    }
}
