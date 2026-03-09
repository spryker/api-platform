# API-Providing Modules: Extension Interfaces, Plugins & Registrations

## Context

This document catalogs all Extension modules for API-providing modules listed in `src/Spryker/ApiPlatform/resources/plan/api-providing-modules.md`. For each Extension module it lists the defined interfaces, the plugins implementing those interfaces, and where those plugins are registered in `src/Pyz/` DependencyProvider classes.

---

## Extension Modules Overview

Of 112 API-providing modules, **23 have corresponding Extension modules** with plugin interfaces:

| # | Extension Module | Layer(s) | Interface Count |
|---|-----------------|----------|----------------|
| 1 | AuthRestApiExtension | Glue, Zed | 2 |
| 2 | CartCodesRestApiExtension | Glue | 1 |
| 3 | CartReorderRestApiExtension | Glue | 3 |
| 4 | CartsRestApiExtension | Glue, Zed | 13 |
| 5 | CheckoutRestApiExtension | Glue, Zed | 9 |
| 6 | CompanyBusinessUnitsRestApiExtension | Glue | 1 |
| 7 | CustomersRestApiExtension | Glue | 3 |
| 8 | MerchantsRestApiExtension | Glue | 1 |
| 9 | OrderPaymentsRestApiExtension | Zed | 1 |
| 10 | OrdersRestApiExtension | Glue | 2 |
| 11 | ProductOfferPricesRestApiExtension | Glue | 1 |
| 12 | ProductPricesRestApiExtension | Glue | 1 |
| 13 | ProductsRestApiExtension | Glue | 2 |
| 14 | QuoteRequestsRestApiExtension | Glue | 1 |
| 15 | SharedCartsRestApiExtension | Glue | 1 |
| 16 | ShoppingListsRestApiExtension | Glue | 2 |
| 17 | UrlsRestApiExtension | Glue | 1 |
| 18 | WishlistsRestApiExtension | Glue, Zed | 4 |
| 19 | ShipmentsRestApiExtension | Glue, Zed | 5 |
| 20 | OauthBackendApiExtension | Glue | 1 |
| 21 | PickingListsBackendApiExtension | Glue | 1 |
| 22 | SalesOrdersBackendApiExtension | Glue | 1 |
| 23 | ProductConfigurationsRestApiExtension | Glue | 2 |

**Total: 57 interfaces**

---

## Detailed Catalog

### 1. AuthRestApiExtension

#### Glue: `RestUserMapperPluginInterface`
- **Interface:** `src/Spryker/AuthRestApiExtension/src/Spryker/Glue/AuthRestApiExtension/Dependency/Plugin/RestUserMapperPluginInterface.php`

| Plugin | Module | File |
|--------|--------|------|
| `CompanyUserRestUserMapperPlugin` | CompanyUserAuthRestApi | `src/Spryker/CompanyUserAuthRestApi/src/Spryker/Glue/CompanyUserAuthRestApi/Plugin/AuthRestApi/CompanyUserRestUserMapperPlugin.php` |
| `AgentRestUserMapperPlugin` | AgentAuthRestApi | `src/Spryker/AgentAuthRestApi/src/Spryker/Glue/AgentAuthRestApi/Plugin/AuthRestApi/AgentRestUserMapperPlugin.php` |

**Pyz Registration:** `src/Pyz/AuthRestApi/src/Pyz/Glue/AuthRestApi/AuthRestApiDependencyProvider.php`
- Method: `getRestUserExpanderPlugins()`

#### Zed: `PostAuthPluginInterface`
- **Interface:** `src/Spryker/AuthRestApiExtension/src/Spryker/Zed/AuthRestApiExtension/Dependency/Plugin/PostAuthPluginInterface.php`

| Plugin | Module | File |
|--------|--------|------|
| `AddGuestQuoteItemsToCustomerQuotePostAuthPlugin` | CartsRestApi | `src/Spryker/CartsRestApi/src/Spryker/Zed/CartsRestApi/Communication/Plugin/AuthRestApi/AddGuestQuoteItemsToCustomerQuotePostAuthPlugin.php` |
| `UpdateGuestQuoteToCustomerQuotePostAuthPlugin` | CartsRestApi | `src/Spryker/CartsRestApi/src/Spryker/Zed/CartsRestApi/Communication/Plugin/AuthRestApi/UpdateGuestQuoteToCustomerQuotePostAuthPlugin.php` |

**Pyz Registration:** `src/Pyz/AuthRestApi/src/Pyz/Zed/AuthRestApi/AuthRestApiDependencyProvider.php`
- Method: `getPostAuthPlugins()` — only `UpdateGuestQuoteToCustomerQuotePostAuthPlugin` registered

---

### 2. CartCodesRestApiExtension

#### Glue: `DiscountMapperPluginInterface`
- **Interface:** `src/Spryker/CartCodesRestApiExtension/src/Spryker/Glue/CartCodesRestApiExtension/Dependency/Plugin/DiscountMapperPluginInterface.php`

| Plugin | Module | File |
|--------|--------|------|
| `DiscountPromotionDiscountMapperPlugin` | DiscountPromotionsRestApi | `src/Spryker/DiscountPromotionsRestApi/src/Spryker/Glue/DiscountPromotionsRestApi/Plugin/CartCodesRestApi/DiscountPromotionDiscountMapperPlugin.php` |

**Pyz Registration:** `src/Pyz/CartCodesRestApi/src/Pyz/Glue/CartCodesRestApi/CartCodesRestApiDependencyProvider.php`
- Method: `getDiscountMapperPlugins()`

---

### 3. CartReorderRestApiExtension

#### Glue: `CartReorderRequestExpanderPluginInterface`
- **Interface:** `src/Spryker/CartReorderRestApiExtension/src/Spryker/Glue/CartReorderRestApiExtension/Dependency/Plugin/CartReorderRequestExpanderPluginInterface.php`

