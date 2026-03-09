# Glue REST API → API Platform: Plugin Migration Impact Analysis & Phase 1 Strategy

## Context

We are planning a soft migration from the existing Glue REST API modules to the new API Platform. The core challenge: **57 plugin interfaces across 23 Extension modules** are registered in Glue-layer DependencyProviders, but API Platform uses Symfony DI (autowiring) and bypasses Glue — going directly to Client (storefront) or Zed (backend) facades.

**Key discovery**: The `#[Stack]` PHP attribute + `StackResolverPass` + `ProxyFactory` already exists and is proven in 11 production files. This infrastructure resolves DependencyProvider plugin stacks into the Symfony DI container at runtime — exactly the "virtual move" mechanism needed.

---

## Plugin Categorization (Full Landscape — 57 interfaces)

Every plugin interface falls into one of three categories:

### Category A: REST-Specific Mappers (~23 interfaces) — REIMPLEMENT
Map to/from `RestXxxAttributesTransfer` objects → incompatible with API Platform's generated Resource classes. Must rewrite mapping logic in Provider/Processor classes.

### Category B: Business Logic in Glue (~19 interfaces) — BRIDGE via `#[Stack]`
Operate on business-level transfers but live in Glue DependencyProviders. Can be reused as-is by API Platform Providers via the existing `#[Stack]` attribute. **Zero code changes** to existing modules.

### Category C: Zed-Layer Plugins (~16 interfaces) — NO ACTION NEEDED
Already in the correct layer, consumed via Zed facades. Work automatically for API Platform.

| Category | Count | Strategy | BC Impact | Effort per Endpoint |
|---|---|---|---|---|
| A: REST-specific mappers | ~23 | Reimplement in Provider/Processor | None | Medium |
| B: Business logic in Glue | ~19 | Bridge via `#[Stack]` attribute | None | Low |
| C: Zed-layer plugins | ~16 | No action needed | None | Zero |

---

## Detailed Category A Interfaces (REST-Specific — Reimplement)

| Extension Module | Interface | Why Incompatible |
|---|---|---|
| CartsRestApiExtension (Glue) | `RestCartItemsAttributesMapperPluginInterface` | Maps to `RestCartItemsAttributesTransfer` |
| CartsRestApiExtension (Glue) | `RestCartAttributesMapperPluginInterface` | Maps to `RestCartsAttributesTransfer` |
| CartReorderRestApiExtension | `RestCartReorderAttributesMapperPluginInterface` | Maps to `RestCartReorderAttributesTransfer` |
| CartReorderRestApiExtension | `RestCartReorderAttributesValidatorPluginInterface` | Validates REST-specific attributes |
| CartCodesRestApiExtension | `DiscountMapperPluginInterface` | Maps discounts to REST attributes |
| CheckoutRestApiExtension (Glue) | `CheckoutDataResponseMapperPluginInterface` | Maps to REST checkout data response |
| CheckoutRestApiExtension (Glue) | `CheckoutResponseMapperPluginInterface` | Maps to REST checkout response |
| CompanyBusinessUnitsRestApiExtension | `CompanyBusinessUnitMapperPluginInterface` | Maps to REST company BU attributes |
| MerchantsRestApiExtension | `MerchantRestAttributesMapperPluginInterface` | Maps to REST merchant attributes |
| OrdersRestApiExtension | `RestOrderDetailsAttributesMapperPluginInterface` | Maps to REST order details |
| OrdersRestApiExtension | `RestOrderItemsAttributesMapperPluginInterface` | Maps to REST order items |
| ProductOfferPricesRestApiExtension | `RestProductOfferPricesAttributesMapperPluginInterface` | Maps to REST prices |
| ProductPricesRestApiExtension | `RestProductPricesAttributesMapperPluginInterface` | Maps to REST prices |
| ProductsRestApiExtension | `AbstractProductsResourceExpanderPluginInterface` | Expands REST abstract product resource |
| ProductsRestApiExtension | `ConcreteProductsResourceExpanderPluginInterface` | Expands REST concrete product resource |
| QuoteRequestsRestApiExtension | `RestQuoteRequestAttributesExpanderPluginInterface` | Expands REST quote request attrs |
| ShipmentsRestApiExtension (Glue) | `RestAddressResponseMapperPluginInterface` | Maps REST address response |
| ShoppingListsRestApiExtension | `RestShoppingListItemsAttributesMapperPluginInterface` | Maps to REST shopping list attrs |
| UrlsRestApiExtension | `RestUrlResolverAttributesTransferProviderPluginInterface` | Provides REST URL resolver attrs |
| WishlistsRestApiExtension (Glue) | `RestWishlistItemsAttributesMapperPluginInterface` | Maps to REST wishlist attrs |
| ProductConfigurationsRestApiExtension | `ProductConfigurationPriceMapperPluginInterface` | Maps config prices (REST direction) |
| ProductConfigurationsRestApiExtension | `RestProductConfigurationPriceMapperPluginInterface` | Maps config prices (REST direction) |
| SalesOrdersBackendApiExtension | `OrdersBackendApiAttributesMapperPluginInterface` | Maps backend orders attrs |
| PickingListsBackendApiExtension | `PickingListItemsBackendApiAttributesMapperPluginInterface` | Maps backend picking list attrs |

