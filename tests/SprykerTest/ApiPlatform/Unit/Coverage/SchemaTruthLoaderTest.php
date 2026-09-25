<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Generated\Api\Storefront\WishlistItemsStorefrontResource;
use Generated\Api\Storefront\WishlistsStorefrontResource;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute;
use Spryker\ApiPlatform\Contract\Coverage\TruthSet;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\CollectionOnlyAttributeFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\DeclaredResponsesFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorOnlyCollectionFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\HiddenDeclaredResponsesFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ResponseAttributesFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\RestorePasswordShapeFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SyntheticIdentifierFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\UndeclaredResponsesFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\UnservableItemGetFixtureResource;

/**
 * Loads the truth straight from the generated Wishlist resource classes — the reflection,
 * default-uriTemplate derivation, `{._format}` normalisation, constraint→rule mapping and the
 * providerless item-GET rules — non-servable versus declared internal — are all exercised end to
 * end against real generated metadata.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group SchemaTruthLoaderTest
 * Add your own group annotations below this line
 */
class SchemaTruthLoaderTest extends Unit
{
    public function testGivenTheWishlistResourcesWhenLoadingThenEveryServableOperationIsResolvedToItsCanonicalUriTemplate(): void
    {
        // Arrange
        $truthSet = $this->loadWishlistTruth();

        // Act — the dispatch key, not the full key: which statuses each operation declares is the
        // schema's business and is asserted against fixtures below, so editing a wishlist response
        // must not churn this test.
        $dispatchKeys = array_values(array_unique(array_map(
            static fn ($operation): string => $operation->dispatchKey(),
            $truthSet->servableOperations,
        )));

        // Assert — the collection and item defaults are derived, a nested route keeps its parent
        // segment, and no key carries the `{._format}` suffix the metadata appends. Pinning the
        // whole operation list instead would make every new wishlist operation this loader's
        // business.
        $this->assertContains('GET /wishlists', $dispatchKeys);
        $this->assertContains('GET /wishlists/{uuid}', $dispatchKeys);
        $this->assertContains('POST /wishlists/{wishlistUuid}/wishlist-items', $dispatchKeys);
        $this->assertContains('DELETE /wishlists/{wishlistUuid}/wishlist-items/{uuid}', $dispatchKeys);
        $this->assertSame([], array_values(array_filter(
            $dispatchKeys,
            static fn (string $dispatchKey): bool => str_contains($dispatchKey, '_format'),
        )));
    }

    public function testGivenTheWishlistResourcesWhenLoadingThenEachDeclaredErrorStatusBecomesItsOwnItem(): void
    {
        // Arrange
        $truthSet = $this->loadWishlistTruth();

        // Act
        $collectionKeys = array_values(array_filter(
            $this->operationKeys($truthSet->servableOperations),
            static fn (string $key): bool => str_starts_with($key, 'GET /wishlists '),
        ));

        // Assert — one item per declared error status of the collection GET, and no synthesised 404:
        // a collection cannot answer not-found, and the schema does not claim it does.
        sort($collectionKeys);
        $this->assertSame(['GET /wishlists 401', 'GET /wishlists 403'], $collectionKeys);
    }

    public function testGivenOperationDeclares403And404WhenLoadingThenOneErrorItemPerDeclaredStatus(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([DeclaredResponsesFixtureResource::class]);

        // Assert — the declared 403 stands on its own; nothing remaps it to a 404.
        $servableKeys = $this->operationKeys($truthSet->servableOperations);
        sort($servableKeys);
        $this->assertSame(
            [
                'PATCH /customer-password/{customerReference}',
                'PATCH /customer-password/{customerReference} 403',
                'PATCH /customer-password/{customerReference} 404',
            ],
            $servableKeys,
        );
    }

    public function testGivenOperationDeclaresOnly204And422WhenLoadingThenNo404ItemIsDerived(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([RestorePasswordShapeFixtureResource::class]);

        // Assert
        $servableKeys = $this->operationKeys($truthSet->servableOperations);
        sort($servableKeys);
        $this->assertSame(
            [
                'PATCH /customer-restore-password/{restorePasswordKey}',
                'PATCH /customer-restore-password/{restorePasswordKey} 422',
            ],
            $servableKeys,
        );
    }

