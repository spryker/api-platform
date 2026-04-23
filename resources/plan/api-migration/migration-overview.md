# API Platform Migration Overview

> Last updated: 2026-04-07

## Table of Contents
- [Migration Status Table](#migration-status-table)
- [DependencyProviders in ApplicationServices.php](#dependencyproviders-in-applicationservicesphp)
- [Validation: Mapper Usage in Providers/Processors](#validation-mapper-usage-in-providersprocessors)
- [Validation: Direct Property Access Violations](#validation-direct-property-access-violations)

---

## Migration Status Table

Legend:
- **Source Module** = Original RestApi/BackendApi module
- **Target Module** = New API Platform module where logic was placed
- **Providers** = Number of new Provider classes
- **Processors** = Number of new Processor classes
- **Mapper Issues** = Provider/Processor uses mapper class or mapper plugins (should be eliminated)
- **Property Access** = Direct `$resource->prop = val` instead of `fromArray()`/`toArray()`
- **Status**: `done` | `in-progress` | `review` | `todo` | `blocked`
- **Migration Guide**: `done` = guide published in spryker-docs | `todo` = not yet written

| # | Source Module | Target Module | Providers | Processors | Mapper Issues | Property Access | Status | Migration Guide |
|---|---------------|---------------|-----------|------------|---------------|-----------------|--------|-----------------|
| 1 | AgentAuthRestApi | Agent | 1 | 2 | none | none | review | done |
| 2 | AlternativeProductsRestApi | ProductAlternative | 2 | 0 | none | none | review | done |
| 3 | AuthRestApi | Authentication | 0 | 3 | none | none | review | done |
| 4 | AvailabilityNotificationsRestApi | — | 0 | 0 | — | — | todo | todo |
| 5 | CartCodesRestApi | CartCode | 5 | 3 | CartCodeErrorHandler (renamed) | none | review | done |
| 6 | CartPermissionGroupsRestApi | SharedCart | 2 | 1 | none | none | review | done |
| 7 | CartReorderRestApi | — | 0 | 0 | — | — | todo | todo |
| 8 | CartsRestApi | Cart | 7 | 4 | CartErrorHandler (renamed), mapper plugins (postponed) | yes (items, guestCartItems — postponed) | review | done |
| 9 | CatalogSearchRestApi | Catalog | 2 | 0 | none | none | review | done |
| 10 | CategoriesRestApi | Category | 2 | 0 | none | none | review | done |
| 11 | CheckoutRestApi | Checkout | 2 | 1 | CheckoutErrorHandler (renamed), response mapper plugins (postponed — RestApi modules) | none | review | done |
| 12 | CmsPagesRestApi | — | 0 | 0 | — | — | todo | todo |
| 13 | CompaniesRestApi | Company | 1 | 0 | none | none | review | done |
| 14 | CompanyBusinessUnitAddressesRestApi | CompanyUnitAddress | 1 | 0 | none | none | review | todo |
| 15 | CompanyBusinessUnitsRestApi | CompanyBusinessUnit | 1 | 0 | none | none | review | todo |
| 16 | CompanyRolesRestApi | CompanyRole | 1 | 0 | none | none | review | todo |
| 17 | CompanyUserAuthRestApi | CompanyUser | 1 | 1 | none | none | review | done |
| 18 | CompanyUsersRestApi | CompanyUser | 1 | 0 | none | none | review | done |
| 19 | ConfigurableBundleCartsRestApi | — | 0 | 0 | — | — | todo | todo |
| 20 | ConfigurableBundlesRestApi | — | 0 | 0 | — | — | todo | todo |
| 21 | ContentBannersRestApi | — | 0 | 0 | — | — | todo | todo |
| 22 | ContentProductAbstractListsRestApi | ContentProduct | 2 | 0 | none | none | review | done |
| 23 | CustomerAccessRestApi | CustomerAccess | 1 | 0 | none | none | review | done |
| 24 | CustomersRestApi | Customer | 4 | 4 | none | none | review | done |
| 25 | DiscountsRestApi | — | 0 | 0 | — | — | todo | todo |
| 26 | MerchantOpeningHoursRestApi | MerchantOpeningHours | 1 | 0 | none | none | review | done |
| 27 | MerchantProductOffersRestApi | MerchantProductOffer | 2 | 0 | none | none | review | done |
| 28 | MerchantsRestApi | Merchant | 2 | 0 | none (inlined) | none | review | done |
| 29 | NavigationsRestApi | Navigation | 1 | 0 | none | none | review | done |
| 30 | OauthApi | Oauth | 0 | 1 | none | none | review | done |
| 31 | OrderPaymentsRestApi | Payment | 0 | 1 | none | none | review | done |
| 32 | OrdersRestApi | Sales | 2 | 0 | none (refactored to expander plugins, Rest* transfers removed) | none | review | done |
| 33 | PaymentsRestApi | Payment | 0 | 3 | none | none | review | done |
| 34 | ProductAttributesRestApi | ProductAttribute | 1 | 0 | none | none | review | done |
| 35 | ProductAvailabilitiesRestApi | Availability | 2 | 0 | none | none | review | done |
| 36 | ProductBundlesRestApi | ProductBundle | 3 | 0 | none (replaced with trait + serializer) | none | review | done |
| 37 | ProductImageSetsRestApi | ProductImage | 2 | 0 | none | none | review | done |
| 38 | ProductLabelsRestApi | ProductLabel | 1 | 0 | none | none | review | done |
| 39 | ProductMeasurementUnitsRestApi | ProductMeasurementUnit | 2 | 0 | none | none | review | done |
| 40 | ProductOfferAvailabilitiesRestApi | ProductOfferAvailability | 1 | 0 | none | none | review | done |
| 41 | ProductOfferPricesRestApi | PriceProductOffer | 1 | 0 | none | none | review | done |
| 42 | ProductOfferServicePointAvailabilitiesRestApi | ProductOfferServicePointAvailability | 0 | 1 | none | none | review | done |
| 43 | ProductOptionsRestApi | ProductOption | 1 | 0 | none | none | review | done |
| 44 | ProductPricesRestApi | PriceProduct | 2 | 0 | none | none | review | done |
| 45 | ProductReviewsRestApi | ProductReview | 1 | 0 | none | none | review | done |
| 46 | ProductsRestApi | Product | 2 | 0 | none | none | review | done |
| 47 | ProductTaxSetsRestApi | Tax | 1 | 0 | none | none | review | done |
| 48 | QuoteRequestAgentsRestApi | QuoteRequestAgent | 1 | 5 | none | none | review | done |
| 49 | QuoteRequestsRestApi | QuoteRequest | 1 | 6 | none | none | review | done |
| 50 | RelatedProductsRestApi | — | 0 | 0 | — | — | todo | todo |
| 51 | SalesReturnsRestApi | — | 0 | 0 | — | — | todo | todo |
| 52 | ServicePointsRestApi | — | 0 | 0 | — | — | todo | todo |
| 53 | SharedCartsRestApi | SharedCart | 2 | 1 | none | none | review | done |
| 54 | ShipmentTypesRestApi | ShipmentType | 1 | 0 | none | none | review | done |
| 55 | ShoppingListsRestApi | — | 0 | 0 | — | — | todo | todo |
| 56 | StoresRestApi / StoresApi | Store | 1 | 0 | none | none | review | done |
| 57 | TaxAppRestApi | TaxApp | 0 | 1 | none | none | review | done |
| 58 | UpSellingProductsRestApi | — | 0 | 0 | — | — | todo | todo |
| 59 | UrlsRestApi | — | 0 | 0 | — | — | todo | todo |
| 60 | WishlistsRestApi | — | 0 | 0 | — | — | todo | todo |

### BackendAPI Modules

| # | Source Module | Target Module | Providers | Processors | Mapper Issues | Property Access | Status | Migration Guide |
|---|---------------|---------------|-----------|------------|---------------|-----------------|--------|-----------------|
| 61 | OauthBackendApi | Authentication | 0 | 1 | none | none | review | todo |
| 62 | PickingListsBackendApi | PickingList | 0 | 2 | none | none | review | todo |
| 63 | ShipmentTypesBackendApi | ShipmentType | 1 | 1 | none | none | review | todo |
| 64 | CategoriesBackendApi | — | 0 | 0 | — | — | todo | todo |
| 65 | DynamicEntityBackendApi | — | 0 | 0 | — | — | todo | todo |
| 66 | ProductAttributesBackendApi | — | 0 | 0 | — | — | todo | todo |
| 67 | ProductsBackendApi | — | 0 | 0 | — | — | todo | todo |
| 68 | PushNotificationsBackendApi | — | 0 | 0 | — | — | todo | todo |
| 69 | ServicePointsBackendApi | — | 0 | 0 | — | — | todo | todo |
| 70 | ProductImageSetsBackendApi | — | 0 | 0 | — | — | todo | todo |
| 71 | SalesOrdersBackendApi | Sales | 0 | 0 | — | — | todo | todo | <!-- schema exists in Sales/backend, no Provider/Processor yet -->
| 72 | ShipmentsBackendApi | Shipment | 0 | 0 | — | — | todo | todo | <!-- schema exists in Shipment/backend, no Provider/Processor yet -->
| 73 | StoresBackendApi | — | 0 | 0 | — | — | todo | todo |
| 74 | UsersBackendApi | — | 0 | 0 | — | — | todo | todo |
| 75 | WarehouseOauthBackendApi | — | 0 | 0 | — | — | todo | todo |
| 76 | WarehousesBackendApi | — | 0 | 0 | — | — | todo | todo |
| 77 | WarehouseUsersBackendApi | — | 0 | 0 | — | — | todo | todo |
| 78 | — (new) | KernelFeature | 1 | 0 | none | none | review | todo |

### Schema Extension Modules

These modules contribute `*.resource.yml` files that add properties, validation, or relationships to resources defined in other modules. They have no source RestApi/BackendApi module and no standalone Provider/Processor.

| Module | Type | Extends Resource | What it adds |
|--------|------|------------------|-------------|
| MerchantProduct | Storefront | AbstractProducts (Product) | `merchantReference` property |
| SalesOrderAmendment | Storefront | Orders (Sales), Carts (Cart) | `order-amendments` resource + `amendmentOrderReference` |
| ShipmentTypeProductOfferServicePointAvailabilitiesRestApi | Storefront | ProductOfferServicePointAvailabilities | Validation schema |
| Currency | Backend | Stores (Store) | Currency properties |
| Locale | Backend | Stores (Store) | Locale properties |
| StoreContext | Backend | Stores (Store) | Context properties |

### Summary

| Metric | Count |
|--------|-------|
| Total source modules | 78 |
| Migrated (review) | 46 |
| Not started (todo) | 31 |
| Total new Providers | 62 |
| Total new Processors | 37 |
| Schema extension modules | 6 |
| With mapper violations | 2 modules (Cart mapper plugins postponed, Checkout response mapper plugins postponed — RestApi constraint) |
| With property access violations | 3 modules (Cart items/guestCartItems postponed, Payment already clean, ProductOfferServicePointAvailability already clean) |
| Migration guides published | 36 (AgentAuthRestApi, AlternativeProductsRestApi, AuthRestApi, CartCodesRestApi, CartPermissionGroupsRestApi, CartsRestApi, CatalogSearchRestApi, CategoriesRestApi, CheckoutRestApi, CompaniesRestApi, CompanyUsersRestApi+CompanyUserAuthRestApi, ContentProductAbstractListsRestApi, CustomersRestApi, CustomerAccessRestApi, MerchantOpeningHoursRestApi, MerchantProductOffersRestApi, MerchantsRestApi, NavigationsRestApi, OauthApi, OrderPaymentsRestApi+PaymentsRestApi, OrdersRestApi, ProductAttributesRestApi, ProductAvailabilitiesRestApi, ProductBundlesRestApi, ProductImageSetsRestApi, ProductLabelsRestApi, ProductMeasurementUnitsRestApi, ProductOfferAvailabilitiesRestApi, ProductOfferPricesRestApi, ProductOfferServicePointAvailabilitiesRestApi, ProductOptionsRestApi, ProductPricesRestApi, ProductReviewsRestApi, ProductsRestApi, ProductTaxSetsRestApi, QuoteRequestsRestApi+QuoteRequestAgentsRestApi, SharedCartsRestApi, ShipmentTypesRestApi, StoresRestApi, TaxAppRestApi) |
| Migration guides remaining | 42 |

---

## DependencyProviders in ApplicationServices.php

The following Pyz-level DependencyProviders are registered in `config/GlueStorefront/ApplicationServices.php` to supply plugin stacks via `#[Stack]` attributes:

| DependencyProvider | Plugin Stacks | Plugin Count | Notes |
|--------------------|---------------|--------------|-------|
| `Pyz\Glue\Checkout\CheckoutDependencyProvider` | checkoutRequestAttributesValidatorPlugins, checkoutRequestValidatorPlugins, checkoutDataResponseMapperPlugins, checkoutRequestExpanderPlugins | 12 | Response mapper plugins postponed (RestApi constraint) |
| `Pyz\Glue\Sales\SalesDependencyProvider` | orderItemExpanderPlugins, orderDetailsExpanderPlugins | 6 | Renamed from Mapper to Expander, Shipment plugin removed (inlined in trait) |
| `Pyz\Glue\Cart\CartDependencyProvider` | cartItemRequestExpanderPlugins, cartItemStorefrontResourceMapperPlugins, cartStorefrontResourceMapperPlugins, cartItemFilterPlugins | 12 | Mapper plugins postponed for review |
| `Pyz\Glue\Product\ProductDependencyProvider` | abstractProductsStorefrontResourceExpanderPlugins, concreteProductsStorefrontResourceExpanderPlugins | 5 | |
| ~~`Pyz\Glue\PriceProduct\PriceProductDependencyProvider`~~ | ~~productPricesStorefrontResourceMapperPlugins~~ | ~~1~~ | Deleted — plugin was dead code |
| ~~`Pyz\Glue\PriceProductOffer\PriceProductOfferDependencyProvider`~~ | ~~productOfferPricesStorefrontResourceMapperPlugins~~ | ~~1~~ | Deleted — volume prices already inlined |
| ~~`Pyz\Glue\Merchant\MerchantDependencyProvider`~~ | ~~merchantStorefrontResourceMapperPlugins~~ | ~~1~~ | Deleted — category mapping inlined |

---

## Validation: Mapper Usage in Providers/Processors

The following Provider/Processor classes use mapper classes or mapper plugins — these should be refactored to use `fromArray()`/`toArray()` and inline data preparation instead.

### Mapper Classes — Resolved

| Module | Class | Resolution |
|--------|-------|------------|
| ProductBundle | BundleItemsStorefrontProvider | Replaced with `BundleItemResourcePreparerTrait` + serializer |
| ProductBundle | BundledItemsStorefrontProvider | Same |
| Cart | CartItemsStorefrontProcessor | Renamed `CartErrorMapper` → `CartErrorHandler` (moved to ErrorHandler/) |
| Cart | GuestCartItemsStorefrontProcessor | Same |
| Cart | CartsStorefrontProcessor | Same |
| Cart | GuestCartsStorefrontProcessor | Same |
| CartCode | CartCodesStorefrontProcessor | Renamed `CartCodeErrorMapper` → `CartCodeErrorHandler` (moved to ErrorHandler/) |
| CartCode | CartVouchersStorefrontProcessor | Same |
| CartCode | GuestCartVouchersStorefrontProcessor | Same |
| Checkout | CheckoutStorefrontProcessor | Renamed `CheckoutErrorMapper` → `CheckoutErrorHandler` (moved to ErrorHandler/) |

### Mapper Plugins — Resolved / Remaining

| Module | Class | Resolution |
|--------|-------|------------|
| Merchant | MerchantsStorefrontProvider | Inlined (category mapping in `prepareResourceData`), plugin + interface deleted |
| Sales | OrdersStorefrontProvider | Refactored to `OrderItemExpanderPluginInterface` + `OrderDetailsExpanderPluginInterface` (array-based, Rest* transfers removed) |
| Sales | CustomersOrdersStorefrontProvider | Same |
| PriceProduct | (trait) | Dead code removed, plugin + interface deleted |
| PriceProductOffer | (provider) | Volume prices already inlined, plugin + interface deleted |
| **Cart** | **CartItemsStorefrontProcessor** | **Postponed** — `CartStorefrontResourceMapperPluginInterface`, `CartItemStorefrontResourceMapperPluginInterface` need detailed review |
| **Cart** | **GuestCartItemsStorefrontProcessor** | **Postponed** — same |
| **Checkout** | **CheckoutStorefrontProcessor** | **Postponed** — `CheckoutDataResponseMapperPluginInterface` lives in RestApi modules (not allowed to refactor) |

---

## Validation: Direct Property Access Violations

The following Provider/Processor classes use direct property access (`$resource->prop = value`) instead of `fromArray()`/`toArray()`:

### Resolved

| Module | Class | Resolution |
|--------|-------|------------|
| Agent | AgentAccessTokensStorefrontProcessor | `TokenResourcePreparerTrait` + serializer |
| Agent | AgentCustomerImpersonationAccessTokensStorefrontProcessor | Same |
| Authentication | AccessTokensStorefrontProcessor | Same (+ `idCompanyUser` in data array) |
| Authentication | RefreshTokensStorefrontProcessor | Same |
| Authentication | TokensStorefrontProcessor | Same |
| Cart | CartsStorefrontProcessor | Totals cleared in data array before denormalization |
| CartCode | CartCodesStorefrontProvider | `serializer->denormalize()` |
| CartCode | CartVouchersStorefrontProvider | Same |
| CartCode | GuestCartCodesStorefrontProvider | Same |
| CartCode | GuestCartVouchersStorefrontProvider | Same |
| Payment | All 4 processors | Already clean (no violations found) |
| ProductOfferServicePointAvailability | Processor | Already clean (no violations found) |
| QuoteRequest | All 4 processors | `serializer->denormalize()` via abstract class |
| QuoteRequestAgent | All 3 processors | Same |
| SharedCart | CartPermissionGroupsStorefrontProvider | `serializer->denormalize()` |
| SharedCart | SharedCartsStorefrontProvider | Same |

### Remaining (postponed)

| Module | Class | Properties | Reason |
|--------|-------|------------|--------|
| Cart | CartItemsStorefrontProcessor | items | Relationship attachment pattern for JSON:API includes |
| Cart | GuestCartItemsStorefrontProcessor | guestCartItems | Same |
| Cart | CustomersCartsStorefrontProvider | — | Already clean (no violations found) |
