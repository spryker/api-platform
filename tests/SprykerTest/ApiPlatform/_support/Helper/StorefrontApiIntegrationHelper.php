<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\ApiPlatform\Helper;

use Spryker\Glue\GlueStorefrontApiApplication\GlueStorefrontApiApplicationFactory;
use SprykerTest\Client\Queue\Helper\QueueHelper;
use SprykerTest\Client\Testify\Helper\ClientHelper;
use SprykerTest\Client\ZedRequest\Helper\InProcessZedRequestHelper;
use SprykerTest\Shared\Propel\Helper\TransactionHelper;
use SprykerTest\Shared\Testify\Helper\BootstrapHelper;
use SprykerTest\Shared\Testify\Helper\ConfigHelper;
use SprykerTest\Shared\Testify\Helper\DataCleanupHelper;
use SprykerTest\Shared\Testify\Helper\DependencyHelper;
use SprykerTest\Shared\Testify\Helper\LocatorHelper;
use SprykerTest\Shared\Testify\Helper\SqliteDatabaseHelper;
use SprykerTest\Zed\Event\Helper\EventHelper;
use SprykerTest\Zed\EventBehavior\Helper\EventBehaviorHelper;
use SprykerTest\Zed\Publisher\Helper\PublishHelper;
use SprykerTest\Zed\Testify\Helper\Business\BusinessHelper;
use SprykerTest\Zed\Testify\Helper\Business\DependencyProviderHelper;

/**
 * Wires the full-stack Storefront API integration tier: a real kernel over a SQLite database, with
 * the Client-to-Zed remote call dispatched in-process. Set `publish: true` to add the publish leg.
 */
class StorefrontApiIntegrationHelper extends AbstractApiSuiteHelper
{
    /**
     * The publish leg's queue helper guesses its application from the suite namespace, which is
     * wrong for a Glue-namespaced suite.
     */
    protected const string QUEUE_APPLICATION = 'Zed';

    /**
     * @var array<string>
     */
    protected const array PUBLISH_MODULES = [
        ClientHelper::class,
        DependencyProviderHelper::class,
        EventHelper::class,
        QueueHelper::class,
        BusinessHelper::class,
        EventBehaviorHelper::class,
        PublishHelper::class,
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $config = [
        'application' => 'GLUE_STOREFRONT',
        'applicationPluginProvider' => [
            'class' => GlueStorefrontApiApplicationFactory::class,
            'method' => 'getApplicationPlugins',
        ],
        'projectNamespaces' => [],
        'environmentModule' => null,
        'publish' => false,
    ];

    /**
     * SqliteDatabaseHelper comes first: it writes the database env vars that the Spryker Config
     * reads before it is frozen.
     *
     * @return array<string>
     */
    protected function getModulesBeforeEnvironment(): array
    {
        return [
            SqliteDatabaseHelper::class,
            'Asserts',
            BootstrapHelper::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getModulesAfterEnvironment(): array
    {
        $modules = [
            LocatorHelper::class,
            ConfigHelper::class,
            DependencyHelper::class,
            DataCleanupHelper::class,
            TransactionHelper::class,
            ApiProcessorProviderHelper::class,
            InProcessZedRequestHelper::class,
        ];

        if (!$this->config['publish']) {
            return $modules;
        }

        return array_merge($modules, static::PUBLISH_MODULES);
    }

    protected function forwardConfiguration(): void
    {
        parent::forwardConfiguration();

        if (!$this->config['publish']) {
            return;
        }

        $this->setModuleConfig(QueueHelper::class, ['application' => static::QUEUE_APPLICATION]);
    }
}