---

## Detailed Category B Interfaces (Business Logic — Bridge via `#[Stack]`)

| Extension Module | Interface | What It Does |
|---|---|---|
| CartsRestApiExtension (Glue) | `CartItemExpanderPluginInterface` | Expands `CartItemRequestTransfer` before Zed call |
| CartsRestApiExtension (Glue) | `CartItemFilterPluginInterface` | Filters cart items in response |
| CartsRestApiExtension (Glue) | `CustomerExpanderPluginInterface` | Expands customer for cart operations |
| CartsRestApiExtension (Glue) | `QuoteCollectionReaderPluginInterface` | Reads quote collection |
| CartsRestApiExtension (Glue) | `QuoteCreatorPluginInterface` (Glue) | Creates quotes from Glue |
| CustomersRestApiExtension | `CustomerExpanderPluginInterface` | Expands customer data |
| CustomersRestApiExtension | `CustomerPostCreatePluginInterface` | Post-create customer actions |
| CheckoutRestApiExtension (Glue) | `CheckoutRequestExpanderPluginInterface` | Expands checkout request |
| CheckoutRestApiExtension (Glue) | `CheckoutRequestValidatorPluginInterface` | Validates checkout request |
| CheckoutRestApiExtension (Glue) | `CheckoutRequestAttributesValidatorPluginInterface` | Validates checkout request attributes |
| ShipmentsRestApiExtension (Glue) | `AddressSourceCheckerPluginInterface` | Checks address source type |
| ShipmentsRestApiExtension (Glue) | `ShippingAddressValidationStrategyPluginInterface` | Validates shipping addresses |
| SharedCartsRestApiExtension | `CompanyUserProviderPluginInterface` | Provides company user |
| ShoppingListsRestApiExtension | `ShoppingListItemRequestMapperPluginInterface` | Maps shopping list item request |
| WishlistsRestApiExtension (Glue) | `WishlistItemRequestMapperPluginInterface` | Maps wishlist item request |
| AuthRestApiExtension (Glue) | `RestUserMapperPluginInterface` | Maps REST user |
| OauthBackendApiExtension | `UserRequestValidationPreCheckerPluginInterface` | Pre-checks user request |
| CartReorderRestApiExtension | `CartReorderRequestExpanderPluginInterface` | Expands cart reorder request |

---

## Detailed Category C Interfaces (Zed-Layer — No Action)

| Extension Module | Interface | Layer |
|---|---|---|
| AuthRestApiExtension | `PostAuthPluginInterface` | Zed |
| CartsRestApiExtension | `CartItemMapperPluginInterface` | Zed |
| CartsRestApiExtension | `QuoteCollectionExpanderPluginInterface` | Zed |
| CartsRestApiExtension | `QuoteCreatorPluginInterface` (Zed) | Zed |
| CartsRestApiExtension | `QuoteExpanderPluginInterface` | Zed |
| CartsRestApiExtension | `QuoteItemReadValidatorPluginInterface` | Zed |
| CartsRestApiExtension | `QuoteMergePersistentCartChangeExpanderPluginInterface` | Zed |
| CheckoutRestApiExtension | `CheckoutDataExpanderPluginInterface` | Zed |
| CheckoutRestApiExtension | `CheckoutDataValidatorPluginInterface` | Zed |
| CheckoutRestApiExtension | `QuoteMapperPluginInterface` | Zed |
| CheckoutRestApiExtension | `ReadCheckoutDataValidatorPluginInterface` | Zed |
| OrderPaymentsRestApiExtension | `OrderPaymentUpdaterPluginInterface` | Zed |
| ShipmentsRestApiExtension | `AddressProviderStrategyPluginInterface` | Zed |
| ShipmentsRestApiExtension | `QuoteItemExpanderPluginInterface` | Zed |
| WishlistsRestApiExtension | `RestWishlistItemsAttributesDeleteStrategyPluginInterface` | Zed |
| WishlistsRestApiExtension | `RestWishlistItemsAttributesUpdateStrategyPluginInterface` | Zed |