| Plugin | Module | File |
|--------|--------|------|
| `CompanyUserCompanyCartReorderRequestExpanderPlugin` | CompaniesRestApi | `src/Spryker/CompaniesRestApi/src/Spryker/Glue/CompaniesRestApi/Plugin/CartReorderRestApi/CompanyUserCompanyCartReorderRequestExpanderPlugin.php` |
| `CompanyUserCompanyBusinessUnitCartReorderRequestExpanderPlugin` | CompanyBusinessUnitsRestApi | `src/Spryker/CompanyBusinessUnitsRestApi/src/Spryker/Glue/CompanyBusinessUnitsRestApi/Plugin/CartReorderRestApi/CompanyUserCompanyBusinessUnitCartReorderRequestExpanderPlugin.php` |

**Pyz Registration:** `src/Pyz/CartReorderRestApi/src/Pyz/Glue/CartReorderRestApi/CartReorderRestApiDependencyProvider.php`
- Method: `getCartReorderRequestExpanderPlugins()`

#### Glue: `RestCartReorderAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/CartReorderRestApiExtension/src/Spryker/Glue/CartReorderRestApiExtension/Dependency/Plugin/RestCartReorderAttributesMapperPluginInterface.php`

| Plugin | Module | File |
|--------|--------|------|
| `OrderAmendmentRestCartReorderAttributesMapperPlugin` | OrderAmendmentsRestApi | `src/Spryker/OrderAmendmentsRestApi/src/Spryker/Glue/OrderAmendmentsRestApi/Plugin/CartReorderRestApi/OrderAmendmentRestCartReorderAttributesMapperPlugin.php` |

**Pyz Registration:** `src/Pyz/CartReorderRestApi/src/Pyz/Glue/CartReorderRestApi/CartReorderRestApiDependencyProvider.php`
- Method: `getRestCartReorderAttributesMapperPlugins()`

#### Glue: `RestCartReorderAttributesValidatorPluginInterface`
- **Interface:** `src/Spryker/CartReorderRestApiExtension/src/Spryker/Glue/CartReorderRestApiExtension/Dependency/Plugin/RestCartReorderAttributesValidatorPluginInterface.php`
- **No implementations found**

---

### 4. CartsRestApiExtension

#### Glue: `CartItemExpanderPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Glue/CartsRestApiExtension/Dependency/Plugin/CartItemExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `MerchantProductOfferCartItemExpanderPlugin` | MerchantProductOffersRestApi |
| `MerchantProductCartItemExpanderPlugin` | MerchantProductsRestApi |
| `DiscountPromotionCartItemExpanderPlugin` | DiscountPromotionsRestApi |
| `SalesUnitCartItemExpanderPlugin` | ProductMeasurementUnitsRestApi |
| `ProductConfigurationCartItemExpanderPlugin` | ProductConfigurationsRestApi |
| `ProductOptionCartItemExpanderPlugin` | ProductOptionsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Glue/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getCartItemExpanderPlugins()`

#### Glue: `CartItemFilterPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Glue/CartsRestApiExtension/Dependency/Plugin/CartItemFilterPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductBundleCartItemFilterPlugin` | ProductBundleCartsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Glue/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getCartItemFilterPlugins()`

#### Glue: `CustomerExpanderPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Glue/CartsRestApiExtension/Dependency/Plugin/CustomerExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CompanyUserCustomerExpanderPlugin` | CompanyUsersRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Glue/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getCustomerExpanderPlugins()`

#### Glue: `QuoteCollectionReaderPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Glue/CartsRestApiExtension/Dependency/Plugin/QuoteCollectionReaderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CartQuoteCollectionReaderPlugin` | CartsRestApi |

**Pyz Registration:** Not registered in Pyz

#### Glue: `QuoteCreatorPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Glue/CartsRestApiExtension/Dependency/Plugin/QuoteCreatorPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `SingleQuoteCreatorPlugin` | CartsRestApi |

**Pyz Registration:** Not registered in Pyz

#### Glue: `RestCartAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Glue/CartsRestApiExtension/Dependency/Plugin/RestCartAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `SalesOrderThresholdRestCartAttributesMapperPlugin` | SalesOrderThresholdsRestApi |
| `OrderAmendmentRestCartAttributesMapperPlugin` | OrderAmendmentsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Glue/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getRestCartAttributesMapperPlugins()`

#### Glue: `RestCartItemsAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Glue/CartsRestApiExtension/Dependency/Plugin/RestCartItemsAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `MerchantProductOfferRestCartItemsAttributesMapperPlugin` | MerchantProductOffersRestApi |
| `ProductConfigurationRestCartItemsAttributesMapperPlugin` | ProductConfigurationsRestApi |
| `SalesUnitsRestCartItemsAttributesMapperPlugin` | ProductMeasurementUnitsRestApi |
| `ConfiguredBundleItemsAttributesMapperPlugin` | ConfigurableBundleCartsRestApi |
| `ProductOptionRestCartItemsAttributesMapperPlugin` | ProductOptionsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Glue/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getRestCartItemsAttributesMapperPlugins()`

#### Zed: `CartItemMapperPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Zed/CartsRestApiExtension/Dependency/Plugin/CartItemMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `MerchantProductOfferCartItemMapperPlugin` | MerchantProductOffersRestApi |
| `DiscountPromotionCartItemMapperPlugin` | DiscountPromotionsRestApi |
| `ProductConfigurationCartItemMapperPlugin` | ProductConfigurationsRestApi |
| `SalesUnitCartItemMapperPlugin` | ProductMeasurementUnitsRestApi |
| `ProductOptionCartItemMapperPlugin` | ProductOptionsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Zed/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getCartItemMapperPlugins()`

#### Zed: `QuoteCollectionExpanderPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Zed/CartsRestApiExtension/Dependency/Plugin/QuoteCollectionExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `SharedCartQuoteCollectionExpanderPlugin` | SharedCart |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Zed/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getQuoteCollectionExpanderPlugins()`

#### Zed: `QuoteCreatorPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Zed/CartsRestApiExtension/Dependency/Plugin/QuoteCreatorPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `QuoteCreatorPlugin` | PersistentCart |
| `QuoteCreatorPlugin` | CartsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Zed/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getQuoteCreatorPlugin()` — returns `QuoteCreatorPlugin` from PersistentCart

#### Zed: `QuoteExpanderPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Zed/CartsRestApiExtension/Dependency/Plugin/QuoteExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CustomerCompanyUserQuoteExpanderPlugin` | CompanyUsersRestApi |
| `QuotePermissionGroupQuoteExpanderPlugin` | SharedCartsRestApi |
| `SalesOrderThresholdQuoteExpanderPlugin` | SalesOrderThresholdsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Zed/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getQuoteExpanderPlugins()`

#### Zed: `QuoteItemReadValidatorPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Zed/CartsRestApiExtension/Dependency/Plugin/QuoteItemReadValidatorPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `BundleItemQuoteItemReadValidatorPlugin` | ProductBundleCartsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Zed/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getQuoteItemReadValidatorPlugins()`

#### Zed: `QuoteMergePersistentCartChangeExpanderPluginInterface`
- **Interface:** `src/Spryker/CartsRestApiExtension/src/Spryker/Zed/CartsRestApiExtension/Dependency/Plugin/QuoteMergePersistentCartChangeExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `BundleItemQuoteMergePersistentCartChangeExpanderPlugin` | ProductBundleCartsRestApi |

**Pyz Registration:** `src/Pyz/CartsRestApi/src/Pyz/Zed/CartsRestApi/CartsRestApiDependencyProvider.php`
- Method: `getQuoteMergePersistentCartChangeExpanderPlugins()`

---

### 5. CheckoutRestApiExtension

#### Glue: `CheckoutDataResponseMapperPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Glue/CheckoutRestApiExtension/Dependency/Plugin/CheckoutDataResponseMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `SelectedPaymentMethodCheckoutDataResponseMapperPlugin` | PaymentsRestApi |
| `ServicePointCheckoutDataResponseMapperPlugin` | ServicePointsRestApi |

**Pyz Registration:** `src/Pyz/CheckoutRestApi/src/Pyz/Glue/CheckoutRestApi/CheckoutRestApiDependencyProvider.php`
- Method: `getCheckoutDataResponseMapperPlugins()`

#### Glue: `CheckoutRequestAttributesValidatorPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Glue/CheckoutRestApiExtension/Dependency/Plugin/CheckoutRequestAttributesValidatorPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `BillingAddressCheckoutRequestAttributesValidatorPlugin` | CustomersRestApi |
| `ServicePointCheckoutRequestAttributesValidatorPlugin` | ServicePointsRestApi |

**Pyz Registration:** `src/Pyz/CheckoutRestApi/src/Pyz/Glue/CheckoutRestApi/CheckoutRestApiDependencyProvider.php`
- Method: `getCheckoutRequestAttributesValidatorPlugins()`

#### Glue: `CheckoutRequestExpanderPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Glue/CheckoutRestApiExtension/Dependency/Plugin/CheckoutRequestExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CompanyUserCheckoutRequestExpanderPlugin` | CompanyUsersRestApi |

**Pyz Registration:** `src/Pyz/CheckoutRestApi/src/Pyz/Glue/CheckoutRestApi/CheckoutRestApiDependencyProvider.php`
- Method: `getCheckoutRequestExpanderPlugins()`

#### Glue: `CheckoutRequestValidatorPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Glue/CheckoutRestApiExtension/Dependency/Plugin/CheckoutRequestValidatorPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ShipmentDataCheckoutRequestValidatorPlugin` | ShipmentsRestApi |

**Pyz Registration:** `src/Pyz/CheckoutRestApi/src/Pyz/Glue/CheckoutRestApi/CheckoutRestApiDependencyProvider.php`
- Method: `getCheckoutRequestValidatorPlugins()`

#### Glue: `CheckoutResponseMapperPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Glue/CheckoutRestApiExtension/Dependency/Plugin/CheckoutResponseMapperPluginInterface.php`
- **No implementations found**

#### Zed: `CheckoutDataExpanderPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Zed/CheckoutRestApiExtension/Dependency/Plugin/CheckoutDataExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ShipmentCheckoutDataExpanderPlugin` | ShipmentsRestApi |
| `ServicePointCheckoutDataExpanderPlugin` | ServicePointsRestApi |
| `CompanyBusinessUnitAddressCheckoutDataExpanderPlugin` | CompanyBusinessUnitAddressesRestApi |

**Pyz Registration:** `src/Pyz/CheckoutRestApi/src/Pyz/Zed/CheckoutRestApi/CheckoutRestApiDependencyProvider.php`
- Method: `getCheckoutDataExpanderPlugins()`

#### Zed: `CheckoutDataValidatorPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Zed/CheckoutRestApiExtension/Dependency/Plugin/CheckoutDataValidatorPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ShipmentMethodCheckoutDataValidatorPlugin` | ShipmentsRestApi |
| `ItemsCheckoutDataValidatorPlugin` | ShipmentsRestApi |
| `ShipmentTypeCheckoutDataValidatorPlugin` | ShipmentTypesRestApi |
| `CustomerAddressCheckoutDataValidatorPlugin` | CustomersRestApi |
| `CountryCheckoutDataValidatorPlugin` | Country |
| `CountriesCheckoutDataValidatorPlugin` | Country |
| `CompanyBusinessUnitAddressCheckoutDataValidatorPlugin` | CompanyBusinessUnitAddressesRestApi |
| `ClickAndCollectExampleReplaceCheckoutDataValidatorPlugin` | ClickAndCollectExample |

