<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use LogicException;
use Spryker\ApiPlatform\Contract\Coverage\ErrorMappingEntry;
use Spryker\ApiPlatform\Contract\Coverage\ErrorMappingResolver;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping\FixtureErrorMappingConfig;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping\FixtureOverriddenErrorMappingConfig;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping\FixtureProjectErrorMappingOverride;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping\FixtureStaticErrorMappingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ErrorMappingResolverTest
 * Add your own group annotations below this line
 */
class ErrorMappingResolverTest extends Unit
{
    protected const string FIXTURE_PROJECT_NAMESPACE = 'ErrorMappingFixtureProject';

    protected const string FIXTURE_OVERRIDE_CLASS = 'ErrorMappingFixtureProject\\ApiPlatform\\Unit\\Coverage\\Fixture\\ErrorMapping\\FixtureOverriddenErrorMappingConfig';

    public function testGivenAnInstanceMappingWhenResolvingThenEveryCodeStatusEntryIsReturned(): void
    {
        // Arrange
        $source = FixtureErrorMappingConfig::class . '::getErrorIdentifierToRestErrorMapping';

        // Act
        $entries = (new ErrorMappingResolver([]))->resolve($source);

        // Assert
        $this->assertSame(
            [$source . '  cart.not-found  404 code 101', $source . '  cart.locked  422 code 118'],
            array_map(static fn (ErrorMappingEntry $entry): string => $entry->key(), $entries),
        );
    }

    public function testGivenAStaticMappingWhenResolvingThenItIsCalledStatically(): void
    {
        // Arrange
        $source = FixtureStaticErrorMappingConfig::class . '::getErrorIdentifierToRestErrorMapping';

        // Act
        $entries = (new ErrorMappingResolver([]))->resolve($source);

        // Assert
        $this->assertCount(1, $entries);
        $this->assertSame('1503', $entries[0]->code);
        $this->assertSame(404, $entries[0]->status);
    }

    public function testGivenAProjectOverrideWhenResolvingThenTheOverrideEntriesWin(): void
    {
        // Arrange
        if (!class_exists(static::FIXTURE_OVERRIDE_CLASS)) {
            class_alias(FixtureProjectErrorMappingOverride::class, static::FIXTURE_OVERRIDE_CLASS);
        }
        $source = FixtureOverriddenErrorMappingConfig::class . '::getErrorIdentifierToRestErrorMapping';

        // Act
        $entries = (new ErrorMappingResolver([static::FIXTURE_PROJECT_NAMESPACE]))->resolve($source);

        // Assert
        $this->assertSame(['1104', '1105'], array_map(static fn (ErrorMappingEntry $entry): string => $entry->code, $entries));
    }

    public function testGivenAnEmptyMappingWhenResolvingThenItFails(): void
    {
        // Arrange
        $source = FixtureErrorMappingConfig::class . '::getEmptyErrorMapping';

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('returns no code/status entries');

        // Act
        (new ErrorMappingResolver([]))->resolve($source);
    }

    public function testGivenAMappingWithAnEntryWithoutCodeWhenResolvingThenItFailsNamingTheEntry(): void
    {
        // Arrange
        $source = FixtureErrorMappingConfig::class . '::getErrorMappingWithAnEntryWithoutCode';

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('The error mapping %s maps "cart.locked" to no code/status entry', $source));

        // Act
        (new ErrorMappingResolver([]))->resolve($source);
    }

    public function testGivenAMappingOfMessagesToIdentifiersWhenResolvingThenItFailsNamingTheFirstEntry(): void
    {
        // Arrange
        $source = FixtureErrorMappingConfig::class . '::getErrorMessageToErrorIdentifierMapping';

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('maps "Cart is gone." to no code/status entry');

        // Act
        (new ErrorMappingResolver([]))->resolve($source);
    }

    public function testGivenAMappingThatReturnsNoArrayWhenResolvingThenItFailsNamingTheClass(): void
    {
        // Arrange
        $source = FixtureErrorMappingConfig::class . '::getNonArrayErrorMapping';

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('The error mapping %s read from %s returns string instead of an array.', $source, FixtureErrorMappingConfig::class));

        // Act
        (new ErrorMappingResolver([]))->resolve($source);
    }
}
