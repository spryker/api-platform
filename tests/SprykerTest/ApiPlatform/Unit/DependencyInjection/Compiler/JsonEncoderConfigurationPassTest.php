<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection\Compiler;

use ApiPlatform\Serializer\JsonEncoder as ApiPlatformJsonEncoder;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\DependencyInjection\Compiler\JsonEncoderConfigurationPass;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group Compiler
 * @group JsonEncoderConfigurationPassTest
 * Add your own group annotations below this line
 */
class JsonEncoderConfigurationPassTest extends Unit
{
    protected const int EXPECTED_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE;

    protected ApiUnitTester $tester;

    public function testGivenSingleArgumentEncoderDefinitionsWhenProcessingThenWrappedEncoderWithLegacyByteFormOptionsIsAppended(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setDefinition('api_platform.jsonapi.encoder', new Definition(ApiPlatformJsonEncoder::class, ['jsonapi']));
        $container->setDefinition('api_platform.problem.encoder', new Definition(ApiPlatformJsonEncoder::class, ['jsonproblem']));

        // Act
        (new JsonEncoderConfigurationPass())->process($container);

        // Assert
        foreach (['api_platform.jsonapi.encoder', 'api_platform.problem.encoder'] as $serviceId) {
            $arguments = $container->getDefinition($serviceId)->getArguments();
            $this->assertCount(2, $arguments, $serviceId);

            $wrappedEncoder = $arguments[1];
            $this->assertInstanceOf(Definition::class, $wrappedEncoder);
            $this->assertSame(JsonEncoder::class, $wrappedEncoder->getClass());

            $jsonEncode = $wrappedEncoder->getArgument(0);
            $this->assertInstanceOf(Definition::class, $jsonEncode);
            $this->assertSame(JsonEncode::class, $jsonEncode->getClass());
            $this->assertSame([JsonEncode::OPTIONS => static::EXPECTED_OPTIONS], $jsonEncode->getArgument(0));
        }
    }

    public function testGivenPreConfiguredEncoderDefinitionWhenProcessingThenDefinitionIsLeftUntouched(): void
    {
        // Arrange: a future API Platform version may pre-configure the wrapped encoder itself
        $preConfigured = new Definition(JsonEncoder::class);
        $definition = new Definition(ApiPlatformJsonEncoder::class, ['jsonapi', $preConfigured]);
        $container = new ContainerBuilder();
        $container->setDefinition('api_platform.jsonapi.encoder', $definition);

        // Act
        (new JsonEncoderConfigurationPass())->process($container);

        // Assert
        $arguments = $container->getDefinition('api_platform.jsonapi.encoder')->getArguments();
        $this->assertCount(2, $arguments);
        $this->assertSame($preConfigured, $arguments[1]);
    }

    public function testGivenMissingEncoderDefinitionsWhenProcessingThenNothingIsRegistered(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        (new JsonEncoderConfigurationPass())->process($container);

        // Assert
        $this->assertFalse($container->hasDefinition('api_platform.jsonapi.encoder'));
        $this->assertFalse($container->hasDefinition('api_platform.problem.encoder'));
    }
}