**Pyz Registration:** `src/Pyz/CheckoutRestApi/src/Pyz/Zed/CheckoutRestApi/CheckoutRestApiDependencyProvider.php`
- Method: `getCheckoutDataValidatorPlugins()` (also `getCheckoutDataValidatorPluginsForOrderAmendment()`)
- Note: `CountryCheckoutDataValidatorPlugin` not registered in Pyz (only `CountriesCheckoutDataValidatorPlugin` is)

#### Zed: `QuoteMapperPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Zed/CheckoutRestApiExtension/Dependency/Plugin/QuoteMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ShipmentsQuoteMapperPlugin` | ShipmentsRestApi |
| `ShipmentQuoteMapperPlugin` | ShipmentsRestApi |
| `ShipmentTypeServicePointQuoteMapperPlugin` | ShipmentTypeServicePointsRestApi |
| `ServicePointQuoteMapperPlugin` | ServicePointsRestApi |
| `ReplaceServicePointQuoteItemsQuoteMapperPlugin` | ServicePointCartsRestApi |
| `PaymentsQuoteMapperPlugin` | PaymentsRestApi |
| `CustomerQuoteMapperPlugin` | CustomersRestApi |
| `AddressQuoteMapperPlugin` | CustomersRestApi |
| `CompanyUserQuoteMapperPlugin` | CompanyUsersRestApi |
| `CompanyBusinessUnitAddressQuoteMapperPlugin` | CompanyBusinessUnitAddressesRestApi |

**Pyz Registration:** `src/Pyz/CheckoutRestApi/src/Pyz/Zed/CheckoutRestApi/CheckoutRestApiDependencyProvider.php`
- Method: `getQuoteMapperPlugins()`

#### Zed: `ReadCheckoutDataValidatorPluginInterface`
- **Interface:** `src/Spryker/CheckoutRestApiExtension/src/Spryker/Zed/CheckoutRestApiExtension/Dependency/Plugin/ReadCheckoutDataValidatorPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ItemsReadCheckoutDataValidatorPlugin` | ShipmentsRestApi |
| `ShipmentTypeReadCheckoutDataValidatorPlugin` | ShipmentTypesRestApi |
| `SalesOrderThresholdReadCheckoutDataValidatorPlugin` | SalesOrderThresholdsRestApi |
| `ClickAndCollectExampleReplaceReadCheckoutDataValidatorPlugin` | ClickAndCollectExample |

**Pyz Registration:** `src/Pyz/CheckoutRestApi/src/Pyz/Zed/CheckoutRestApi/CheckoutRestApiDependencyProvider.php`
- Method: `getReadCheckoutDataValidatorPlugins()`

---

### 6. CompanyBusinessUnitsRestApiExtension

#### Glue: `CompanyBusinessUnitMapperPluginInterface`
- **Interface:** `src/Spryker/CompanyBusinessUnitsRestApiExtension/src/Spryker/Glue/CompanyBusinessUnitsRestApiExtension/Dependency/Plugin/CompanyBusinessUnitMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `DefaultBillingAddressMapperPlugin` | CompanyBusinessUnitAddressesRestApi |

**Pyz Registration:** `src/Pyz/CompanyBusinessUnitsRestApi/src/Pyz/Glue/CompanyBusinessUnitsRestApi/CompanyBusinessUnitsRestApiDependencyProvider.php`
- Method: `getCompanyBusinessUnitMapperPlugins()`

---

### 7. CustomersRestApiExtension

#### Glue: `CustomerExpanderPluginInterface`
- **Interface:** `src/Spryker/CustomersRestApiExtension/src/Spryker/Glue/CustomersRestApiExtension/Dependency/Plugin/CustomerExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CustomerProductListCustomerExpanderPlugin` | MerchantRelationshipProductListsRestApi |
| `CompanyUserCustomerExpanderPlugin` | CompanyUsersRestApi |
| `CompanyBusinessUnitCustomerExpanderPlugin` | CompanyBusinessUnitsRestApi |

**Pyz Registration:** `src/Pyz/CustomersRestApi/src/Pyz/Glue/CustomersRestApi/CustomersRestApiDependencyProvider.php`
- Method: `getCustomerExpanderPlugins()`

#### Glue: `CustomerPostCreatePluginInterface`
- **Interface:** `src/Spryker/CustomersRestApiExtension/src/Spryker/Glue/CustomersRestApiExtension/Dependency/Plugin/CustomerPostCreatePluginInterface.php`

| Plugin | Module |
|--------|--------|
| `UpdateCartCreateCustomerReferencePlugin` | CartsRestApi |

**Pyz Registration:** `src/Pyz/CustomersRestApi/src/Pyz/Glue/CustomersRestApi/CustomersRestApiDependencyProvider.php`
- Method: `getCustomerPostCreatePlugins()`

#### Glue: `CustomerPostRegisterPluginInterface`
- **Interface:** `src/Spryker/CustomersRestApiExtension/src/Spryker/Glue/CustomersRestApiExtension/Dependency/Plugin/CustomerPostRegisterPluginInterface.php`
- **No implementations found**

---

### 8. MerchantsRestApiExtension

#### Glue: `MerchantRestAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/MerchantsRestApiExtension/src/Spryker/Glue/MerchantsRestApiExtension/Dependency/Plugin/MerchantRestAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `MerchantCategoryMerchantRestAttributesMapperPlugin` | MerchantCategoriesRestApi |

**Pyz Registration:** `src/Pyz/MerchantsRestApi/src/Pyz/Glue/MerchantsRestApi/MerchantsRestApiDependencyProvider.php`
- Method: `getMerchantRestAttributesMapperPlugins()`

---

### 9. OrderPaymentsRestApiExtension

