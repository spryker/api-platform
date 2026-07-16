<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use ReflectionMethod;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Generator\ClassGenerator;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group ClassGeneratorTest
 * Add your own group annotations below this line
 */
class ClassGeneratorTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenSchemaWhenGeneratingThenReturnsPhpClass(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class CustomerStorefrontResource', $result);
    }

    public function testGivenApiTypeWhenGeneratingThenIncludesApiTypeInNamespace(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('namespace Generated\Api\Storefront;', $result);
    }

    public function testGivenPropertiesWhenGeneratingThenTransformsTypes(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'properties' => ['id' => ['type' => 'integer']]];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('public ?int $id = null;', $result);
    }

    public function testGivenBackendApiTypeWhenGeneratingThenGeneratesCorrectClassName(): void
    {
        // Arrange
        $schema = ['name' => 'Order', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Backend');

        // Assert
        $this->assertStringContainsString('class OrderBackendResource', $result);
    }

    public function testGivenResourceNameWithSpacesWhenGeneratingThenRemovesWhitespaceFromClassName(): void
    {
        // Arrange
        $schema = ['name' => 'Access Tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensStorefrontResource', $result);
        $this->assertStringNotContainsString('class Access TokensStorefrontResource', $result);
    }

    // Resource Name Normalization Integration Tests

    public function testGivenKebabCaseNameWhenGeneratingThenConvertsToPascalCase(): void
    {
        // Arrange
        $schema = ['name' => 'access-tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensStorefrontResource', $result);
    }

    public function testGivenSnakeCaseNameWhenGeneratingThenConvertsToPascalCase(): void
    {
        // Arrange
        $schema = ['name' => 'access_tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensStorefrontResource', $result);
    }

    public function testGivenDotSeparatedNameWhenGeneratingThenConvertsToPascalCase(): void
    {
        // Arrange
        $schema = ['name' => 'access.tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensStorefrontResource', $result);
    }

    public function testGivenMixedSeparatorsWhenGeneratingThenNormalizesAll(): void
    {
        // Arrange
        $schema = ['name' => 'access_token-system.v2', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokenSystemV2StorefrontResource', $result);
    }

    public function testGivenNameWithVersionNumberWhenGeneratingThenPreservesNumbers(): void
    {
        // Arrange
        $schema = ['name' => 'api-v2-tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class ApiV2TokensStorefrontResource', $result);
    }

    public function testGivenNameWithSpecialCharsWhenGeneratingThenRemovesInvalidCharacters(): void
    {
        // Arrange
        $schema = ['name' => 'access@tokens#v2', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensV2StorefrontResource', $result);
    }

    public function testGivenEmptyStringWhenGeneratingThenThrowsException(): void
    {
        // Arrange
        $schema = ['name' => '', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name cannot be empty');

        // Act
        $generator->generate($schema, 'Storefront');
    }

    public function testGivenNameStartingWithNumberWhenGeneratingThenThrowsException(): void
    {
        // Arrange
        $schema = ['name' => '2fa-tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name cannot start with a number');

        // Act
        $generator->generate($schema, 'Storefront');
    }

    public function testGivenComplexMultiWordNameWhenGeneratingThenNormalizesCorrectly(): void
    {
        // Arrange
        $schema = ['name' => 'user-profile-data-v3', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'backend');

        // Assert
        $this->assertStringContainsString('class UserProfileDataV3BackendResource', $result);
    }

    public function testGivenFqcnConstraintWhenGeneratingThenAddsUseStatementAndAttribute(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'properties' => ['email' => ['type' => 'string']],
            'validation' => [
                'post' => [
                    'email' => [
                        'NotBlank',
                        '\Spryker\Zed\Customer\Business\Validator\UniqueEmail',
                    ],
                ],
            ],
            'operations' => ['post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('post');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act
        $result = $generator->generate($schema, 'Backend');

        // Assert
        $this->assertStringContainsString('use Spryker\Zed\Customer\Business\Validator\UniqueEmail;', $result);
        $this->assertStringContainsString("#[UniqueEmail(groups: ['post'])]", $result);
    }

    public function testGivenFqcnConstraintWithoutLeadingBackslashWhenGeneratingThenNormalizesCorrectly(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'properties' => ['email' => ['type' => 'string']],
            'validation' => [
                'post' => [
                    'email' => [
                        'Spryker\Zed\Customer\Business\Validator\UniqueEmail',
                    ],
                ],
            ],
            'operations' => ['Post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('post');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act
        $result = $generator->generate($schema, 'Backend');

        // Assert
        $this->assertStringContainsString('use Spryker\Zed\Customer\Business\Validator\UniqueEmail;', $result);
        $this->assertStringContainsString("#[UniqueEmail(groups: ['post'])]", $result);
    }

    public function testGivenSprykerConstraintCollisionWhenGeneratingThenUsesModuleAlias(): void
    {
        // Arrange
        $schema = [
            'name' => 'Product',
            'properties' => ['email' => ['type' => 'string']],
            'validation' => [
                'post' => [
                    'email' => [
                        '\Spryker\Zed\Customer\Business\Validator\Email',
                        '\Spryker\Glue\Product\Business\Validator\Email',
                    ],
                ],
            ],
            'operations' => ['Post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('post');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act
        $result = $generator->generate($schema, 'Backend');

        // Assert
        $this->assertStringContainsString('use Spryker\Zed\Customer\Business\Validator\Email as SprykerCustomerEmail;', $result);
        $this->assertStringContainsString('use Spryker\Glue\Product\Business\Validator\Email as SprykerProductEmail;', $result);
        $this->assertStringContainsString("#[SprykerCustomerEmail(groups: ['post'])]", $result);
        $this->assertStringContainsString("#[SprykerProductEmail(groups: ['post'])]", $result);
    }

    public function testGivenMultiVendorConstraintCollisionWhenGeneratingThenUsesVendorAlias(): void
    {
        // Arrange
        $schema = [
            'name' => 'Product',
            'properties' => ['value' => ['type' => 'string']],
            'validation' => [
                'post' => [
                    'value' => [
                        '\Spryker\Zed\Validator\NotNull',
                        '\Acme\Validation\NotNull',
                    ],
                ],
            ],
            'operations' => ['Post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('post');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act
        $result = $generator->generate($schema, 'Backend');

        // Assert
        $this->assertStringContainsString('use Spryker\Zed\Validator\NotNull as SprykerNotNull;', $result);
        $this->assertStringContainsString('use Acme\Validation\NotNull as AcmeNotNull;', $result);
        $this->assertStringContainsString("#[SprykerNotNull(groups: ['post'])]", $result);
        $this->assertStringContainsString("#[AcmeNotNull(groups: ['post'])]", $result);
    }

    public function testGivenFqcnConstraintWithOptionsWhenGeneratingThenFormatsOptionsCorrectly(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'properties' => ['email' => ['type' => 'string']],
            'validation' => [
                'post' => [
                    'email' => [
                        [
                            '\Spryker\Zed\Customer\Business\Validator\UniqueEmail' => [
                                'message' => 'Email already exists',
                            ],
                        ],
                    ],
                ],
            ],
            'operations' => ['Post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('post');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act
        $result = $generator->generate($schema, 'Backend');

        // Assert
        $this->assertStringContainsString('use Spryker\Zed\Customer\Business\Validator\UniqueEmail;', $result);
        $this->assertStringContainsString("#[UniqueEmail(message: 'Email already exists', groups: ['post'])]", $result);
    }

    public function testGivenSymfonyAndFqcnConstraintsTogetherWhenGeneratingThenIncludesBothUseStatements(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'properties' => ['email' => ['type' => 'string']],
            'validation' => [
                'post' => [
                    'email' => [
                        'NotBlank',
                        '\Spryker\Zed\Customer\Business\Validator\UniqueEmail',
                    ],
                ],
            ],
            'operations' => ['Post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('post');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act
        $result = $generator->generate($schema, 'Backend');

        // Assert
        $this->assertStringContainsString('use Symfony\Component\Validator\Constraints as Assert;', $result);
        $this->assertStringContainsString('use Spryker\Zed\Customer\Business\Validator\UniqueEmail;', $result);
        $this->assertStringContainsString("#[Assert\\NotBlank(groups: ['post'])]", $result);
        $this->assertStringContainsString("#[UniqueEmail(groups: ['post'])]", $result);
    }

    public function testGivenObjectPropertyWithNestedPropertiesWhenGeneratingThenTypesPropertyToNestedObjectClass(): void
    {
        // Arrange
        $schema = [
            'name' => 'Carts',
            'properties' => [
                'totals' => [
                    'type' => 'object',
                    'properties' => [
                        'grandTotal' => ['type' => 'integer', 'description' => 'Final total'],
                    ],
                ],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString(
            'public ?CartsTotalsStorefrontObject $totals = null;',
            $result,
            'The parent property is typed to the per-resource companion class `{Resource}{Field}{ApiType}Object`.',
        );
    }

    public function testGivenObjectPropertyWhenGeneratingAllThenReturnsNestedObjectClass(): void
    {
        // Arrange
        $schema = [
            'name' => 'Carts',
            'properties' => [
                'totals' => [
                    'type' => 'object',
                    'properties' => [
                        'grandTotal' => ['type' => 'integer', 'description' => 'Final total'],
                    ],
                ],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generateAll($schema, 'Storefront');

        // Assert
        $this->assertArrayHasKey(
            'CartsTotalsStorefrontObject',
            $result->getNestedObjectClasses(),
            'The companion class is keyed by its full class name `{Resource}{Field}{ApiType}Object`.',
        );
        $this->assertStringContainsString('public ?int $grandTotal = null;', $result->getNestedObjectClasses()['CartsTotalsStorefrontObject']);
        $this->assertStringContainsString('public ?CartsTotalsStorefrontObject $totals = null;', $result->getMainClassCode());
        $this->assertStringContainsString(
            'CartsTotalsStorefrontObject::fromArray($data[\'totals\'])',
            $result->getMainClassCode(),
            'fromArray() must hydrate the nested object via its own factory — assigning the raw sub-array to the typed property would be a TypeError at runtime.',
        );
        $this->assertStringContainsString(
            '\'totals\' => $this->totals?->toArray(),',
            $result->getMainClassCode(),
            'toArray() must serialize the nested object back to a plain array.',
        );
        $this->assertStringContainsString(
            'use Generated\Api\Storefront\Carts\CartsTotalsStorefrontObject;',
            $result->getMainClassCode(),
            'The resource must import the helper\'s relocated FQCN so the short name resolves.',
        );
    }

    public function testGivenObjectCollectionPropertyWhenGeneratingAllThenEmitsPluralizedCompanionAndTypesParentToArrayWithNoUseStatement(): void
    {
        // Arrange — a collection of typed objects: `type: array` whose `items` are a typed object.
        // The companion is named after the pluralized field segment and the parent property is `array`.
        // IMPORTANT: collection properties are typed `array` on the resource — no short-name
        // class reference, so no `use` for the element class must be emitted.
        $schema = [
            'name' => 'CartItems',
            'properties' => [
                'calculations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generateAll($schema, 'Storefront');

        // Assert
        $this->assertArrayHasKey(
            'CartItemsCalculationsStorefrontObject',
            $result->getNestedObjectClasses(),
            '`calculations` pluralizes to `Calculations` (already plural), so the companion is CartItemsCalculationsStorefrontObject; the parent property itself is typed `array`.',
        );
        $this->assertStringContainsString('public ?int $amount = null;', $result->getNestedObjectClasses()['CartItemsCalculationsStorefrontObject']);
        $this->assertStringContainsString('public array $calculations = [];', $result->getMainClassCode());
        $this->assertStringNotContainsString(
            'use Generated\Api\Storefront\CartItems\CartItemsCalculationsStorefrontObject;',
            $result->getMainClassCode(),
            'Collection element class must NOT appear in a `use` on the resource — the property is typed `array`, so the class is never referenced by short name.',
        );
    }

    public function testGivenNestedObjectPropertyWithCollectionValidationWhenGeneratingThenReplacesCollectionWithValidCascade(): void
    {
        // Arrange — a denormalized value object would fail an array-shaped Collection, so the parent
        // property must carry only an #[Assert\Valid] cascade (scoped to the same operation groups);
        // the field-level Collection validation is lifted onto the companion value object instead.
        $schema = [
            'name' => 'Checkout',
            'properties' => [
                'billingAddress' => [
                    'type' => 'object',
                    'properties' => [
                        'firstName' => ['type' => 'string'],
                    ],
                ],
            ],
            'validation' => [
                'post' => [
                    'billingAddress' => [
                        [
                            'Collection' => [
                                'fields' => [
                                    'firstName' => ['NotBlank'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'operations' => ['Post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('checkout:create');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('public ?CheckoutBillingAddressStorefrontObject $billingAddress = null;', $result);
        $this->assertStringContainsString("#[Assert\\Valid(groups: ['checkout:create'])]", $result);
        $this->assertStringNotContainsString('#[Assert\\Collection(', $result);
    }

    public function testGivenObjectPropertyWithoutNestedPropertiesWhenGeneratingThenTypesToObjectAndEmitsNoCompanion(): void
    {
        // Arrange — a bare `type: object` with no `properties` is not a generated nested object,
        // so it maps to the plain `object` PHP type and emits no companion class.
        $schema = [
            'name' => 'Orders',
            'properties' => [
                'totals' => [
                    'type' => 'object',
                ],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generateAll($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('public ?object $totals = null;', $result->getMainClassCode());
        $this->assertSame([], $result->getNestedObjectClasses());
    }

    public function testGivenNoMatchingOperationGroupsWhenBuildingValidCascadeThenReturnsBareValid(): void
    {
        // The bare-#[Assert\Valid] branch is defensive: it is unreachable through generate() (the cascade
        // only fires when a Collection attribute exists, which requires a matching operation, which in
        // turn yields a non-empty group set), so the contract is pinned by invoking the protected method
        // directly with operations that do not match the validation schema.
        $generator = $this->createClassGenerator();
        $method = new ReflectionMethod(ClassGenerator::class, 'buildValidCascadeAttribute');
        $method->setAccessible(true);

        // Act — operations declare only Get, but the validation lives under Post → no group resolves.
        $result = $method->invoke(
            $generator,
            ['post' => ['billingAddress' => [['Collection' => ['fields' => []]]]]],
            ['Get' => []],
            'billingAddress',
            'Checkout',
        );

        // Assert
        $this->assertSame(['#[Assert\\Valid]'], $result);
    }

    protected function createValidationGroupMapper(string $group): ValidationGroupMapperInterface
    {
        $validationGroupMapper = $this->makeEmpty(ValidationGroupMapperInterface::class, [
            'mapOperationToGroup' => $group,
        ]);

        return $validationGroupMapper;
    }

    protected function createClassGenerator(): ClassGenerator
    {
        $validationGroupMapper = $this->makeEmpty(ValidationGroupMapperInterface::class);

        return $this->createClassGeneratorWithMapper($validationGroupMapper);
    }

    protected function createClassGeneratorWithMapper(ValidationGroupMapperInterface $validationGroupMapper): ClassGenerator
    {
        $this->tester->getContainer()->set(ValidationGroupMapperInterface::class, $validationGroupMapper);

        return $this->tester->getContainer()->get(ClassGenerator::class);
    }

    public function testGivenResourceTypePropertyWhenGeneratingThenUsesResourceClassAsPhpType(): void
    {
        // Arrange
        $schema = [
            'name' => 'CustomerAddress',
            'shortName' => 'customer-address',
            'properties' => [
                'customer' => [
                    'type' => 'CustomersStorefrontResource',
                    'writable' => false,
                    'readable' => true,
                    'description' => 'The customer who owns this address',
                ],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('public ?CustomersStorefrontResource $customer = null;', $result);
        $this->assertStringContainsString('public function setCustomer(?CustomersStorefrontResource $customer): self', $result);
        $this->assertStringContainsString('public function getCustomer(): ?CustomersStorefrontResource', $result);
    }

    public function testGivenKnownCanonicalObjectNameWhenGeneratingThenTypesPropertyToCanonicalClassAndEmitsNoCompanion(): void
    {
        // Arrange — `billingAddress` carries objectName: Address and Address is in the known set.
        // The property must be typed `?Address` and NO per-resource companion emitted.
        $schema = [
            'name' => 'Checkout',
            'apiType' => 'Storefront',
            'properties' => [
                'billingAddress' => [
                    'type' => 'object',
                    'objectName' => 'Address',
                    'properties' => ['zipCode' => ['type' => 'string']],
                ],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generateAll($schema, 'Storefront', ['Address' => true]);

        // Assert
        $code = $result->getMainClassCode();
        $this->assertStringContainsString('?Address $billingAddress', $code);
        $this->assertArrayNotHasKey('CheckoutBillingAddressStorefrontObject', $result->getNestedObjectClasses());
        // The canonical class lives at Generated\Api\{ApiType}\{Name} — NO resource sub-namespace.
        $this->assertStringContainsString('use Generated\\Api\\Storefront\\Address;', $code);
        // Must NOT import at the per-resource sub-namespace (the bug this test pins).
        $this->assertStringNotContainsString('use Generated\\Api\\Storefront\\Checkout\\Address;', $code);
    }

    public function testGivenTwoPropertiesWithSameCanonicalObjectNameWhenGeneratingThenEmitsUseStatementOnce(): void
    {
        // Arrange — both billingAddress and shippingAddress carry objectName: Address.
        // The canonical `use` must appear exactly once (deduplicated).
        $schema = [
            'name' => 'Checkout',
            'apiType' => 'Storefront',
            'properties' => [
                'billingAddress' => [
                    'type' => 'object',
                    'objectName' => 'Address',
                    'properties' => ['zipCode' => ['type' => 'string']],
                ],
                'shippingAddress' => [
                    'type' => 'object',
                    'objectName' => 'Address',
                    'properties' => ['zipCode' => ['type' => 'string']],
                ],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generateAll($schema, 'Storefront', ['Address' => true]);

        // Assert — exactly one canonical use statement, not two.
        $code = $result->getMainClassCode();
        $this->assertSame(1, substr_count($code, 'use Generated\\Api\\Storefront\\Address;'));
        $this->assertStringNotContainsString('use Generated\\Api\\Storefront\\Checkout\\Address;', $code);
    }

    public function testGivenUnknownCanonicalObjectNameWhenGeneratingThenEmitsPerResourceCompanionAsUsual(): void
    {
        // Arrange — same schema but empty known-canonical set → BC: per-resource companion must be emitted.
        $schema = [
            'name' => 'Checkout',
            'apiType' => 'Storefront',
            'properties' => [
                'billingAddress' => [
                    'type' => 'object',
                    'objectName' => 'Address',
                    'properties' => ['zipCode' => ['type' => 'string']],
                ],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generateAll($schema, 'Storefront', []);

        // Assert — per-resource companion IS emitted and the property types to it.
        $this->assertArrayHasKey('CheckoutBillingAddressStorefrontObject', $result->getNestedObjectClasses());
        $this->assertStringContainsString('?CheckoutBillingAddressStorefrontObject $billingAddress', $result->getMainClassCode());
    }

    public function testGivenCanonicalReferenceSiteWithCollectionValidationWhenGeneratingThenReplacesCollectionWithValidCascade(): void
    {
        // Arrange — a reference-only canonical site: `billingAddress` carries objectName: Address and
        // NO inline `properties`, and Address is in the known set, so the property is typed `?Address`.
        // Its array-shaped Collection constraint would reject that object, so the parent property must
        // carry only an #[Assert\Valid] cascade (scoped to the operation group); the field validation
        // lives on the canonical class. This is the locked Plan Decision 3 behaviour.
        $schema = [
            'name' => 'Checkout',
            'apiType' => 'Storefront',
            'properties' => [
                'billingAddress' => [
                    'type' => 'object',
                    'objectName' => 'Address',
                ],
            ],
            'validation' => [
                'post' => [
                    'billingAddress' => [
                        [
                            'Collection' => [
                                'fields' => [
                                    'zipCode' => ['NotBlank'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'operations' => ['Post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('checkout:create');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act
        $result = $generator->generate($schema, 'Storefront', ['Address' => true]);

        // Assert — typed to the shared canonical class, Collection superseded by the Valid cascade.
        $this->assertStringContainsString('public ?Address $billingAddress = null;', $result);
        $this->assertStringContainsString("#[Assert\\Valid(groups: ['checkout:create'])]", $result);
        $this->assertStringNotContainsString('#[Assert\\Collection(', $result);
    }

    public function testGivenCanonicalReferenceSiteWithCollectionValidationButEmptyKnownSetWhenGeneratingThenKeepsCollection(): void
    {
        // Arrange — BC counterpart: same reference-only site but the known-canonical set is EMPTY
        // (e.g. the registry pre-pass did not resolve Address), so `objectName` is dormant. The
        // property is NOT typed to a canonical class and the cascade must NOT fire — today's behaviour
        // (the site-level Collection constraint is emitted unchanged) is preserved.
        $schema = [
            'name' => 'Checkout',
            'apiType' => 'Storefront',
            'properties' => [
                'billingAddress' => [
                    'type' => 'object',
                    'objectName' => 'Address',
                ],
            ],
            'validation' => [
                'post' => [
                    'billingAddress' => [
                        [
                            'Collection' => [
                                'fields' => [
                                    'zipCode' => ['NotBlank'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'operations' => ['Post' => []],
        ];
        $validationGroupMapper = $this->createValidationGroupMapper('checkout:create');
        $generator = $this->createClassGeneratorWithMapper($validationGroupMapper);

        // Act — empty known set.
        $result = $generator->generate($schema, 'Storefront', []);

        // Assert — no canonical typing, no cascade; the array-shaped Collection constraint stays.
        $this->assertStringNotContainsString('?Address $billingAddress', $result);
        $this->assertStringContainsString('#[Assert\\Collection(', $result);
        $this->assertStringNotContainsString('#[Assert\\Valid', $result);
    }

    public function testGivenStandardPhpTypePropertyWhenGeneratingThenUsesMappedPhpType(): void
    {
        // Arrange
        $schema = [
            'name' => 'Test',
            'shortName' => 'test',
            'properties' => [
                'name' => ['type' => 'string'],
                'count' => ['type' => 'integer'],
                'price' => ['type' => 'number'],
                'active' => ['type' => 'boolean'],
                'tags' => ['type' => 'array'],
            ],
        ];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('public ?string $name = null;', $result);
        $this->assertStringContainsString('public ?int $count = null;', $result);
        $this->assertStringContainsString('public ?float $price = null;', $result);
        $this->assertStringContainsString('public ?bool $active = null;', $result);
        $this->assertStringContainsString('public array $tags = [];', $result);
    }
}