---

## The `#[Stack]` Bridge Pattern (Category B)

This is the critical mechanism. It already exists and works:

### How It Works

```php
// API Platform Provider declares dependency on Glue-registered plugins:
use Spryker\Service\Container\Attributes\Stack;
use Spryker\Glue\CartsRestApi\CartsRestApiDependencyProvider;

class CartsStorefrontProvider implements ProviderInterface
{
    /**
     * @param array<\Spryker\Glue\CartsRestApiExtension\Dependency\Plugin\CartItemExpanderPluginInterface> $cartItemExpanderPlugins
     */
    #[Stack(
        dependencyProvider: CartsRestApiDependencyProvider::class,
        dependencyProviderMethod: 'getCartItemExpanderPlugins',
        provideToArgument: '$cartItemExpanderPlugins',
    )]
    public function __construct(
        protected CartClientInterface $cartClient,
        protected array $cartItemExpanderPlugins,
    ) {}
}
```

### Resolution Flow (all existing code, no changes)
1. `StackResolverPass` (compile-time) detects `#[Stack]` → creates factory service definition
2. Factory calls `ProxyFactory::createPluginProviderProxy(dependencyProviderClass, getterMethod)`
3. `ProxyFactory` uses `ContainerDelegator` to find Pyz DependencyProvider override
4. Falls back to `new DependencyProviderClass()` if not in container
5. Calls protected getter via reflection → returns plugin array
6. Array injected into Provider/Processor constructor

### Key Infrastructure Files (existing, no modifications needed)
- `src/Spryker/Container/src/Spryker/Service/Container/Attributes/Stack.php`
- `src/Spryker/Container/src/Spryker/Service/Container/Pass/StackResolverPass.php`
- `src/Spryker/Container/src/Spryker/Service/Container/ProxyFactory.php`

### Existing Usage (11 files, proven pattern)
- `src/Spryker/Customer/src/Spryker/Zed/Customer/Business/CustomerExpander/CustomerExpander.php`
- `src/Spryker/Customer/src/Spryker/Zed/Customer/Business/Customer/Customer.php`
- `src/Spryker/Store/src/Spryker/Client/Store/Reader/StoreReader.php`
- (and 8 more Customer module files)

---

## Phase 1: Proof-of-Concept Modules (5 modules)

These 5 modules validate all three categories and establish patterns for the full migration:

### Module 1: StoresRestApi (StorefrontAPI) — Validate basic flow
**Current endpoints**: `GET /stores`, `GET /stores/{id}`
**Plugins**: None from Extension modules
**Category**: No plugins involved — pure Provider implementation
**Why first**: Simplest possible migration. Validates API Platform resource definition, YAML schema, Provider pattern, and storefront authentication flow.
**What to build**:
- `resources/api/storefront/stores.resource.yml`
- `Spryker\Glue\Store\Api\Storefront\Provider\StoresStorefrontProvider` (uses `StoreClientInterface`)

---

### Module 2: ProductsRestApi (StorefrontAPI) — Validate Category A (reimplement)
**Current endpoints**: `GET /abstract-products/{sku}`, `GET /concrete-products/{sku}`
**Plugins from Extension**:

| Interface | Category | Plugins (Pyz) |
|---|---|---|
| `AbstractProductsResourceExpanderPluginInterface` | A (reimplement) | `ProductReviewsAbstractProductsResourceExpanderPlugin`, `MultiSelectAttributeAbstractProductsResourceExpanderPlugin` |
| `ConcreteProductsResourceExpanderPluginInterface` | A (reimplement) | `ProductReviewsConcreteProductsResourceExpanderPlugin`, `ProductDiscontinuedConcreteProductsResourceExpanderPlugin`, `ProductConfigurationConcreteProductsResourceExpanderPlugin`, `MultiSelectAttributeConcreteProductsResourceExpanderPlugin` |

**Why second**: Validates how to reimplement REST-specific expander plugins for API Platform Resource classes. The old plugins add data to `RestAbstractProductsAttributesTransfer` / `RestConcreteProductsAttributesTransfer`. The new Providers must add the same data to the generated `AbstractProductsStorefrontResource` / `ConcreteProductsStorefrontResource`.