#### Zed: `OrderPaymentUpdaterPluginInterface`
- **Interface:** `src/Spryker/OrderPaymentsRestApiExtension/src/Spryker/Zed/OrderPaymentsRestApiExtension/Dependency/Plugin/OrderPaymentUpdaterPluginInterface.php`
- **No implementations found**

---

### 10. OrdersRestApiExtension

#### Glue: `RestOrderDetailsAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/OrdersRestApiExtension/src/Spryker/Glue/OrdersRestApiExtension/Dependency/Plugin/RestOrderDetailsAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ShipmentRestOrderDetailsAttributesMapperPlugin` | ShipmentsRestApi |
| `BundleItemRestOrderDetailsAttributesMapperPlugin` | ProductBundlesRestApi |

**Pyz Registration:** `src/Pyz/OrdersRestApi/src/Pyz/Glue/OrdersRestApi/OrdersRestApiDependencyProvider.php`
- Method: `getRestOrderDetailsAttributesMapperPlugins()`

#### Glue: `RestOrderItemsAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/OrdersRestApiExtension/src/Spryker/Glue/OrdersRestApiExtension/Dependency/Plugin/RestOrderItemsAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductOptionRestOrderItemsAttributesMapperPlugin` | ProductOptionsRestApi |
| `SalesUnitRestOrderItemsAttributesMapperPlugin` | ProductMeasurementUnitsRestApi |
| `ProductConfigurationRestOrderItemsAttributesMapperPlugin` | ProductConfigurationsRestApi |
| `OmsRestOrderItemsAttributesMapperPlugin` | OmsRestApi |
| `SalesConfiguredBundleRestOrderItemsAttributesMapperPlugin` | ConfigurableBundlesRestApi |

**Pyz Registration:** `src/Pyz/OrdersRestApi/src/Pyz/Glue/OrdersRestApi/OrdersRestApiDependencyProvider.php`
- Method: `getRestOrderItemsAttributesMapperPlugins()`

---

### 11. ProductOfferPricesRestApiExtension

#### Glue: `RestProductOfferPricesAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/ProductOfferPricesRestApiExtension/src/Spryker/Glue/ProductOfferPricesRestApiExtension/Dependency/Plugin/RestProductOfferPricesAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `RestProductOfferPricesAttributesMapperPlugin` | PriceProductOfferVolumesRestApi |

**Pyz Registration:** `src/Pyz/ProductOfferPricesRestApi/src/Pyz/Glue/ProductOfferPricesRestApi/ProductOfferPricesRestApiDependencyProvider.php`
- Method: `getRestProductOfferPricesAttributesMapperPlugins()`

---

### 12. ProductPricesRestApiExtension

#### Glue: `RestProductPricesAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/ProductPricesRestApiExtension/src/Spryker/Glue/ProductPricesRestApiExtension/Dependency/Plugin/RestProductPricesAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `PriceProductVolumeRestProductPricesAttributesMapperPlugin` | PriceProductVolumesRestApi |

**Pyz Registration:** `src/Pyz/ProductPricesRestApi/src/Pyz/Glue/ProductPricesRestApi/ProductPricesRestApiDependencyProvider.php`
- Method: `getRestProductPricesAttributesMapperPlugins()`

---

### 13. ProductsRestApiExtension

#### Glue: `AbstractProductsResourceExpanderPluginInterface`
- **Interface:** `src/Spryker/ProductsRestApiExtension/src/Spryker/Glue/ProductsRestApiExtension/Dependency/Plugin/AbstractProductsResourceExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductReviewsAbstractProductsResourceExpanderPlugin` | ProductReviewsRestApi |
| `MultiSelectAttributeAbstractProductsResourceExpanderPlugin` | ProductAttributesRestApi |

**Pyz Registration:** `src/Pyz/ProductsRestApi/src/Pyz/Glue/ProductsRestApi/ProductsRestApiDependencyProvider.php`
- Method: `getAbstractProductsResourceExpanderPlugins()`

#### Glue: `ConcreteProductsResourceExpanderPluginInterface`
- **Interface:** `src/Spryker/ProductsRestApiExtension/src/Spryker/Glue/ProductsRestApiExtension/Dependency/Plugin/ConcreteProductsResourceExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductReviewsConcreteProductsResourceExpanderPlugin` | ProductReviewsRestApi |
| `ProductDiscontinuedConcreteProductsResourceExpanderPlugin` | ProductDiscontinuedRestApi |
| `ProductConfigurationConcreteProductsResourceExpanderPlugin` | ProductConfigurationsRestApi |
| `MultiSelectAttributeConcreteProductsResourceExpanderPlugin` | ProductAttributesRestApi |

**Pyz Registration:** `src/Pyz/ProductsRestApi/src/Pyz/Glue/ProductsRestApi/ProductsRestApiDependencyProvider.php`
- Method: `getConcreteProductsResourceExpanderPlugins()`

---

### 14. QuoteRequestsRestApiExtension

#### Glue: `RestQuoteRequestAttributesExpanderPluginInterface`
- **Interface:** `src/Spryker/QuoteRequestsRestApiExtension/src/Spryker/Glue/QuoteRequestsRestApiExtension/Dependency/Plugin/RestQuoteRequestAttributesExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ShipmentsRestQuoteRequestAttributesExpanderPlugin` | ShipmentsRestApi |
| `ProductOptionsRestQuoteRequestAttributesExpanderPlugin` | ProductOptionsRestApi |
| `SalesUnitRestQuoteRequestAttributesExpanderPlugin` | ProductMeasurementUnitsRestApi |
| `MerchantProductOffersRestQuoteRequestAttributesExpanderPlugin` | MerchantProductOffersRestApi |
| `DiscountsRestQuoteRequestAttributesExpanderPlugin` | DiscountsRestApi |
| `ConfiguredBundleRestQuoteRequestAttributesExpanderPlugin` | ConfigurableBundlesRestApi |