    public function testGivenOperationDeclaresNoResponsesWhenLoadingThenItIsCollectedAsUndeclaredAndNoErrorItemsDerived(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([UndeclaredResponsesFixtureResource::class]);

        // Assert — the success item survives so existing annotations do not go stale, but the
        // operation is reported as a schema defect instead of growing synthesised error items.
        $this->assertSame(['PATCH /undeclared/{uuid}'], $this->operationKeys($truthSet->servableOperations));
        $this->assertSame(['PATCH /undeclared/{uuid}'], $this->operationKeys($truthSet->undeclaredResponseOperations));
        $this->assertSame([], $truthSet->declaredResponses);
    }

    public function testGivenDeclaredResponsesWhenLoadingThenDeclaredResponsesMapCarriesAllStatusesPerDispatchKey(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([DeclaredResponsesFixtureResource::class]);

        // Assert
        $this->assertSame(
            ['PATCH /customer-password/{customerReference}' => [204, 403, 404]],
            $truthSet->declaredResponses,
        );
        $this->assertSame([], $truthSet->undeclaredResponseOperations);
    }

    public function testGivenAProviderlessItemGetWhenLoadingThenItIsClassifiedNonServable(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([UnservableItemGetFixtureResource::class]);

        // Assert
        $this->assertSame(['GET /unservable/{uuid}'], $this->operationKeys($truthSet->nonServableOperations));
    }

    public function testGivenAnOperationDeclaringNoSuccessResponseWhenLoadingThenItDemandsNoResponseAttributes(): void
    {
        // Arrange — the bare collection URL of a resource addressed only by id answers 400 and never
        // a resource body, so there is nothing for a test to assert on.
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([ErrorOnlyCollectionFixtureResource::class]);

        // Assert
        $dispatchKeys = array_values(array_unique(array_map(
            static fn ($responseAttribute): string => $responseAttribute->dispatchKey,
            $truthSet->responseAttributes,
        )));
        $this->assertSame(['GET /error-only/{uuid}'], $dispatchKeys);
    }

    public function testGivenShapeScopedAttributesWhenLoadingThenEachOperationDemandsItsOwnShape(): void
    {
        // Arrange — pagination metadata is the collection response's and a create can never carry
        // it, while a detail field belongs to the item response and a list that answers a summary
        // never has it.
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([CollectionOnlyAttributeFixtureResource::class]);

        // Assert
        $byOperation = [];

        foreach ($truthSet->responseAttributes as $responseAttribute) {
            $byOperation[$responseAttribute->dispatchKey][] = $responseAttribute->path;
        }

        $this->assertSame(['name', 'numFound'], $byOperation['GET /paginated']);
        $this->assertSame(['name', 'detail'], $byOperation['POST /paginated']);
    }

    public function testGivenASyntheticIdentifierWhenAskingWhetherTheResourceDeclaresOneThenItDoesNot(): void
    {
        // Arrange — the envelope check keys on this: a singleton's `data.id` is null on purpose, so
        // treating its IRI anchor as a real identifier would demand a change of contract.
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act & Assert
        $this->assertFalse($loader->declaresIdentifier(SyntheticIdentifierFixtureResource::class));
        $this->assertTrue($loader->declaresIdentifier(WishlistsStorefrontResource::class));
    }

    public function testGivenAHiddenOperationWhenLoadingThenItsDeclaredStatusesAreReadFromExtraProperties(): void
    {
        // Arrange — `openapi: false` leaves no Operation object to read the responses back from, so
        // the only surviving record of the contract is the extra property the generator emits.
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([HiddenDeclaredResponsesFixtureResource::class]);

        // Assert
        $this->assertSame(['GET /hidden' => [400]], $truthSet->declaredResponses);
        $this->assertSame([], $truthSet->undeclaredResponseOperations);
    }

    public function testGivenAProviderlessItemGetMarkedInternalWhenLoadingThenItIsClassifiedInternalInstead(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([UnservableItemGetFixtureResource::class]);

        // Assert — an internal operation must not reach the non-servable warning, or declaring one
        // deliberately would be indistinguishable from forgetting a provider. The classification
        // keys on the `internal` flag, not on the NotFoundAction controller that implements it.
        $this->assertSame(['GET /unservable/{uuid}/internal'], $this->operationKeys($truthSet->internalOperations));
        $this->assertNotContains('GET /unservable/{uuid}/internal', $this->operationKeys($truthSet->nonServableOperations));
    }

