<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection\Compiler;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\DependencyInjection\Compiler\ApiTypeServiceFilterPass;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group Compiler
 * @group ApiTypeServiceFilterPassTest
 * Add your own group annotations below this line
 */
class ApiTypeServiceFilterPassTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenServiceWithNonMatchingApiTypeWhenProcessingThenServiceIsRemoved(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('spryker_api_platform.api_types', ['backend']);

        $definition = new Definition('App\Subscriber\StorefrontOnlySubscriber');
        $definition->addTag('spryker_api_platform.api_type', ['type' => 'storefront']);
        $container->setDefinition('app.storefront_subscriber', $definition);

        $pass = new ApiTypeServiceFilterPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertFalse($container->hasDefinition('app.storefront_subscriber'));
    }

    public function testGivenServiceWithMatchingApiTypeWhenProcessingThenServiceIsKept(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('spryker_api_platform.api_types', ['storefront']);

        $definition = new Definition('App\Subscriber\StorefrontOnlySubscriber');
        $definition->addTag('spryker_api_platform.api_type', ['type' => 'storefront']);
        $container->setDefinition('app.storefront_subscriber', $definition);

        $pass = new ApiTypeServiceFilterPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertTrue($container->hasDefinition('app.storefront_subscriber'));
    }

    public function testGivenServiceWithMultipleApiTypesWhenAnyTypeMatchesThenServiceIsKept(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('spryker_api_platform.api_types', ['backend']);

        $definition = new Definition('App\Subscriber\MultiTypeSubscriber');
        $definition->addTag('spryker_api_platform.api_type', ['type' => 'storefront']);
        $definition->addTag('spryker_api_platform.api_type', ['type' => 'backend']);
        $container->setDefinition('app.multi_type_subscriber', $definition);

        $pass = new ApiTypeServiceFilterPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertTrue($container->hasDefinition('app.multi_type_subscriber'));
    }

    public function testGivenUntaggedServiceWhenProcessingThenServiceIsNotAffected(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('spryker_api_platform.api_types', ['backend']);

        $definition = new Definition('App\Subscriber\RegularSubscriber');
        $container->setDefinition('app.regular_subscriber', $definition);

        $pass = new ApiTypeServiceFilterPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertTrue($container->hasDefinition('app.regular_subscriber'));
    }

    public function testGivenMissingApiTypesParameterWhenProcessingThenServiceIsKept(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        $definition = new Definition('App\Subscriber\StorefrontOnlySubscriber');
        $definition->addTag('spryker_api_platform.api_type', ['type' => 'storefront']);
        $container->setDefinition('app.storefront_subscriber', $definition);

        $pass = new ApiTypeServiceFilterPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertTrue($container->hasDefinition('app.storefront_subscriber'));
    }

    public function testGivenDifferentCaseApiTypeWhenProcessingThenComparisonIsCaseInsensitive(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('spryker_api_platform.api_types', ['Storefront']);

        $definition = new Definition('App\Subscriber\StorefrontOnlySubscriber');
        $definition->addTag('spryker_api_platform.api_type', ['type' => 'storefront']);
        $container->setDefinition('app.storefront_subscriber', $definition);

        $pass = new ApiTypeServiceFilterPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertTrue($container->hasDefinition('app.storefront_subscriber'));
    }
}