**Pyz Registration:** `src/Pyz/QuoteRequestsRestApi/src/Pyz/Glue/QuoteRequestsRestApi/QuoteRequestsRestApiDependencyProvider.php`
- Method: `getRestQuoteRequestAttributesExpanderPlugins()`

---

### 15. SharedCartsRestApiExtension

#### Glue: `CompanyUserProviderPluginInterface`
- **Interface:** `src/Spryker/SharedCartsRestApiExtension/src/Spryker/Glue/SharedCartsRestApiExtension/Dependency/Plugin/CompanyUserProviderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CompanyUserStorageProviderPlugin` | CompanyUserStorage |

**Pyz Registration:** `src/Pyz/SharedCartsRestApi/src/Pyz/Glue/SharedCartsRestApi/SharedCartsRestApiDependencyProvider.php`
- Method: `getCompanyUserProviderPlugin()`

---

### 16. ShoppingListsRestApiExtension

#### Glue: `RestShoppingListItemsAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/ShoppingListsRestApiExtension/src/Spryker/Glue/ShoppingListsRestApiExtension/Dependency/Plugin/RestShoppingListItemsAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductConfigurationRestShoppingListItemsAttributesMapperPlugin` | ProductConfigurationShoppingListsRestApi |

**Pyz Registration:** `src/Pyz/ShoppingListsRestApi/src/Pyz/Glue/ShoppingListsRestApi/ShoppingListsRestApiDependencyProvider.php`
- Method: `getRestShoppingListItemsAttributesMapperPlugins()`

#### Glue: `ShoppingListItemRequestMapperPluginInterface`
- **Interface:** `src/Spryker/ShoppingListsRestApiExtension/src/Spryker/Glue/ShoppingListsRestApiExtension/Dependency/Plugin/ShoppingListItemRequestMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductConfigurationShoppingListItemRequestMapperPlugin` | ProductConfigurationShoppingListsRestApi |

**Pyz Registration:** `src/Pyz/ShoppingListsRestApi/src/Pyz/Glue/ShoppingListsRestApi/ShoppingListsRestApiDependencyProvider.php`
- Method: `getShoppingListItemRequestMapperPlugins()`

---

### 17. UrlsRestApiExtension

#### Glue: `RestUrlResolverAttributesTransferProviderPluginInterface`
- **Interface:** `src/Spryker/UrlsRestApiExtension/src/Spryker/Glue/UrlsRestApiExtension/Dependency/Plugin/RestUrlResolverAttributesTransferProviderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductAbstractRestUrlResolverAttributesTransferProviderPlugin` | ProductsRestApi |
| `MerchantRestUrlResolverAttributesTransferProviderPlugin` | MerchantsRestApi |
| `CmsPageRestUrlResolverAttributesTransferProviderPlugin` | CmsPagesRestApi |
| `CategoryNodeRestUrlResolverAttributesTransferProviderPlugin` | CategoriesRestApi |

**Pyz Registration:** `src/Pyz/UrlsRestApi/src/Pyz/Glue/UrlsRestApi/UrlsRestApiDependencyProvider.php`
- Method: `getRestUrlResolverAttributesTransferProviderPlugins()`

---

### 18. WishlistsRestApiExtension

#### Glue: `RestWishlistItemsAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/WishlistsRestApiExtension/src/Spryker/Glue/WishlistsRestApiExtension/Dependency/Plugin/RestWishlistItemsAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductPriceRestWishlistItemsAttributesMapperPlugin` | ProductPricesRestApi |
| `ProductConfigurationRestWishlistItemsAttributesMapperPlugin` | ProductConfigurationWishlistsRestApi |
| `ProductAvailabilityRestWishlistItemsAttributesMapperPlugin` | ProductAvailabilitiesRestApi |
| `ProductOfferRestWishlistItemsAttributesMapperPlugin` | MerchantProductOfferWishlistRestApi |

**Pyz Registration:** `src/Pyz/WishlistsRestApi/src/Pyz/Glue/WishlistsRestApi/WishlistsRestApiDependencyProvider.php`
- Method: `getRestWishlistItemsAttributesMapperPlugins()`

#### Glue: `WishlistItemRequestMapperPluginInterface`
- **Interface:** `src/Spryker/WishlistsRestApiExtension/src/Spryker/Glue/WishlistsRestApiExtension/Dependency/Plugin/WishlistItemRequestMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductConfigurationWishlistItemRequestMapperPlugin` | ProductConfigurationWishlistsRestApi |

**Pyz Registration:** `src/Pyz/WishlistsRestApi/src/Pyz/Glue/WishlistsRestApi/WishlistsRestApiDependencyProvider.php`
- Method: `getWishlistItemRequestMapperPlugins()`

#### Zed: `RestWishlistItemsAttributesDeleteStrategyPluginInterface`
- **Interface:** `src/Spryker/WishlistsRestApiExtension/src/Spryker/Zed/WishlistsRestApiExtension/Dependency/Plugin/RestWishlistItemsAttributesDeleteStrategyPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductConfigurationRestWishlistItemsAttributesDeleteStrategyPlugin` | ProductConfigurationWishlistsRestApi |
| `ProductOfferRestWishlistItemsAttributesDeleteStrategyPlugin` | MerchantProductOfferWishlistRestApi |
| `EmptyProductOfferRestWishlistItemsAttributesDeleteStrategyPlugin` | MerchantProductOfferWishlistRestApi |

**Pyz Registration:** `src/Pyz/WishlistsRestApi/src/Pyz/Zed/WishlistsRestApi/WishlistsRestApiDependencyProvider.php`
- Method: `getRestWishlistItemsAttributesDeleteStrategyPlugins()`
- Note: `EmptyProductOfferRestWishlistItemsAttributesDeleteStrategyPlugin` is NOT registered in Pyz

