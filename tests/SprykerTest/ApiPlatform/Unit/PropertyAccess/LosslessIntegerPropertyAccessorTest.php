<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\PropertyAccess;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\LossyIntegerConversionException;
use Spryker\ApiPlatform\PropertyAccess\LosslessIntegerPropertyAccessor;
use SprykerTest\ApiPlatform\Fixture\LosslessIntegerFixture;
use Symfony\Component\PropertyAccess\Exception\InvalidTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group PropertyAccess
 * @group LosslessIntegerPropertyAccessorTest
 * Add your own group annotations below this line
 */
class LosslessIntegerPropertyAccessorTest extends Unit
{
    protected const string PROPERTY_QUANTITY = 'quantity';

    protected const string PROPERTY_POSITION = 'position';

    protected const string PROPERTY_PRICE = 'price';

    protected const string PROPERTY_REFERENCE = 'reference';

    protected const float FRACTIONAL_NUMBER = 1.5;

    protected const string FRACTIONAL_NUMERIC_STRING = '1.5';

    protected const string NON_NUMERIC_STRING = 'abc';

    /**
     * @var array<string, bool>
     */
    protected const array DENORMALIZATION_CONTEXT = [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true];

    /**
     * @dataProvider provideFractionalValues
     */
    public function testGivenAFractionalValueWhenWritingAnIntegerPropertyThroughItsSetterThenItIsRejected(mixed $value): void
    {
        // Arrange
        $fixture = new LosslessIntegerFixture();

        // Act
        $exception = $this->captureException(fn () => $this->createAccessor()->setValue($fixture, static::PROPERTY_QUANTITY, $value));

        // Assert
        $this->assertInstanceOf(LossyIntegerConversionException::class, $exception);
        $this->assertSame(static::PROPERTY_QUANTITY, $exception->propertyPath);
        $this->assertNull($fixture->quantity);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function provideFractionalValues(): array
    {
        return [
            'a float' => [static::FRACTIONAL_NUMBER],
            'a numeric string' => [static::FRACTIONAL_NUMERIC_STRING],
        ];
    }

    public function testGivenAFractionalValueWhenWritingAnIntegerPropertyWithoutASetterThenItIsRejected(): void
    {
        // Arrange
        $fixture = new LosslessIntegerFixture();

        // Act
        $exception = $this->captureException(fn () => $this->createAccessor()->setValue($fixture, static::PROPERTY_POSITION, static::FRACTIONAL_NUMBER));

        // Assert
        $this->assertInstanceOf(LossyIntegerConversionException::class, $exception);
        $this->assertNull($fixture->position);
    }

    /**
     * @dataProvider provideLosslessValues
     */
    public function testGivenALosslessValueWhenWritingAnIntegerPropertyThenItIsWrittenAsInteger(mixed $value, int $expectedQuantity): void
    {
        // Arrange
        $fixture = new LosslessIntegerFixture();

        // Act
        $this->createAccessor()->setValue($fixture, static::PROPERTY_QUANTITY, $value);

        // Assert
        $this->assertSame($expectedQuantity, $fixture->quantity);
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public function provideLosslessValues(): array
    {
        return [
            'an integer' => [2, 2],
            'an integral float' => [2.0, 2],
            'an integer string' => ['2', 2],
            'an integral float string' => ['2.0', 2],
            'an integral exponent string' => ['1e3', 1000],
            'a negative integer string' => ['-2', -2],
        ];
    }

    public function testGivenAFractionalValueWhenWritingAFloatPropertyThenItIsWrittenUnchanged(): void
    {
        // Arrange
        $fixture = new LosslessIntegerFixture();

        // Act
        $this->createAccessor()->setValue($fixture, static::PROPERTY_PRICE, static::FRACTIONAL_NUMBER);

        // Assert
        $this->assertSame(static::FRACTIONAL_NUMBER, $fixture->price);
    }

    public function testGivenAFractionalStringWhenWritingAPropertyThatAlsoAcceptsStringsThenItIsWrittenUnchanged(): void
    {
        // Arrange
        $fixture = new LosslessIntegerFixture();

        // Act
        $this->createAccessor()->setValue($fixture, static::PROPERTY_REFERENCE, static::FRACTIONAL_NUMERIC_STRING);

        // Assert
        $this->assertSame(static::FRACTIONAL_NUMERIC_STRING, $fixture->reference);
    }

    public function testGivenANonNumericStringWhenWritingAnIntegerPropertyThenTheDecoratedAccessorStillRejectsIt(): void
    {
        // Arrange
        $fixture = new LosslessIntegerFixture();

        // Act
        $exception = $this->captureException(fn () => $this->createAccessor()->setValue($fixture, static::PROPERTY_QUANTITY, static::NON_NUMERIC_STRING));

        // Assert
        $this->assertInstanceOf(InvalidTypeException::class, $exception);
    }

    public function testGivenAFractionalValueWhenDenormalizingWithoutTypeEnforcementThenTheSerializerReportsTheAttribute(): void
    {
        // Act
        $exception = $this->captureException(fn () => $this->createSerializer()->denormalize(
            [static::PROPERTY_QUANTITY => static::FRACTIONAL_NUMBER],
            LosslessIntegerFixture::class,
            null,
            static::DENORMALIZATION_CONTEXT,
        ));

        // Assert
        $this->assertInstanceOf(NotNormalizableValueException::class, $exception);
        $this->assertInstanceOf(LossyIntegerConversionException::class, $exception->getPrevious());
    }

    public function testGivenAFractionalValueForANestedIntegerWhenDenormalizingWithoutTypeEnforcementThenItIsRejected(): void
    {
        // Act
        $exception = $this->captureException(fn () => $this->createSerializer()->denormalize(
            ['salesUnit' => ['id' => static::FRACTIONAL_NUMBER]],
            LosslessIntegerFixture::class,
            null,
            static::DENORMALIZATION_CONTEXT,
        ));

        // Assert
        $this->assertTrue($this->chainContains($exception, LossyIntegerConversionException::class));
    }

    public function testGivenAnIntegerStringWhenDenormalizingWithoutTypeEnforcementThenItIsStillAccepted(): void
    {
        // Act
        $fixture = $this->createSerializer()->denormalize(
            [static::PROPERTY_QUANTITY => '2'],
            LosslessIntegerFixture::class,
            null,
            static::DENORMALIZATION_CONTEXT,
        );

        // Assert
        $this->assertSame(2, $fixture->quantity);
    }

    protected function createAccessor(): LosslessIntegerPropertyAccessor
    {
        return new LosslessIntegerPropertyAccessor(PropertyAccess::createPropertyAccessor());
    }

    protected function createSerializer(): Serializer
    {
        return new Serializer([new ObjectNormalizer(null, null, $this->createAccessor(), new ReflectionExtractor())]);
    }

    protected function captureException(callable $callable): ?Throwable
    {
        try {
            $callable();
        } catch (Throwable $throwable) {
            return $throwable;
        }

        return null;
    }

    protected function chainContains(?Throwable $throwable, string $exceptionClass): bool
    {
        for (; $throwable !== null; $throwable = $throwable->getPrevious()) {
            if ($throwable instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }
}