    public function testGivenTheWishlistResourcesWhenLoadingThenEachValidationRuleIsRequiredPerActiveInputOperation(): void
    {
        // Arrange
        $truthSet = $this->loadWishlistTruth();

        // Act
        $validationKeys = $this->validationKeys($truthSet->validationConstraints);

        // Assert — the wishlist name rules carry both the create and the update group, so POST and
        // PATCH each demand their own test. The cascaded rules are asserted below.
        $topLevelKeys = array_values(array_filter(
            $validationKeys,
            static fn (string $key): bool => !str_contains($key, 'productConfigurationInstance.'),
        ));
        sort($topLevelKeys);
        $this->assertSame(
            [
                'wishlist-items.sku.NotBlank on POST /wishlists/{wishlistUuid}/wishlist-items',
                'wishlists.name.Length.max on PATCH /wishlists/{uuid}',
                'wishlists.name.Length.max on POST /wishlists',
                'wishlists.name.NotBlank on PATCH /wishlists/{uuid}',
                'wishlists.name.NotBlank on POST /wishlists',
            ],
            $topLevelKeys,
        );
    }

    public function testGivenAValidCascadeOntoAValueObjectWhenLoadingThenItsOwnRulesEnterTheTruthSet(): void
    {
        // Arrange — `productConfigurationInstance` carries `Assert\Valid` and denormalizes into
        // WishlistItemsProductConfigurationInstanceStorefrontObject, whose leaves hold the rules.
        $truthSet = $this->loadWishlistTruth();

        // Act
        $leafKeys = $this->nestedValidationKeysFor($truthSet, 'POST /wishlists/{wishlistUuid}/wishlist-items', 'productConfigurationInstance.', true);

        // Assert
        $this->assertSame(
            [
                'wishlist-items.productConfigurationInstance.availableQuantity.NotBlank',
                'wishlist-items.productConfigurationInstance.availableQuantity.Type',
                'wishlist-items.productConfigurationInstance.configuration.NotBlank',
                'wishlist-items.productConfigurationInstance.configuratorKey.NotBlank',
                'wishlist-items.productConfigurationInstance.displayData.NotBlank',
                'wishlist-items.productConfigurationInstance.isComplete.NotNull',
                'wishlist-items.productConfigurationInstance.isComplete.Type',
            ],
            $leafKeys,
        );
    }

    public function testGivenACollectionConstraintInsideAValueObjectWhenLoadingThenEveryFieldRuleEntersTheTruthSet(): void
    {
        // Arrange — `prices` carries `All(Collection(...))`, with `currency` nesting a further
        // Collection and `volumePrices` an `Optional(All(Collection(...)))`.
        $truthSet = $this->loadWishlistTruth();

        // Act
        $priceKeys = $this->nestedValidationKeysFor($truthSet, 'POST /wishlists/{wishlistUuid}/wishlist-items', 'productConfigurationInstance.prices.');

        // Assert — the list index is data, not contract, so no `[0]` segment appears in the path.
        $this->assertSame(
            [
                'wishlist-items.productConfigurationInstance.prices.currency.code.NotBlank',
                'wishlist-items.productConfigurationInstance.prices.currency.name.NotBlank',
                'wishlist-items.productConfigurationInstance.prices.currency.symbol.NotBlank',
                'wishlist-items.productConfigurationInstance.prices.grossAmount.GreaterThanOrEqual',
                'wishlist-items.productConfigurationInstance.prices.grossAmount.NotBlank',
                'wishlist-items.productConfigurationInstance.prices.grossAmount.Type',
                'wishlist-items.productConfigurationInstance.prices.netAmount.GreaterThanOrEqual',
                'wishlist-items.productConfigurationInstance.prices.netAmount.NotBlank',
                'wishlist-items.productConfigurationInstance.prices.netAmount.Type',
                'wishlist-items.productConfigurationInstance.prices.priceTypeName.NotBlank',
                'wishlist-items.productConfigurationInstance.prices.volumePrices.grossAmount.GreaterThanOrEqual',
                'wishlist-items.productConfigurationInstance.prices.volumePrices.grossAmount.Type',
                'wishlist-items.productConfigurationInstance.prices.volumePrices.netAmount.GreaterThanOrEqual',
                'wishlist-items.productConfigurationInstance.prices.volumePrices.netAmount.Type',
                'wishlist-items.productConfigurationInstance.prices.volumePrices.quantity.NotBlank',
                'wishlist-items.productConfigurationInstance.prices.volumePrices.quantity.Type',
            ],
            $priceKeys,
        );
    }