#### Zed: `RestWishlistItemsAttributesUpdateStrategyPluginInterface`
- **Interface:** `src/Spryker/WishlistsRestApiExtension/src/Spryker/Zed/WishlistsRestApiExtension/Dependency/Plugin/RestWishlistItemsAttributesUpdateStrategyPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductConfigurationRestWishlistItemsAttributesUpdateStrategyPlugin` | ProductConfigurationWishlistsRestApi |

**Pyz Registration:** `src/Pyz/WishlistsRestApi/src/Pyz/Zed/WishlistsRestApi/WishlistsRestApiDependencyProvider.php`
- Method: `getRestWishlistItemsAttributesUpdateStrategyPlugins()`

---

### 19. ShipmentsRestApiExtension

#### Glue: `AddressSourceCheckerPluginInterface`
- **Interface:** `src/Spryker/ShipmentsRestApiExtension/src/Spryker/Glue/ShipmentsRestApiExtension/Dependency/Plugin/AddressSourceCheckerPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CustomerAddressSourceCheckerPlugin` | CustomersRestApi |
| `CompanyBusinessUnitAddressSourceCheckerPlugin` | CompanyBusinessUnitAddressesRestApi |

**Pyz Registration:** `src/Pyz/ShipmentsRestApi/src/Pyz/Glue/ShipmentsRestApi/ShipmentsRestApiDependencyProvider.php`
- Method: `getAddressSourceCheckerPlugins()`

#### Glue: `RestAddressResponseMapperPluginInterface`
- **Interface:** `src/Spryker/ShipmentsRestApiExtension/src/Spryker/Glue/ShipmentsRestApiExtension/Dependency/Plugin/RestAddressResponseMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CompanyBusinessUnitUuidRestAddressResponseMapperPlugin` | CompanyBusinessUnitAddressesRestApi |

**Pyz Registration:** `src/Pyz/ShipmentsRestApi/src/Pyz/Glue/ShipmentsRestApi/ShipmentsRestApiDependencyProvider.php`
- Method: `getRestAddressResponseMapperPlugins()`

#### Glue: `ShippingAddressValidationStrategyPluginInterface`
- **Interface:** `src/Spryker/ShipmentsRestApiExtension/src/Spryker/Glue/ShipmentsRestApiExtension/Dependency/Plugin/ShippingAddressValidationStrategyPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `SingleShipmentTypeServicePointShippingAddressValidationStrategyPlugin` | ShipmentTypeServicePointsRestApi |
| `MultiShipmentTypeServicePointShippingAddressValidationStrategyPlugin` | ShipmentTypeServicePointsRestApi |

**Pyz Registration:** `src/Pyz/ShipmentsRestApi/src/Pyz/Glue/ShipmentsRestApi/ShipmentsRestApiDependencyProvider.php`
- Method: `getShippingAddressValidationStrategyPlugins()`

#### Zed: `AddressProviderStrategyPluginInterface`
- **Interface:** `src/Spryker/ShipmentsRestApiExtension/src/Spryker/Zed/ShipmentsRestApiExtension/Dependency/Plugin/AddressProviderStrategyPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CustomerAddressProviderStrategyPlugin` | CustomersRestApi |
| `CompanyBusinessUnitAddressProviderStrategyPlugin` | CompanyBusinessUnitAddressesRestApi |

**Pyz Registration:** `src/Pyz/ShipmentsRestApi/src/Pyz/Zed/ShipmentsRestApi/ShipmentsRestApiDependencyProvider.php`
- Method: `getAddressProviderStrategyPlugins()`

#### Zed: `QuoteItemExpanderPluginInterface`
- **Interface:** `src/Spryker/ShipmentsRestApiExtension/src/Spryker/Zed/ShipmentsRestApiExtension/Dependency/Plugin/QuoteItemExpanderPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ShipmentTypeQuoteItemExpanderPlugin` | ShipmentTypesRestApi |
| `CopyShipmentToProductBundleQuoteItemExpanderPlugin` | ProductBundleCartsRestApi |
| `MerchantReferenceQuoteItemExpanderPlugin` | MerchantShipmentsRestApi |

**Pyz Registration:** `src/Pyz/ShipmentsRestApi/src/Pyz/Zed/ShipmentsRestApi/ShipmentsRestApiDependencyProvider.php`
- Method: `getQuoteItemExpanderPlugins()`

---

### 20. OauthBackendApiExtension

#### Glue: `UserRequestValidationPreCheckerPluginInterface`
- **Interface:** `src/Spryker/OauthBackendApiExtension/src/Spryker/Glue/OauthBackendApiExtension/Dependency/Plugin/UserRequestValidationPreCheckerPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `WarehouseUserRequestValidationPreCheckerPlugin` | WarehouseOauthBackendApi |

**Pyz Registration:** `src/Pyz/OauthBackendApi/src/Pyz/Glue/OauthBackendApi/OauthBackendApiDependencyProvider.php`
- Method: `getUserRequestValidationPreCheckerPlugins()`

---

### 21. PickingListsBackendApiExtension

#### Glue: `PickingListItemsBackendApiAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/PickingListsBackendApiExtension/src/Spryker/Glue/PickingListsBackendApiExtension/Dependency/Plugin/PickingListItemsBackendApiAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `ProductPackagingUnitPickingListItemsBackendApiAttributesMapperPlugin` | ProductPackagingUnitsBackendApi |

**Pyz Registration:** `src/Pyz/PickingListsBackendApi/src/Pyz/Glue/PickingListsBackendApi/PickingListsBackendApiDependencyProvider.php`
- Method: `getPickingListItemsBackendApiAttributesMapperPlugins()`

---

### 22. SalesOrdersBackendApiExtension

