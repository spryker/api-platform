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

    protected const string PARENT_PROPERTY = 'payment';

    protected const string LEAF_PROPERTY = 'field';

    protected const string NOT_BLANK_ATTRIBUTE = "#[Assert\\NotBlank(groups: ['payments:create'])]";

    protected const string OPTIONAL_NOT_BLANK_ATTRIBUTE = "#[Assert\\NotBlank(groups: ['payments:create'], payload: ['source' => 'optional'])]";

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
        // The `All` wrapper collapses: Symfony forbids `Valid` inside a Composite constraint, and
        // `Valid` already cascades element-wise over an array.
        $this->assertSame(
            [static::VALID_CASCADE_ATTRIBUTE],
            $lifted['prices']['_validationAttributes'],
        );
        $this->assertSame(
            [static::NOT_BLANK_ATTRIBUTE],
            $lifted['prices']['items']['properties']['priceTypeName']['_validationAttributes'],
        );
    }

    public function testGivenAllWrappedObjectCollectionWithSiblingConstraintsWhenLiftingThenKeepsThemInTheWrapperBesideTheCascade(): void
    {
        // Arrange
        $nestedProperties = [
            'prices' => [
                'type' => 'array',
                'items' => $this->createObjectProperty(['priceTypeName' => ['type' => 'string']]),
            ],
        ];
        $priceConstraints = [
            [
                'All' => [
                    'constraints' => [
                        ['Collection' => ['fields' => ['priceTypeName' => ['NotBlank']]]],
                        'NotNull',
                    ],
                ],
            ],
        ];
        $validationSchema = $this->createPostSchema('payment', $this->createCollection(['prices' => $priceConstraints]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'payment', static::RESOURCE_NAME);

        // Assert
        // Only the cascade leaves the wrapper. `NotNull` still applies per element, so it stays inside
        // the `All` — collapsing the whole wrapper would silently drop declared validation.
        $this->assertSame(
            [
                "#[Assert\\All(constraints: [new Assert\\NotNull(groups: ['payments:create'])], groups: ['payments:create'])]",
                static::VALID_CASCADE_ATTRIBUTE,
            ],
            $lifted['prices']['_validationAttributes'],
        );
        $this->assertSame(
            [static::NOT_BLANK_ATTRIBUTE],
            $lifted['prices']['items']['properties']['priceTypeName']['_validationAttributes'],
        );
    }

    /**
     * @dataProvider validationSetupProvider
     *
     * @param array<string, mixed> $nestedProperties
     * @param array<mixed> $propertyConstraints
     * @param array<string, array{path: array<int, string>, attributes: array<int, string>|null}> $expectations
     */
    public function testGivenValidationSetupWhenLiftingThenEmitsExpectedAttributes(
        array $nestedProperties,
        array $propertyConstraints,
        array $expectations
    ): void {
        // Arrange
        $validationSchema = $this->createPostSchema(static::PARENT_PROPERTY, $propertyConstraints);

        // Act
        $lifted = $this->createLifter()->lift(
            $nestedProperties,
            $validationSchema,
            static::POST_OPERATIONS,
            static::PARENT_PROPERTY,
            static::RESOURCE_NAME,
        );

        // Assert
        foreach ($expectations as $description => $expectation) {
            $this->assertSame(
                $expectation['attributes'],
                $this->readValidationAttributes($lifted, $expectation['path']),
                $description,
            );
        }
    }

    /**
     * Crosses presence declaration (bare / `Optional` / `allowMissingFields` / both) against
     * constraint kind and property shape. The presence declaration must only ever add the
     * Optional payload tag — never rewrite or drop a constraint, because absence is decided at
     * runtime from the request body, not from the emitted attribute.
     *
     * @return array<string, array{array<string, mixed>, array<mixed>, array<string, array{path: array<int, string>, attributes: array<int, string>|null}>}>
     */
    public function validationSetupProvider(): array
    {
        return [
            'bare leaf stays hard-required' => $this->leafCase(
                ['NotBlank'],
                [static::NOT_BLANK_ATTRIBUTE],
            ),
            'optional-wrapped leaf is tagged, not relaxed' => $this->leafCase(
                [['Optional' => ['constraints' => ['NotBlank']]]],
                [static::OPTIONAL_NOT_BLANK_ATTRIBUTE],
            ),
            'allowMissingFields tags the leaf' => $this->leafCase(
                ['NotBlank'],
                [static::OPTIONAL_NOT_BLANK_ATTRIBUTE],
                ['allowMissingFields' => true],
            ),
            'allowMissingFields does not double-wrap an optional leaf' => $this->leafCase(
                [['Optional' => ['constraints' => ['NotBlank']]]],
                [static::OPTIONAL_NOT_BLANK_ATTRIBUTE],
                ['allowMissingFields' => true],
            ),
            'optional-wrapped NotNull survives' => $this->leafCase(
                [['Optional' => ['constraints' => ['NotNull']]]],
                ["#[Assert\\NotNull(groups: ['payments:create'], payload: ['source' => 'optional'])]"],
            ),
            'optional-wrapped constraint keeps its options' => $this->leafCase(
                [['Optional' => ['constraints' => [['NotBlank' => ['message' => 'Required.']]]]]],
                ["#[Assert\\NotBlank(message: 'Required.', groups: ['payments:create'], payload: ['source' => 'optional'])]"],
            ),
            'null-guarding constraint is tagged unchanged' => $this->leafCase(
                [['Optional' => ['constraints' => [['Date' => null]]]]],
                ["#[Assert\\Date(groups: ['payments:create'], payload: ['source' => 'optional'])]"],
            ),
            // Expression fires on null, so no codegen rewrite could have made it absent-tolerant.
            'non-null-guarding constraint is tagged unchanged' => $this->leafCase(
                [['Optional' => ['constraints' => [['Expression' => ['expression' => 'value > 0']]]]]],
                ["#[Assert\\Expression(expression: 'value > 0', groups: ['payments:create'], payload: ['source' => 'optional'])]"],
            ),
            'declared payload is merged, not duplicated' => $this->leafCase(
                [['Optional' => ['constraints' => [['NotBlank' => ['payload' => ['origin' => 'yml']]]]]]],
                ["#[Assert\\NotBlank(payload: ['origin' => 'yml', 'source' => 'optional'], groups: ['payments:create'])]"],
            ),
            'allowMissingFields tags every field of the collection' => [
                ['first' => ['type' => 'string'], 'second' => ['type' => 'string']],
                [[
        'Collection' => [
        'allowMissingFields' => true,
        'fields' => [
                    'first' => ['NotBlank'],
                    'second' => [['Length' => ['min' => 3]]],
                ]]]],
                [
                    'first is tagged' => ['path' => ['first'], 'attributes' => [static::OPTIONAL_NOT_BLANK_ATTRIBUTE]],
                    'second is tagged' => ['path' => ['second'], 'attributes' => ["#[Assert\\Length(min: 3, groups: ['payments:create'], payload: ['source' => 'optional'])]"]],
                ],
            ],
            'optional field beside required siblings in an object collection' => [
                [
            'shipments' => [
            'type' => 'array',
            'items' => $this->createObjectProperty([
                    'items' => ['type' => 'array'],
                    'idShipmentMethod' => ['type' => 'integer'],
                    'shippingAddress' => $this->createObjectProperty(['iso2Code' => ['type' => 'string']]),
                ])]],
                $this->createCollection([
                'shipments' => [[
                'All' => [
                'constraints' => $this->createCollection([
                    'items' => ['NotBlank'],
                    'idShipmentMethod' => ['NotBlank'],
                    'shippingAddress' => [['Optional' => ['constraints' => ['NotBlank']]]],
                ])]]]]),
                [
                    'optional field is tagged' => [
                        'path' => ['shipments', 'items', 'properties', 'shippingAddress'],
                        'attributes' => [static::OPTIONAL_NOT_BLANK_ATTRIBUTE],
                    ],
                    'required sibling untouched' => [
                        'path' => ['shipments', 'items', 'properties', 'items'],
                        'attributes' => [static::NOT_BLANK_ATTRIBUTE],
                    ],
                    'second required sibling untouched' => [
                        'path' => ['shipments', 'items', 'properties', 'idShipmentMethod'],
                        'attributes' => [static::NOT_BLANK_ATTRIBUTE],
                    ],
                ],
            ],
            'nested collection becomes a cascade and lifts its leaf' => [
                ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])],
                $this->createCollection(['customer' => $this->createCollection(['email' => ['NotBlank']])]),
                [
                    'parent cascades' => ['path' => ['customer'], 'attributes' => [static::VALID_CASCADE_ATTRIBUTE]],
                    'leaf is lifted' => ['path' => ['customer', 'properties', 'email'], 'attributes' => [static::NOT_BLANK_ATTRIBUTE]],
                ],
            ],
            'optional-wrapped nested collection cascades with the tag' => [
                ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])],
                $this->createCollection(['customer' => [['Optional' => ['constraints' => $this->createCollection(['email' => ['NotBlank']])]]]]),
                [
                    'cascade carries the tag' => [
                        'path' => ['customer'],
                        'attributes' => ["#[Assert\\Valid(groups: ['payments:create'], payload: ['source' => 'optional'])]"],
                    ],
                    'leaf stays hard-required' => ['path' => ['customer', 'properties', 'email'], 'attributes' => [static::NOT_BLANK_ATTRIBUTE]],
                ],
            ],
            'required-wrapped nested collection collapses to a bare cascade' => [
                ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])],
                $this->createCollection(['customer' => [['Required' => ['constraints' => $this->createCollection(['email' => ['NotBlank']])]]]]),
                [
                    'cascade is bare' => ['path' => ['customer'], 'attributes' => [static::VALID_CASCADE_ATTRIBUTE]],
                    'leaf is lifted' => ['path' => ['customer', 'properties', 'email'], 'attributes' => [static::NOT_BLANK_ATTRIBUTE]],
                ],
            ],
            'untyped array keeps its collection constraint' => [
                ['prices' => ['type' => 'array']],
                $this->createCollection(['prices' => [['All' => ['constraints' => $this->createCollection(['priceTypeName' => ['NotBlank']])]]]]),
                [
                    'collection is left in place' => [
                        'path' => ['prices'],
                        'attributes' => ["#[Assert\\All(constraints: [new Assert\\Collection(fields: ['priceTypeName' => [new Assert\\NotBlank(groups: ['payments:create'])]], groups: ['payments:create'])], groups: ['payments:create'])]"],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $fieldConstraints
     * @param array<int, string> $expectedAttributes
     * @param array<string, mixed> $collectionOptions
     *
     * @return array{array<string, mixed>, array<mixed>, array<string, array{path: array<int, string>, attributes: array<int, string>|null}>}
     */
    protected function leafCase(array $fieldConstraints, array $expectedAttributes, array $collectionOptions = []): array
    {
        return [
            [static::LEAF_PROPERTY => ['type' => 'string']],
            [['Collection' => $collectionOptions + ['fields' => [static::LEAF_PROPERTY => $fieldConstraints]]]],
            ['leaf attributes' => ['path' => [static::LEAF_PROPERTY], 'attributes' => $expectedAttributes]],
        ];
    }

    /**
     * @param array<string, mixed> $lifted
     * @param array<int, string> $path
     *
     * @return array<int, string>|null
     */
    protected function readValidationAttributes(array $lifted, array $path): ?array
    {
        $node = $lifted;

        foreach ($path as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return is_array($node) && isset($node['_validationAttributes']) ? $node['_validationAttributes'] : null;
    }

    public function testGivenRequiredWrappedNestedCollectionWhenLiftingThenCollapsesIntoABareCascade(): void
    {
        // Arrange
        $nestedProperties = ['customer' => $this->createObjectProperty(['email' => ['type' => 'string']])];
        $customerConstraints = [
            ['Required' => ['constraints' => $this->createCollection(['email' => ['NotBlank']])]],
        ];
        $validationSchema = $this->createPostSchema('quote', $this->createCollection(['customer' => $customerConstraints]));

        // Act
        $lifted = $this->createLifter()->lift($nestedProperties, $validationSchema, static::POST_OPERATIONS, 'quote', static::RESOURCE_NAME);

        // Assert
        // `Required` is a Composite like `All`, so Symfony would reject a `Valid` left nested inside it.
        // Unlike `Optional`, nothing downstream unwraps it, so the lifter has to collapse it here.
        $this->assertSame([static::VALID_CASCADE_ATTRIBUTE], $lifted['customer']['_validationAttributes']);
        $this->assertSame([static::NOT_BLANK_ATTRIBUTE], $lifted['customer']['properties']['email']['_validationAttributes']);
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
