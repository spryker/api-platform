<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProviderInterface;
use Codeception\Stub;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\State\StrictBooleanCanonicalizingDeserializeProvider;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use SprykerTest\ApiPlatform\Fixture\CollectionFixtureItem;
use SprykerTest\ApiPlatform\Fixture\StrictBooleanFixture;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group StrictBooleanCanonicalizingDeserializeProviderTest
 * Add your own group annotations below this line
 */
class StrictBooleanCanonicalizingDeserializeProviderTest extends Unit
{
    protected const string PROPERTY = 'isActive';

    public function testAppliesFalseWhenTheClientSpelledTheStringFalse(): void
    {
        // Arrange
        $deserialized = new StrictBooleanFixture();
        $deserialized->isActive = true;

        // Act
        $data = $this->createProvider($deserialized)->provide(new Post(), [], $this->buildContext(['isActive' => 'false']));

        // Assert
        $this->assertFalse($data->isActive);
    }

    public function testKeepsTrueWhenTheClientSpelledTheStringTrue(): void
    {
        // Arrange
        $deserialized = new StrictBooleanFixture();
        $deserialized->isActive = true;

        // Act
        $data = $this->createProvider($deserialized)->provide(new Post(), [], $this->buildContext(['isActive' => 'true']));

        // Assert
        $this->assertTrue($data->isActive);
    }

    /**
     * @dataProvider provideValuesThatMustNotBeRewritten
     */
    public function testLeavesTheDeserializedValueAloneForAnythingElse(mixed $submittedValue, bool $deserializedValue): void
    {
        // Arrange
        $deserialized = new StrictBooleanFixture();
        $deserialized->isActive = $deserializedValue;

        // Act
        $data = $this->createProvider($deserialized)
            ->provide(new Post(), [], $this->buildContext(['isActive' => $submittedValue]));

        // Assert
        $this->assertSame($deserializedValue, $data->isActive);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public function provideValuesThatMustNotBeRewritten(): array
    {
        return [
            'a real boolean' => [true, true],
            'a capitalised "True"' => ['True', true],
            'an unrelated string' => ['yes', true],
            'an integer' => [1, true],
            'null' => [null, false],
        ];
    }

    public function testLeavesAPropertyWithoutTheConstraintAlone(): void
    {
        // Arrange
        $deserialized = new CollectionFixtureItem();
        $deserialized->sku = 'false';

        // Act
        $data = $this->createProvider($deserialized)->provide(new Post(), [], $this->buildContext(['sku' => 'false']));

        // Assert
        $this->assertSame('false', $data->sku);
    }

    public function testReturnsACollectionUntouched(): void
    {
        // Arrange
        $provider = $this->createProvider([new StrictBooleanFixture()]);

        // Act
        $data = $provider->provide(new Post(), [], $this->buildContext(['isActive' => 'false']));

        // Assert
        $this->assertIsArray($data);
    }

    public function testReturnsTheDeserializedObjectWhenThereIsNoRequest(): void
    {
        // Arrange
        $deserialized = new StrictBooleanFixture();
        $deserialized->isActive = true;

        // Act
        $data = $this->createProvider($deserialized)->provide(new Post());

        // Assert
        $this->assertTrue($data->isActive);
    }

    protected function createProvider(mixed $deserialized): StrictBooleanCanonicalizingDeserializeProvider
    {
        /** @var \ApiPlatform\State\ProviderInterface<object> $decorated */
        $decorated = Stub::makeEmpty(ProviderInterface::class, [
            'provide' => fn (): mixed => $deserialized,
        ]);

        return new StrictBooleanCanonicalizingDeserializeProvider($decorated, new ValidationConstraintReader());
    }

    /**
     * @param array<string, mixed> $submittedAttributes
     *
     * @return array<string, mixed>
     */
    protected function buildContext(array $submittedAttributes): array
    {
        $body = (string)json_encode(['data' => ['type' => 'fixtures', 'attributes' => $submittedAttributes]]);

        return ['request' => Request::create('/fixtures', Request::METHOD_POST, [], [], [], [], $body)];
    }
}
