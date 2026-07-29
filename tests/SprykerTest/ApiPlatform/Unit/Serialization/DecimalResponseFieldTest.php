<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Serialization;

use Codeception\Test\Unit;
use Spryker\DecimalObject\Decimal;
use Spryker\Service\Serializer\SerializerInterface;
use Spryker\Service\Serializer\SerializerServiceFactory;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Guards the wire format of decimal-backed API Platform response fields: reducing a `Decimal` to
 * `int`/`float` before denormalization turns a decimal string into a JSON number and breaks a
 * published API contract.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Serialization
 * @group DecimalResponseFieldTest
 * Add your own group annotations below this line
 */
class DecimalResponseFieldTest extends Unit
{
    protected ApiUnitTester $tester;

    protected function getSerializer(): SerializerInterface
    {
        return (new SerializerServiceFactory())->createSerializer();
    }

    public function testGivenDecimalWithTrailingZerosWhenDenormalizingIntoStringPropertyThenPreservesFullScale(): void
    {
        // Arrange
        $serializer = $this->getSerializer();
        $data = ['amount' => Decimal::create('1.5000000000')];

        // Act
        $resource = $serializer->denormalize($data, DecimalResourceFixture::class);

        // Assert
        $this->assertSame('1.5000000000', $resource->amount);
    }

    public function testGivenIntegerValuedDecimalWhenDenormalizingIntoStringPropertyThenKeepsFractionalNotation(): void
    {
        // Arrange
        $serializer = $this->getSerializer();
        $data = ['amount' => Decimal::create('3.0')];

        // Act
        $resource = $serializer->denormalize($data, DecimalResourceFixture::class);

        // Assert
        $this->assertSame('3.0', $resource->amount);
    }

    public function testGivenDecimalNestedInArrayPropertyWhenDenormalizingThenEncodesAsDecimalString(): void
    {
        // Arrange
        $serializer = $this->getSerializer();
        $data = ['items' => [['amount' => Decimal::create('1.5000000000')]]];

        // Act
        $resource = $serializer->denormalize($data, DecimalResourceFixture::class);

        // Assert
        $this->assertSame('{"items":[{"amount":"1.5000000000"}]}', json_encode(['items' => $resource->items]));
    }

    public function testGivenDecimalPassedThroughArrayPropertyWhenEncodingThenMatchesDecimalJsonSerialization(): void
    {
        // Arrange
        $serializer = $this->getSerializer();
        $decimal = Decimal::create('1.5000000000');

        // Act
        $resource = $serializer->denormalize(['items' => [['amount' => $decimal]]], DecimalResourceFixture::class);

        // Assert
        $this->assertSame(json_encode(['amount' => $decimal]), json_encode($resource->items[0]));
    }

    public function testGivenIntegerValuedDecimalWhenDenormalizingIntoIntegerPropertyThenAssignsInteger(): void
    {
        // Arrange
        $serializer = $this->getSerializer();
        $data = ['quantity' => Decimal::create('5.0000000000')];

        // Act
        $resource = $serializer->denormalize($data, DecimalResourceFixture::class);

        // Assert
        $this->assertSame(5, $resource->quantity);
    }
}
