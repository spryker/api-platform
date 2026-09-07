<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\ApiPlatform\Helper;

use Spryker\Glue\GlueBackendApiApplication\GlueBackendApiApplicationFactory;
use SprykerTest\Shared\Propel\Helper\ConnectionHelper;
use SprykerTest\Shared\Propel\Helper\TransactionHelper;
use SprykerTest\Shared\Testify\Helper\BootstrapHelper;
use SprykerTest\Shared\Testify\Helper\ConfigHelper;
use SprykerTest\Shared\Testify\Helper\DataCleanupHelper;
use SprykerTest\Shared\Testify\Helper\DependencyHelper;
use SprykerTest\Shared\Testify\Helper\LocatorHelper;

/**
 * Wires the full-stack Backend API integration tier: a real `GLUE_BACKEND` kernel booted in-process
 * and dispatched to with `handleApiRequest()`, over the environment's own database.
 *
 * `ContainerHelper` must NOT be enabled here: its `_after()` nulls the shared `ContainerDelegator`,
 * which under `reuseApplicationContainer` is exactly the instance the next test method's kernel
 * needs. It stays in {@see BackendApiLogicHelper}, where no container is reused.
 *
 * Because the kernel runs in this process, it resolves the same Propel connection the fixtures were
 * written on, so `TransactionHelper` isolation works: rows a test creates are visible to the request
 * it then dispatches, and are rolled back afterwards.
 */
class BackendApiIntegrationHelper extends AbstractApiSuiteHelper
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
            ConfigHelper::class,
            DependencyHelper::class,
            ConnectionHelper::class,
            DataCleanupHelper::class,
            TransactionHelper::class,
            ApiProcessorProviderHelper::class,
        ];
    }
}
