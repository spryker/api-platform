<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProviderInterface;
use Codeception\Stub;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\State\WhitespaceTrimmingDeserializeProvider;
use SprykerTest\ApiPlatform\Fixture\WhitespaceTrimmingFixture;
use SprykerTest\ApiPlatform\Fixture\WhitespaceTrimmingNestedFixture;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group WhitespaceTrimmingDeserializeProviderTest
 * Add your own group annotations below this line
 */
class WhitespaceTrimmingDeserializeProviderTest extends Unit
{
    public function testGivenPaddedSubmittedStringsWhenProvidingThenTheyAreTrimmed(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->name = "  Spryker \t";
        $whitespaceTrimmingFixture->description = "\n Text \r\n";

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Post(),
            [],
            $this->buildContext(['name' => "  Spryker \t", 'description' => "\n Text \r\n"]),
        );

        // Assert
        $this->assertSame('Spryker', $data->name);
        $this->assertSame('Text', $data->description);
    }

    public function testGivenAPropertyThatAllowsWhitespaceWhenProvidingThenItIsKeptAsSubmitted(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->password = ' secret ';

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Post(),
            [],
            $this->buildContext(['password' => ' secret ']),
        );

        // Assert
        $this->assertSame(' secret ', $data->password);
    }

    public function testGivenAPaddedStoredValueThatWasNotSubmittedWhenProvidingThenItIsLeftAlone(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->name = ' Stored name ';
        $whitespaceTrimmingFixture->description = ' New text ';

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Patch(),
            [],
            $this->buildContext(['description' => ' New text ']),
        );

        // Assert
        $this->assertSame(' Stored name ', $data->name);
        $this->assertSame('New text', $data->description);
    }

    public function testGivenADeserializedValueThatDiffersFromTheSubmittedOneWhenProvidingThenItIsLeftAlone(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->name = ' Set by the provider ';

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Patch(),
            [],
            $this->buildContext(['name' => ' Submitted ']),
        );

        // Assert
        $this->assertSame(' Set by the provider ', $data->name);
    }

    public function testGivenANestedObjectWhenProvidingThenItsSubmittedStringsAreTrimmedUnlessTheyAllowWhitespace(): void
    {
        // Arrange
        $whitespaceTrimmingNestedFixture = new WhitespaceTrimmingNestedFixture();
        $whitespaceTrimmingNestedFixture->city = ' Berlin ';
        $whitespaceTrimmingNestedFixture->addressLine = ' Main Street 1 ';
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->address = $whitespaceTrimmingNestedFixture;

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Post(),
            [],
            $this->buildContext(['address' => ['city' => ' Berlin ', 'addressLine' => ' Main Street 1 ']]),
        );

        // Assert
        $this->assertSame('Berlin', $data->address->city);
        $this->assertSame(' Main Street 1 ', $data->address->addressLine);
    }

    public function testGivenAListAndAListOfMapsWhenProvidingThenTheirSubmittedStringsAreTrimmed(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->tags = [' DE ', 'AT ', 5];
        $whitespaceTrimmingFixture->addresses = [['city' => ' Berlin ', 'zipCode' => 10115]];

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Post(),
            [],
            $this->buildContext([
                'tags' => [' DE ', 'AT ', 5],
                'addresses' => [['city' => ' Berlin ', 'zipCode' => 10115]],
            ]),
        );

        // Assert
        $this->assertSame(['DE', 'AT', 5], $data->tags);
        $this->assertSame([['city' => 'Berlin', 'zipCode' => 10115]], $data->addresses);
    }

    public function testGivenAListThatAllowsWhitespaceWhenProvidingThenItsItemsAreKeptAsSubmitted(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->codes = [' A1 '];

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Post(),
            [],
            $this->buildContext(['codes' => [' A1 ']]),
        );

        // Assert
        $this->assertSame([' A1 '], $data->codes);
    }

    public function testGivenANonPublicOrUnknownSubmittedAttributeWhenProvidingThenNothingIsAssigned(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->setInternalNote(' Note ');

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Post(),
            [],
            $this->buildContext(['internalNote' => ' Note ', 'unknown' => ' Value ', 7 => ' Numeric ']),
        );

        // Assert
        $this->assertSame(' Note ', $data->getInternalNote());
        $this->assertObjectNotHasProperty('unknown', $data);
    }

    public function testGivenANonStringSubmittedValueWhenProvidingThenItIsLeftAlone(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->quantity = 3;

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(
            new Post(),
            [],
            $this->buildContext(['quantity' => 3]),
        );

        // Assert
        $this->assertSame(3, $data->quantity);
    }

    public function testGivenARequestBodyWithoutAttributesWhenProvidingThenTheDeserializedObjectIsReturnedUntouched(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->name = ' Spryker ';
        $request = Request::create('/fixtures', Request::METHOD_POST, [], [], [], [], 'not json');

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(new Post(), [], ['request' => $request]);

        // Assert
        $this->assertSame(' Spryker ', $data->name);
    }

    public function testGivenNoRequestWhenProvidingThenTheDeserializedObjectIsReturnedUntouched(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->name = ' Spryker ';

        // Act
        $data = $this->createProvider($whitespaceTrimmingFixture)->provide(new Post());

        // Assert
        $this->assertSame(' Spryker ', $data->name);
    }

    public function testGivenACollectionWhenProvidingThenItIsReturnedUntouched(): void
    {
        // Arrange
        $whitespaceTrimmingFixture = new WhitespaceTrimmingFixture();
        $whitespaceTrimmingFixture->name = ' Spryker ';

        // Act
        $data = $this->createProvider([$whitespaceTrimmingFixture])->provide(
            new Post(),
            [],
            $this->buildContext(['name' => ' Spryker ']),
        );

        // Assert
        $this->assertSame(' Spryker ', $data[0]->name);
    }

    protected function createProvider(mixed $deserialized): WhitespaceTrimmingDeserializeProvider
    {
        /** @var \ApiPlatform\State\ProviderInterface<object> $decorated */
        $decorated = Stub::makeEmpty(ProviderInterface::class, [
            'provide' => fn (): mixed => $deserialized,
        ]);

        return new WhitespaceTrimmingDeserializeProvider($decorated);
    }

    /**
     * @param array<array-key, mixed> $submittedAttributes
     *
     * @return array<string, mixed>
     */
    protected function buildContext(array $submittedAttributes): array
    {
        $body = (string)json_encode(['data' => ['type' => 'fixtures', 'attributes' => $submittedAttributes]]);

        return ['request' => Request::create('/fixtures', Request::METHOD_PATCH, [], [], [], [], $body)];
    }
}