#### Glue: `OrdersBackendApiAttributesMapperPluginInterface`
- **Interface:** `src/Spryker/SalesOrdersBackendApiExtension/src/Spryker/Glue/SalesOrdersBackendApiExtension/Dependency/Plugin/OrdersBackendApiAttributesMapperPluginInterface.php`

| Plugin | Module |
|--------|--------|
| `CartNoteOrdersBackendApiAttributesMapperPlugin` | CartNotesBackendApi |

**Pyz Registration:** `src/Pyz/SalesOrdersBackendApi/src/Pyz/Glue/SalesOrdersBackendApi/SalesOrdersBackendApiDependencyProvider.php`
- Method: `getOrdersBackendApiAttributesMapperPlugins()`

---

### 23. ProductConfigurationsRestApiExtension

#### Glue: `ProductConfigurationPriceMapperPluginInterface`
- **Interface:** `src/Spryker/ProductConfigurationsRestApiExtension/src/Spryker/Glue/ProductConfigurationsRestApiExtension/Dependency/Plugin/ProductConfigurationPriceMapperPluginInterface.php`

| Plugin | Module | Variant |
|--------|--------|---------|
| `ProductConfigurationVolumePriceProductConfigurationPriceMapperPlugin` | ProductConfigurationsPriceProductVolumesRestApi | ProductConfigurationsRestApi |
| `ProductConfigurationVolumePriceProductConfigurationPriceMapperPlugin` | ProductConfigurationsPriceProductVolumesRestApi | ProductConfigurationWishlistsRestApi |
| `ProductConfigurationVolumePriceProductConfigurationPriceMapperPlugin` | ProductConfigurationsPriceProductVolumesRestApi | ProductConfigurationShoppingListsRestApi |

**Pyz Registration (3 DependencyProviders):**
- `src/Pyz/ProductConfigurationsRestApi/src/Pyz/Glue/ProductConfigurationsRestApi/ProductConfigurationsRestApiDependencyProvider.php` -> `getProductConfigurationPriceMapperPlugins()`
- `src/Pyz/ProductConfigurationWishlistsRestApi/src/Pyz/Glue/ProductConfigurationWishlistsRestApi/ProductConfigurationWishlistsRestApiDependencyProvider.php` -> `getProductConfigurationPriceMapperPlugins()`
- `src/Pyz/ProductConfigurationShoppingListsRestApi/src/Pyz/Glue/ProductConfigurationShoppingListsRestApi/ProductConfigurationShoppingListsRestApiDependencyProvider.php` -> `getProductConfigurationPriceMapperPlugins()`

#### Glue: `RestProductConfigurationPriceMapperPluginInterface`
- **Interface:** `src/Spryker/ProductConfigurationsRestApiExtension/src/Spryker/Glue/ProductConfigurationsRestApiExtension/Dependency/Plugin/RestProductConfigurationPriceMapperPluginInterface.php`

| Plugin | Module | Variant |
|--------|--------|---------|
| `ProductConfigurationVolumePriceRestProductConfigurationPriceMapperPlugin` | ProductConfigurationsPriceProductVolumesRestApi | ProductConfigurationsRestApi |
| `ProductConfigurationVolumePriceRestProductConfigurationPriceMapperPlugin` | ProductConfigurationsPriceProductVolumesRestApi | ProductConfigurationWishlistsRestApi |
| `ProductConfigurationVolumePriceRestProductConfigurationPriceMapperPlugin` | ProductConfigurationsPriceProductVolumesRestApi | ProductConfigurationShoppingListsRestApi |

**Pyz Registration (3 DependencyProviders):**
- `src/Pyz/ProductConfigurationsRestApi/src/Pyz/Glue/ProductConfigurationsRestApi/ProductConfigurationsRestApiDependencyProvider.php` -> `getRestProductConfigurationPriceMapperPlugins()`
- `src/Pyz/ProductConfigurationWishlistsRestApi/src/Pyz/Glue/ProductConfigurationWishlistsRestApi/ProductConfigurationWishlistsRestApiDependencyProvider.php` -> `getRestProductConfigurationPriceMapperPlugins()`
- `src/Pyz/ProductConfigurationShoppingListsRestApi/src/Pyz/Glue/ProductConfigurationShoppingListsRestApi/ProductConfigurationShoppingListsRestApiDependencyProvider.php` -> `getRestProductConfigurationPriceMapperPlugins()`

---

## Interfaces With No Implementations

| Extension Module | Interface | Notes |
|-----------------|-----------|-------|
| CartReorderRestApiExtension | `RestCartReorderAttributesValidatorPluginInterface` | No plugins exist |
| CheckoutRestApiExtension | `CheckoutResponseMapperPluginInterface` | No plugins exist |
| CustomersRestApiExtension | `CustomerPostRegisterPluginInterface` | No plugins exist |
| OrderPaymentsRestApiExtension | `OrderPaymentUpdaterPluginInterface` | No plugins exist |

## Plugins Not Registered in Pyz

| Plugin | Module | Extension Interface |
|--------|--------|-------------------|
| `AddGuestQuoteItemsToCustomerQuotePostAuthPlugin` | CartsRestApi | `PostAuthPluginInterface` |
| `CartQuoteCollectionReaderPlugin` | CartsRestApi | `QuoteCollectionReaderPluginInterface` |
| `SingleQuoteCreatorPlugin` | CartsRestApi | `QuoteCreatorPluginInterface` (Glue) |
| `CountryCheckoutDataValidatorPlugin` | Country | `CheckoutDataValidatorPluginInterface` |
| `EmptyProductOfferRestWishlistItemsAttributesDeleteStrategyPlugin` | MerchantProductOfferWishlistRestApi | `RestWishlistItemsAttributesDeleteStrategyPluginInterface` |

---

## Modules Without Extension Modules (89 of 112)

These API-providing modules do not have a corresponding `{Module}Extension` module with plugin interfaces. They are either self-contained or use extension points defined in other modules' Extension packages.