**Key decision**: How to make the expansion extensible in the new world. Options:
  - (a) Inline the expansion logic in the Provider
  - (b) Define new `Api\Storefront\Plugin\ProductResourceExpanderPluginInterface` and use `#[Stack]` or `tagged_iterator`
  - (c) Handle via API Platform's `?include=` relationship loading (already built into the module)

**Recommendation**: Option (c) for related resources (reviews, images, prices — they're already separate endpoints). Option (a) for attribute-level enrichment (discontinued flag, configuration data) — the Provider can call the relevant Client methods directly.

---

### Module 3: WishlistsRestApi (StorefrontAPI) — Validate Category B (`#[Stack]` bridge)
**Current endpoints**: `GET,POST /wishlists`, `GET,PATCH,DELETE /wishlists/{id}`, `POST /wishlists/{id}/wishlist-items`, etc.
**Plugins from Extension**:

| Interface | Category | Plugins (Pyz) |
|---|---|---|
| `RestWishlistItemsAttributesMapperPluginInterface` (Glue) | A (reimplement) | `ProductPriceRestWishlistItemsAttributesMapperPlugin`, `ProductConfigurationRestWishlistItemsAttributesMapperPlugin`, `ProductAvailabilityRestWishlistItemsAttributesMapperPlugin`, `ProductOfferRestWishlistItemsAttributesMapperPlugin` |
| `WishlistItemRequestMapperPluginInterface` (Glue) | **B (bridge)** | `ProductConfigurationWishlistItemRequestMapperPlugin` |
| `RestWishlistItemsAttributesDeleteStrategyPluginInterface` (Zed) | C (auto) | 2 plugins (Zed) |
| `RestWishlistItemsAttributesUpdateStrategyPluginInterface` (Zed) | C (auto) | 1 plugin (Zed) |

**Why third**: Validates all three categories in one module:
- Category A: Reimplement the 4 wishlist item attribute mapper plugins for the new Resource class
- Category B: Bridge `WishlistItemRequestMapperPluginInterface` via `#[Stack]` — the first real test of the virtual bridge pattern
- Category C: The 3 Zed-layer plugins work automatically via the Zed facade

**What to build**:
- YAML schema for wishlists resource
- Provider with `#[Stack]` for `WishlistItemRequestMapperPluginInterface`:
```php
#[Stack(
    dependencyProvider: WishlistsRestApiDependencyProvider::class,
    dependencyProviderMethod: 'getWishlistItemRequestMapperPlugins',
    provideToArgument: '$wishlistItemRequestMapperPlugins',
)]
public function __construct(
    protected WishlistClientInterface $wishlistClient,
    protected array $wishlistItemRequestMapperPlugins,
) {}
```

---

### Module 4: CartsRestApi (StorefrontAPI) — Validate high-complexity Category B
**Current endpoints**: `GET,POST /carts`, `GET,PATCH,DELETE /carts/{id}`, cart items, guest carts
**Plugins from Extension** (13 interfaces — highest density):

| Interface | Category | Plugin Count |
|---|---|---|
| `CartItemExpanderPluginInterface` (Glue) | **B (bridge)** | 6 plugins |
| `CartItemFilterPluginInterface` (Glue) | **B (bridge)** | 1 plugin |
| `CustomerExpanderPluginInterface` (Glue) | **B (bridge)** | 1 plugin |
| `QuoteCollectionReaderPluginInterface` (Glue) | **B (bridge)** | 1 plugin |
| `QuoteCreatorPluginInterface` (Glue) | **B (bridge)** | 1 plugin |
| `RestCartItemsAttributesMapperPluginInterface` (Glue) | A (reimplement) | 5 plugins |
| `RestCartAttributesMapperPluginInterface` (Glue) | A (reimplement) | 2 plugins |
| `CartItemMapperPluginInterface` (Zed) | C (auto) | 5 plugins |
| `QuoteCollectionExpanderPluginInterface` (Zed) | C (auto) | 1 plugin |
| `QuoteCreatorPluginInterface` (Zed) | C (auto) | 1 plugin |
| `QuoteExpanderPluginInterface` (Zed) | C (auto) | 3 plugins |
| `QuoteItemReadValidatorPluginInterface` (Zed) | C (auto) | 1 plugin |
| `QuoteMergePersistentCartChangeExpanderPluginInterface` (Zed) | C (auto) | 1 plugin |

**Why fourth**: Validates `#[Stack]` with multiple plugin stacks on a single Provider. The Provider constructor will have 5 `#[Stack]` attributes pulling from the same `CartsRestApiDependencyProvider`.

**What to build**:
- Provider with multiple `#[Stack]` attributes:
```php
#[Stack(dependencyProvider: CartsRestApiDependencyProvider::class, dependencyProviderMethod: 'getCartItemExpanderPlugins', provideToArgument: '$cartItemExpanderPlugins')]
#[Stack(dependencyProvider: CartsRestApiDependencyProvider::class, dependencyProviderMethod: 'getCartItemFilterPlugins', provideToArgument: '$cartItemFilterPlugins')]
#[Stack(dependencyProvider: CartsRestApiDependencyProvider::class, dependencyProviderMethod: 'getCustomerExpanderPlugins', provideToArgument: '$customerExpanderPlugins')]
public function __construct(
    protected CartClientInterface $cartClient,
    protected array $cartItemExpanderPlugins,
    protected array $cartItemFilterPlugins,
    protected array $customerExpanderPlugins,
) {}
```

---

### Module 5: CheckoutRestApi (StorefrontAPI) — Validate cross-module plugin dependencies
**Current endpoints**: `POST /checkout-data`, `POST /checkout`
**Plugins from Extension** (9 interfaces):

| Interface | Category | Plugin Count |
|---|---|---|
| `CheckoutRequestExpanderPluginInterface` (Glue) | **B (bridge)** | 1 plugin |
| `CheckoutRequestValidatorPluginInterface` (Glue) | **B (bridge)** | 1 plugin |
| `CheckoutRequestAttributesValidatorPluginInterface` (Glue) | **B (bridge)** | 2 plugins |
| `CheckoutDataResponseMapperPluginInterface` (Glue) | A (reimplement) | 2 plugins |
| `CheckoutResponseMapperPluginInterface` (Glue) | A (no impls) | 0 plugins |
| `CheckoutDataExpanderPluginInterface` (Zed) | C (auto) | 3 plugins |
| `CheckoutDataValidatorPluginInterface` (Zed) | C (auto) | 8 plugins |
| `QuoteMapperPluginInterface` (Zed) | C (auto) | 10 plugins |
| `ReadCheckoutDataValidatorPluginInterface` (Zed) | C (auto) | 4 plugins |

**Why fifth**: The checkout flow touches the most cross-module plugins (ShipmentsRestApi, PaymentsRestApi, CustomersRestApi, CompanyUsersRestApi, ServicePointsRestApi all contribute plugins). Validates that `#[Stack]` works correctly when the DependencyProvider aggregates plugins from many modules.

---

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Glue DependencyProvider not resolvable in API Platform application context | `ProxyFactory` falls back to `new DependencyProviderClass()` — safe since plugin getters return plain `new Plugin()` arrays without container access |
| Multiple `#[Stack]` attributes on one constructor | `StackResolverPass` already handles this — each creates an independent factory service |
| Pyz overrides not respected | `ProxyFactory::createPluginProviderProxy()` checks `ContainerDelegator` first for Pyz override, then falls back to core class |
| REST-specific plugins accidentally reused | Categorization prevents this — only Category B plugins use `#[Stack]`; Category A plugins are explicitly reimplemented |
| Performance overhead from reflection | One-time cost per container build (cached). Runtime resolution is a single method call per plugin stack |

---

## Verification Strategy

For each Phase 1 module:
1. **Response parity**: Compare old Glue REST response vs new API Platform response for identical requests (same data, different format)
2. **Plugin execution**: Verify Category B plugins fire by adding temporary logging or using Xdebug
3. **Pyz override**: Confirm project-level plugin additions are included (not just core defaults)
4. **Category C auto-flow**: Verify Zed-layer plugins execute via facade call chain (no gaps)
5. **Run existing tests**: `vendor/bin/spryker-ci spryker-ci` for static validation after each module

---

## What This Plan Does NOT Cover (Deferred to Later Phases)

- Extension-only modules (34 StorefrontAPI + 4 BackendAPI) — these provide plugins to other modules, not their own endpoints
- DynamicEntity endpoints (304 endpoints) — separate analysis
- Endpoint consolidation/optimization from `api-endpoint-optimizations.md` — separate initiative
- New Extension module interfaces for API Platform extensibility (if needed for Category A reimplementations)
- BackendAPI modules beyond what's covered in Phase 1
