<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection\Compiler;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\DependencyInjection\Compiler\ApiPlatformDecoratorPass;
use Spryker\ApiPlatform\OpenApi\Decorator\ErrorResponseOpenApiDecorator;
use Spryker\ApiPlatform\OpenApi\Decorator\OpenApiDecorator;
use Spryker\ApiPlatform\PropertyAccess\LosslessIntegerPropertyAccessor;
use SprykerTest\ApiPlatform\ApiUnitTester;
use stdClass;
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
 * @group ApiPlatformDecoratorPassTest
 * Add your own group annotations below this line
 */
class ApiPlatformDecoratorPassTest extends Unit
{
    protected const string SERVICE_ID_RESOURCE_CLASS_RESOLVER = 'api_platform.resource_class_resolver';

    protected const string SERVICE_ID_OPENAPI_FACTORY = 'api_platform.openapi.factory';

    protected const int PRIORITY_DEFAULT = 0;

    protected const string SERVICE_ID_PROPERTY_ACCESSOR = 'property_accessor';

    /**
     * @var array<string>
     */
    protected const array SERIALIZER_PROPERTY_ACCESSOR_ALIASES = ['api_platform.property_accessor', 'serializer.property_accessor'];

    protected ApiUnitTester $tester;

    /**
     * Symfony applies the decorator with the higher priority first, so the lower priority of the error-response
     * decorator makes it the outermost one: it sees the document after the format decorator has run.
     */
    public function testGivenOpenApiFactoryWhenProcessingThenTheErrorResponseDecoratorWrapsTheFormatDecorator(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->register(static::SERVICE_ID_RESOURCE_CLASS_RESOLVER, stdClass::class);
        $container->register(static::SERVICE_ID_OPENAPI_FACTORY, stdClass::class);

        // Act
        (new ApiPlatformDecoratorPass())->process($container);

        // Assert
        $formatDecoration = $container->getDefinition(OpenApiDecorator::class)->getDecoratedService() ?? [];
        $errorDecoration = $container->getDefinition(ErrorResponseOpenApiDecorator::class)->getDecoratedService() ?? [];

        $this->assertSame(static::SERVICE_ID_OPENAPI_FACTORY, $errorDecoration[0] ?? null);
        $this->assertSame(static::PRIORITY_DEFAULT, $formatDecoration[2] ?? null);
        $this->assertLessThan($formatDecoration[2], $errorDecoration[2] ?? null);
    }

    public function testGivenNoOpenApiFactoryWhenProcessingThenNoDecoratorIsRegistered(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->register(static::SERVICE_ID_RESOURCE_CLASS_RESOLVER, stdClass::class);

        // Act
        (new ApiPlatformDecoratorPass())->process($container);

        // Assert
        $this->assertFalse($container->hasDefinition(ErrorResponseOpenApiDecorator::class));
    }

    public function testGivenTheSerializerPropertyAccessorAliasesWhenProcessingThenEachIsDecoratedWithTheLosslessIntegerAccessor(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->register(static::SERVICE_ID_RESOURCE_CLASS_RESOLVER, stdClass::class);
        $container->register(static::SERVICE_ID_PROPERTY_ACCESSOR, stdClass::class);
        foreach (static::SERIALIZER_PROPERTY_ACCESSOR_ALIASES as $alias) {
            $container->setAlias($alias, static::SERVICE_ID_PROPERTY_ACCESSOR);
        }

        // Act
        (new ApiPlatformDecoratorPass())->process($container);

        // Assert
        $decoratedIds = [];
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() === LosslessIntegerPropertyAccessor::class) {
                $decoratedIds[] = $definition->getDecoratedService()[0] ?? null;
            }
        }
        $this->assertEqualsCanonicalizing(static::SERIALIZER_PROPERTY_ACCESSOR_ALIASES, $decoratedIds);
    }

    public function testGivenNoPropertyAccessorWhenProcessingThenNoLosslessIntegerAccessorIsRegistered(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->register(static::SERVICE_ID_RESOURCE_CLASS_RESOLVER, stdClass::class);

        // Act
        (new ApiPlatformDecoratorPass())->process($container);

        // Assert
        $accessorDefinitions = array_filter(
            $container->getDefinitions(),
            static fn (Definition $definition): bool => $definition->getClass() === LosslessIntegerPropertyAccessor::class,
        );
        $this->assertSame([], $accessorDefinitions);
    }
}
