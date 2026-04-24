<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State\Trait;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\ApiPlatformContextException;
use Spryker\ApiPlatform\State\Trait\UriVariableAwareTrait;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group Trait
 * @group UriVariableAwareTraitTest
 * Add your own group annotations below this line
 */
class UriVariableAwareTraitTest extends Unit
{
    public function testGivenUriVariableSetWhenHasUriVariableThenReturnsTrue(): void
    {
        // Arrange
        $fixture = $this->createFixture(['cartId' => '42']);

        // Act
        $result = $fixture->callHasUriVariable('cartId');

        // Assert
        $this->assertTrue($result);
    }

    public function testGivenUriVariableMissingWhenHasUriVariableThenReturnsFalse(): void
    {
        // Arrange
        $fixture = $this->createFixture([]);

        // Act
        $result = $fixture->callHasUriVariable('cartId');

        // Assert
        $this->assertFalse($result);
    }

    public function testGivenUriVariableSetWhenGetUriVariableThenReturnsValue(): void
    {
        // Arrange
        $fixture = $this->createFixture(['cartId' => '42']);

        // Act
        $result = $fixture->callGetUriVariable('cartId');

        // Assert
        $this->assertSame('42', $result);
    }

    public function testGivenUriVariableMissingWhenGetUriVariableThenThrowsApiPlatformContextException(): void
    {
        // Arrange
        $fixture = $this->createFixture([]);

        // Assert
        $this->expectException(ApiPlatformContextException::class);

        // Act
        $fixture->callGetUriVariable('cartId');
    }

    public function testGivenUriVariableSetWhenFindUriVariableThenReturnsValue(): void
    {
        // Arrange
        $fixture = $this->createFixture(['cartId' => '42']);

        // Act
        $result = $fixture->callFindUriVariable('cartId');

        // Assert
        $this->assertSame('42', $result);
    }

    public function testGivenUriVariableMissingWhenFindUriVariableThenReturnsNull(): void
    {
        // Arrange
        $fixture = $this->createFixture([]);

        // Act
        $result = $fixture->callFindUriVariable('cartId');

        // Assert
        $this->assertNull($result);
    }

    public function testGivenUriVariablesSetWhenGetUriVariablesThenReturnsFullArray(): void
    {
        // Arrange
        $uriVariables = ['cartId' => '42', 'itemId' => '7'];
        $fixture = $this->createFixture($uriVariables);

        // Act
        $result = $fixture->callGetUriVariables();

        // Assert
        $this->assertSame($uriVariables, $result);
    }

    public function testGivenNoUriVariablesWhenGetUriVariablesThenReturnsEmptyArray(): void
    {
        // Arrange
        $fixture = $this->createFixture([]);

        // Act
        $result = $fixture->callGetUriVariables();

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    protected function createFixture(array $uriVariables): object
    {
        $fixture = new class {
            use UriVariableAwareTrait;

            /**
             * @param array<string, mixed> $uriVariables
             */
            public function seed(array $uriVariables): void
            {
                $this->uriVariables = $uriVariables;
            }

            public function callHasUriVariable(string $name): bool
            {
                return $this->hasUriVariable($name);
            }

            public function callGetUriVariable(string $name): mixed
            {
                return $this->getUriVariable($name);
            }

            public function callFindUriVariable(string $name): mixed
            {
                return $this->findUriVariable($name);
            }

            /**
             * @return array<string, mixed>
             */
            public function callGetUriVariables(): array
            {
                return $this->getUriVariables();
            }
        };

        $fixture->seed($uriVariables);

        return $fixture;
    }
}