    public function testGivenNestedRulesActiveOnBothGroupsWhenLoadingThenEachInputOperationDemandsItsOwn(): void
    {
        // Arrange — every nested wishlist-items rule carries the create and the update group.
        $truthSet = $this->loadWishlistTruth();

        // Act
        $verbs = array_count_values(array_map(
            static fn ($validation): string => $validation->verb,
            array_filter(
                $truthSet->validationConstraints,
                static fn ($validation): bool => str_starts_with($validation->attribute, 'productConfigurationInstance.'),
            ),
        ));

        // Assert — the two input operations demand the very same nested rules, and no other verb
        // does. The count itself belongs to the schemas, not to this loader.
        $verbNames = array_keys($verbs);
        sort($verbNames);
        $this->assertSame(['PATCH', 'POST'], $verbNames);
        $this->assertGreaterThan(0, $verbs['POST']);
        $this->assertSame($verbs['POST'], $verbs['PATCH']);
    }

    public function testGivenAConstraintScopedToTheCreateGroupWhenLoadingThenItIsNotRequiredForThePatchOperation(): void
    {
        // Arrange — the sku `NotBlank` carries only the wishlist-items create group, while the
        // PATCH operation validates the update group: the rule is active on POST alone.
        $truthSet = $this->loadWishlistTruth();

        // Act
        $skuValidationKeys = array_values(array_filter(
            $this->validationKeys($truthSet->validationConstraints),
            static fn (string $key): bool => str_starts_with($key, 'wishlist-items.sku.'),
        ));

        // Assert
        $this->assertSame(['wishlist-items.sku.NotBlank on POST /wishlists/{wishlistUuid}/wishlist-items'], $skuValidationKeys);
    }

    public function testGivenAResourceWhenLoadingThenEverySuccessOperationDemandsItsRequiredResponseAttributes(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $truthSet = $loader->load([ResponseAttributesFixtureResource::class]);

        // Assert — a readable required attribute is demanded of every success operation, while the
        // identifier, the write-only and opted-out properties and the relationship links are not.
        $keys = array_map(
            static fn (ResponseAttribute $responseAttribute): string => $responseAttribute->key(),
            $truthSet->responseAttributes,
        );
        $this->assertContains('GET /response-attributes-fixture  name', $keys);
        $this->assertContains('POST /response-attributes-fixture  name', $keys);

        $forbiddenKeys = [];
        foreach (['id', 'secret', 'updatedAt', 'items', 'itemsRelationshipData'] as $path) {
            $forbiddenKeys[] = 'GET /response-attributes-fixture  ' . $path;
            $forbiddenKeys[] = 'POST /response-attributes-fixture  ' . $path;
        }
        $this->assertSame([], array_values(array_intersect($keys, $forbiddenKeys)));
    }

    protected function loadWishlistTruth(): TruthSet
    {
        return ContractCoverageFactory::createSchemaTruthLoader()->load([
            WishlistsStorefrontResource::class,
            WishlistItemsStorefrontResource::class,
        ]);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $operations
     *
     * @return array<string>
     */
    protected function operationKeys(array $operations): array
    {
        return array_map(static fn ($operation) => $operation->key(), $operations);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $validations
     *
     * @return array<string>
     */
    protected function validationKeys(array $validations): array
    {
        return array_map(static fn ($validation) => $validation->key(), $validations);
    }

    /**
     * The nested rules of one operation under a given attribute path, keyed without the
     * ` on <VERB> <uriTemplate>` suffix. `$directLeavesOnly` keeps the value object's own fields
     * apart from the deeper `Collection` walk underneath them.
     *
     * @return array<string>
     */
    protected function nestedValidationKeysFor(
        TruthSet $truthSet,
        string $operationKey,
        string $attributePrefix,
        bool $directLeavesOnly = false
    ): array {
        $keys = [];
        foreach ($truthSet->validationConstraints as $validation) {
            $path = $validation->attribute;
            if (!str_starts_with($path, $attributePrefix)) {
                continue;
            }

            if (strtoupper($validation->verb) . ' ' . $validation->uriTemplate !== $operationKey) {
                continue;
            }

            if ($directLeavesOnly && str_contains(substr($path, strlen($attributePrefix)), '.')) {
                continue;
            }

            $keys[] = $validation->resource . '.' . $path . '.' . $validation->rule;
        }

        sort($keys);

        return array_values(array_unique($keys));
    }
}
