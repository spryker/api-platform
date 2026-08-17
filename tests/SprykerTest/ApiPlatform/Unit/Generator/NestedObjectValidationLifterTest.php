<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\ConstraintFormatter;
use Spryker\ApiPlatform\Generator\FqcnConstraintResolver;
use Spryker\ApiPlatform\Generator\NestedObjectValidationLifter;
use Spryker\ApiPlatform\Generator\ValidationAttributeGenerator;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapper;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group NestedObjectValidationLifterTest
 * Add your own group annotations below this line
 */
class NestedObjectValidationLifterTest extends Unit
{
    protected const string RESOURCE_NAME = 'Payments';

    protected const string CREATE_GROUP = 'payments:create';

    protected const string UPDATE_GROUP = 'payments:update';

    protected const string NOT_BLANK_ATTRIBUTE = "#[Assert\\NotBlank(groups: ['payments:create'])]";

    protected const string VALID_CASCADE_ATTRIBUTE = "#[Assert\\Valid(groups: ['payments:create'])]";

    /**
     * @var array<string, mixed>
     */
    protected const array POST_OPERATIONS = ['Post' => ['type' => 'Post']];

    public function testGivenSingleLevelCollectionWhenLiftingThenAttachesFieldConstraintsToLeafProperties(): void
    {
        // Arrange
        $nestedProperties = [
            'amount' => ['type' => 'integer'],
            'paymentMethodName' => ['type' => 'string'],
        ];
        $validationSchema = $this->createPostSchema('payment', $this->createCollection([
            'amount' => ['NotBlank', ['Type' => ['type' => 'numeric']]],
            'paymentMethodName' => ['NotBlank'],
        ]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'payment', static::RESOURCE_NAME);

        // Assert
        $this->assertSame(
            [static::NOT_BLANK_ATTRIBUTE, "#[Assert\\Type(type: 'numeric', groups: ['payments:create'])]"],
            $lifted['amount']['_validationAttributes'],
        );
        $this->assertSame([static::NOT_BLANK_ATTRIBUTE], $lifted['paymentMethodName']['_validationAttributes']);
    }

    public function testGivenTwoLevelCollectionWhenLiftingThenCascadesObjectFieldAndLiftsItsLeaves(): void
    {
        // Arrange
        $nestedProperties = [
            'customer' => $this->createObjectProperty([
                'firstName' => ['type' => 'string'],
                'email' => ['type' => 'string'],
            ]),
        ];
        $customerCollection = $this->createCollection([
            'firstName' => ['NotBlank'],
            'email' => ['NotBlank', 'Email'],
        ]);
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['customer' => $customerCollection]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertSame([static::VALID_CASCADE_ATTRIBUTE], $lifted['customer']['_validationAttributes']);
        $this->assertSame([static::NOT_BLANK_ATTRIBUTE], $lifted['customer']['properties']['firstName']['_validationAttributes']);
        $this->assertSame(
            [static::NOT_BLANK_ATTRIBUTE, "#[Assert\\Email(groups: ['payments:create'])]"],
            $lifted['customer']['properties']['email']['_validationAttributes'],
        );
    }

    public function testGivenThreeLevelCollectionWhenLiftingThenCascadesEveryObjectLevelAndLiftsTheDeepestLeaf(): void
    {
        // Arrange
        $billingAddressProperty = $this->createObjectProperty(['iso2Code' => ['type' => 'string']]);
        $nestedProperties = ['customer' => $this->createObjectProperty(['billingAddress' => $billingAddressProperty])];

        $billingAddressCollection = $this->createCollection(['iso2Code' => ['NotBlank', 'Country']]);
        $customerCollection = $this->createCollection(['billingAddress' => $billingAddressCollection]);
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['customer' => $customerCollection]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $billingAddress = $lifted['customer']['properties']['billingAddress'];

        $this->assertSame([static::VALID_CASCADE_ATTRIBUTE], $lifted['customer']['_validationAttributes']);
        $this->assertSame([static::VALID_CASCADE_ATTRIBUTE], $billingAddress['_validationAttributes']);
        $this->assertSame(
            [static::NOT_BLANK_ATTRIBUTE, "#[Assert\\Country(groups: ['payments:create'])]"],
            $billingAddress['properties']['iso2Code']['_validationAttributes'],
        );
    }

    public function testGivenNestedCollectionWhenLiftingThenEmitsNoCollectionAttributeOnTheObjectProperty(): void
    {
        // Arrange
        $nestedProperties = ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])];
        $customerCollection = $this->createCollection(['email' => ['NotBlank']]);
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['customer' => $customerCollection]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertStringNotContainsString('Assert\\Collection', implode("\n", $lifted['customer']['_validationAttributes']));
    }

    public function testGivenObjectCollectionFieldWhenLiftingThenLiftsIntoTheItemProperties(): void
    {
        // Arrange
        $nestedProperties = [
            'items' => [
                'type' => 'array',
                'items' => $this->createObjectProperty(['sku' => ['type' => 'string']]),
            ],
        ];
        $itemCollection = $this->createCollection(['sku' => ['NotBlank']]);
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['items' => $itemCollection]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertSame([static::VALID_CASCADE_ATTRIBUTE], $lifted['items']['_validationAttributes']);
        $this->assertSame([static::NOT_BLANK_ATTRIBUTE], $lifted['items']['items']['properties']['sku']['_validationAttributes']);
    }

    public function testGivenAllWrappedObjectCollectionWhenLiftingThenLiftsIntoTheItemPropertiesAndCascadesPerElement(): void
    {
        // Arrange
        $nestedProperties = [
            'prices' => [
                'type' => 'array',
                'items' => $this->createObjectProperty(['priceTypeName' => ['type' => 'string']]),
            ],
        ];
        $priceConstraints = [
            ['All' => ['constraints' => $this->createCollection(['priceTypeName' => ['NotBlank']])]],
        ];
        $validationSchema = $this->createPostSchema('payment', $this->createCollection(['prices' => $priceConstraints]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'payment', static::RESOURCE_NAME);

        // Assert
        $this->assertSame(
            ["#[Assert\\All(constraints: [new Assert\\Valid(groups: ['payments:create'])], groups: ['payments:create'])]"],
            $lifted['prices']['_validationAttributes'],
        );
        $this->assertSame(
            [static::NOT_BLANK_ATTRIBUTE],
            $lifted['prices']['items']['properties']['priceTypeName']['_validationAttributes'],
        );
    }

    public function testGivenAllWrappedCollectionOnAnUntypedArrayWhenLiftingThenLeavesTheCollectionConstraintInPlace(): void
    {
        // Arrange
        $nestedProperties = ['prices' => ['type' => 'array']];
        $priceConstraints = [
            ['All' => ['constraints' => $this->createCollection(['priceTypeName' => ['NotBlank']])]],
        ];
        $validationSchema = $this->createPostSchema('payment', $this->createCollection(['prices' => $priceConstraints]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'payment', static::RESOURCE_NAME);

        // Assert
        $this->assertStringContainsString('Assert\\Collection(', $lifted['prices']['_validationAttributes'][0]);
    }

    public function testGivenSiblingConstraintsNextToNestedCollectionWhenLiftingThenKeepsThemAndSwapsOnlyTheCollection(): void
    {
        // Arrange
        $nestedProperties = ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])];
        $customerConstraints = array_merge(['NotNull'], $this->createCollection(['email' => ['NotBlank']]));
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['customer' => $customerConstraints]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertSame(
            ["#[Assert\\NotNull(groups: ['payments:create'])]", static::VALID_CASCADE_ATTRIBUTE],
            $lifted['customer']['_validationAttributes'],
        );
    }

    public function testGivenOptionalWrappedNestedCollectionWhenLiftingThenCascadesWithTheOptionalPayload(): void
    {
        // Arrange
        $nestedProperties = ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])];
        $customerConstraints = [
            ['Optional' => ['constraints' => $this->createCollection(['email' => ['NotBlank']])]],
        ];
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['customer' => $customerConstraints]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertSame(
            ["#[Assert\\Valid(groups: ['payments:create'], payload: ['source' => 'optional'])]"],
            $lifted['customer']['_validationAttributes'],
        );
        $this->assertSame([static::NOT_BLANK_ATTRIBUTE], $lifted['customer']['properties']['email']['_validationAttributes']);
    }

    public function testGivenAllowMissingFieldsOnNestedCollectionWhenLiftingThenRelaxesTheDeeperRequiredFields(): void
    {
        // Arrange
        $nestedProperties = ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])];
        $customerConstraints = [
            [
                'Collection' => [
                    'allowMissingFields' => true,
                    'fields' => ['email' => ['NotBlank']],
                ],
            ],
        ];
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['customer' => $customerConstraints]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertSame(
            ["#[Assert\\NotBlank(allowNull: true, groups: ['payments:create'])]"],
            $lifted['customer']['properties']['email']['_validationAttributes'],
        );
    }

    public function testGivenSelfReferentialObjectNameWhenLiftingThenCascadesWithoutRecursingIntoItself(): void
    {
        // Arrange
        $parentAddress = $this->createObjectProperty(['iso2Code' => ['type' => 'string']]);
        $parentAddress['objectName'] = 'Address';

        $billingAddress = $this->createObjectProperty([
            'iso2Code' => ['type' => 'string'],
            'parentAddress' => $parentAddress,
        ]);
        $billingAddress['objectName'] = 'Address';
        $nestedProperties = ['billingAddress' => $billingAddress];

        $addressCollection = $this->createCollection([
            'iso2Code' => ['NotBlank'],
            'parentAddress' => $this->createCollection(['iso2Code' => ['NotBlank']]),
        ]);
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['billingAddress' => $addressCollection]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $liftedAddress = $lifted['billingAddress'];

        $this->assertSame([static::VALID_CASCADE_ATTRIBUTE], $liftedAddress['_validationAttributes']);
        $this->assertSame([static::NOT_BLANK_ATTRIBUTE], $liftedAddress['properties']['iso2Code']['_validationAttributes']);
        $this->assertSame([static::VALID_CASCADE_ATTRIBUTE], $liftedAddress['properties']['parentAddress']['_validationAttributes']);
        $this->assertArrayNotHasKey(
            '_validationAttributes',
            $liftedAddress['properties']['parentAddress']['properties']['iso2Code'],
        );
    }

    public function testGivenNestedCollectionOnAnUntypedFieldWhenLiftingThenLeavesTheCollectionConstraintInPlace(): void
    {
        // Arrange
        $nestedProperties = ['metadata' => ['type' => 'map']];
        $metadataCollection = $this->createCollection(['key' => ['NotBlank']]);
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['metadata' => $metadataCollection]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertStringContainsString('#[Assert\\Collection(', $lifted['metadata']['_validationAttributes'][0]);
    }

    public function testGivenTheSameDeeplyNestedDefinitionWhenLiftingTwiceThenProducesIdenticalOutput(): void
    {
        // Arrange
        $country = $this->createObjectProperty(['iso2Code' => ['type' => 'string']]);
        $billingAddress = $this->createObjectProperty(['country' => $country]);
        $nestedProperties = ['customer' => $this->createObjectProperty(['billingAddress' => $billingAddress])];

        $countryCollection = $this->createCollection(['iso2Code' => ['NotBlank', 'Country']]);
        $billingAddressCollection = $this->createCollection(['country' => $countryCollection]);
        $customerCollection = $this->createCollection(['billingAddress' => $billingAddressCollection]);
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['customer' => $customerCollection]));
        $lifter = $this->createLifter();

        // Act
        $first = $lifter->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);
        $second = $lifter->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertSame($first, $second);
        $this->assertSame(
            [static::NOT_BLANK_ATTRIBUTE, "#[Assert\\Country(groups: ['payments:create'])]"],
            $first['customer']['properties']['billingAddress']['properties']['country']['properties']['iso2Code']['_validationAttributes'],
        );
    }

    public function testGivenNestedCollectionUnderSeveralOperationsWhenLiftingThenMergesTheCascadeGroups(): void
    {
        // Arrange
        $nestedProperties = ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])];
        $quoteConstraints = $this->createCollection([
            'customer' => $this->createCollection(['email' => ['NotBlank']]),
        ]);
        $validationSchema = [
            'post' => ['quote' => $quoteConstraints],
            'patch' => ['quote' => $quoteConstraints],
        ];
        $operations = ['Post' => ['type' => 'Post'], 'Patch' => ['type' => 'Patch']];

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, $operations, 'quote', static::RESOURCE_NAME);

        // Assert
        $this->assertSame(
            [sprintf("#[Assert\\Valid(groups: ['%s', '%s'])]", static::CREATE_GROUP, static::UPDATE_GROUP)],
            $lifted['customer']['_validationAttributes'],
        );
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<int, array<string, mixed>>
     */
    protected function createCollection(array $fields): array
    {
        return [['Collection' => ['fields' => $fields]]];
    }

    /**
     * @param array<mixed> $constraints
     *
     * @return array<string, mixed>
     */
    protected function createPostSchema(string $propertyName, array $constraints): array
    {
        return ['post' => [$propertyName => $constraints]];
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    protected function createObjectProperty(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties];
    }

    protected function createLifter(): NestedObjectValidationLifter
    {
        return new NestedObjectValidationLifter(
            new ValidationAttributeGenerator(
                new ValidationGroupMapper(),
                new ConstraintFormatter(),
                new FqcnConstraintResolver(),
            ),
        );
    }
}
