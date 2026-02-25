# API-Providing Modules Catalog

This document catalogs all Spryker core modules (`src/Spryker/`) that provide API endpoints.
It serves as a reference for API Platform migration planning.

**Excluded:**
- Infrastructure modules (Api, ApiExtension, ApiQueryBuilder, DocumentationGeneratorRestApi, TestifyBackendApi)

## Glue RestAPI (StorefrontAPI) Modules

| Module | Endpoints | API Type |
|--------|-----------|----------|
| AgentAuthRestApi | POST /agent-access-tokens, POST /agent-customer-impersonation-access-tokens, GET /agent-customer-search | StorefrontAPI |
| AlternativeProductsRestApi | GET /abstract-products/{id}/related-products, GET /concrete-products/{id}/abstract-alternative-products, GET /concrete-products/{id}/concrete-alternative-products | StorefrontAPI |
| AuthRestApi | POST /access-tokens, POST /refresh-tokens, DELETE /refresh-tokens/{id} | StorefrontAPI |
| AvailabilityNotificationsRestApi | POST /availability-notifications, DELETE /availability-notifications/{id}, GET /my-availability-notifications, GET /customers/{id}/availability-notifications | StorefrontAPI |
| CartCodesRestApi | POST /carts/{id}/cart-codes, DELETE /carts/{id}/cart-codes/{id}, POST /guest-carts/{id}/cart-codes, DELETE /guest-carts/{id}/cart-codes/{id} | StorefrontAPI |
| CartPermissionGroupsRestApi | GET /cart-permission-groups, GET /cart-permission-groups/{id} | StorefrontAPI |
| CartReorderRestApi | POST /cart-reorder | StorefrontAPI |
| CartsRestApi | GET,POST /carts, GET,PATCH,DELETE /carts/{id}, POST /carts/{id}/items, PATCH,DELETE /carts/{id}/items/{id}, GET /guest-carts, GET,PATCH /guest-carts/{id}, POST /guest-carts/{id}/guest-cart-items, PATCH,DELETE /guest-carts/{id}/guest-cart-items/{id}, GET /customers/{id}/carts | StorefrontAPI |
| CatalogSearchRestApi | GET /catalog-search, GET /catalog-search-suggestions | StorefrontAPI |
| CategoriesRestApi | GET /category-trees, GET /category-nodes/{id} | StorefrontAPI |
| CheckoutRestApi | POST /checkout-data, POST /checkout | StorefrontAPI |
| CmsPagesRestApi | GET /cms-pages, GET /cms-pages/{id} | StorefrontAPI |
| CompaniesRestApi | GET /companies, GET /companies/{id} | StorefrontAPI |
| CompanyBusinessUnitAddressesRestApi | GET /company-business-unit-addresses, GET /company-business-unit-addresses/{id} | StorefrontAPI |
| CompanyBusinessUnitsRestApi | GET /company-business-units, GET /company-business-units/{id} | StorefrontAPI |
| CompanyRolesRestApi | GET /company-roles, GET /company-roles/{id} | StorefrontAPI |
| CompanyUserAuthRestApi | POST /company-user-access-tokens | StorefrontAPI |
| CompanyUsersRestApi | GET /company-users, GET /company-users/{id} | StorefrontAPI |
| ConfigurableBundleCartsRestApi | POST /carts/{id}/configured-bundles, PATCH,DELETE /carts/{id}/configured-bundles/{id}, POST,PATCH,DELETE /guest-carts/{id}/guest-configured-bundles/{id} | StorefrontAPI |
| ConfigurableBundlesRestApi | GET /configurable-bundle-templates, GET /configurable-bundle-templates/{id} | StorefrontAPI |
| ContentBannersRestApi | GET /content-banners/{id} | StorefrontAPI |
| ContentProductAbstractListsRestApi | GET /content-product-abstract-lists/{id}, GET /content-product-abstract-lists/{id}/abstract-products | StorefrontAPI |
| CustomerAccessRestApi | GET /customer-access | StorefrontAPI |
| CustomersRestApi | GET,POST /customers, GET,PATCH,DELETE /customers/{id}, GET,POST /customers/{id}/addresses, GET,PATCH,DELETE /customers/{id}/addresses/{id}, POST /customer-forgotten-password, PATCH /customer-restore-password/{id}, PATCH /customer-password/{id}, POST /customer-confirmation | StorefrontAPI |
| DiscountsRestApi | POST /carts/{id}/vouchers, DELETE /carts/{id}/vouchers/{id}, POST /guest-carts/{id}/vouchers, DELETE /guest-carts/{id}/vouchers/{id} | StorefrontAPI |
| MerchantOpeningHoursRestApi | GET /merchants/{id}/merchant-opening-hours | StorefrontAPI |
| MerchantProductOffersRestApi | GET /concrete-products/{id}/product-offers, GET /product-offers/{id} | StorefrontAPI |
| MerchantsRestApi | GET /merchants, GET /merchants/{id}, GET /merchants/{id}/merchant-addresses | StorefrontAPI |
| NavigationsRestApi | GET /navigations/{id} | StorefrontAPI |
| OrderPaymentsRestApi | POST /order-payments | StorefrontAPI |
| OrdersRestApi | GET /orders, GET /orders/{id}, GET /customers/{id}/orders | StorefrontAPI |
| OauthApi | POST /token | StorefrontAPI |
| PaymentsRestApi | POST /payments, POST /payment-cancellations, POST /payment-customers | StorefrontAPI |
| ProductAttributesRestApi | GET /product-management-attributes, GET /product-management-attributes/{id} | StorefrontAPI |
| ProductAvailabilitiesRestApi | GET /abstract-products/{id}/abstract-product-availabilities, GET /concrete-products/{id}/concrete-product-availabilities | StorefrontAPI |
| ProductBundlesRestApi | GET /concrete-products/{id}/bundled-products | StorefrontAPI |
| ProductImageSetsRestApi | GET /abstract-products/{id}/abstract-product-image-sets, GET /concrete-products/{id}/concrete-product-image-sets | StorefrontAPI |
| ProductLabelsRestApi | GET /product-labels/{id} | StorefrontAPI |
| ProductMeasurementUnitsRestApi | GET /product-measurement-units/{id}, GET /concrete-products/{id}/sales-units | StorefrontAPI |
| ProductOfferAvailabilitiesRestApi | GET /product-offers/{id}/product-offer-availabilities | StorefrontAPI |
| ProductOfferPricesRestApi | GET /product-offers/{id}/product-offer-prices | StorefrontAPI |
| ProductOfferServicePointAvailabilitiesRestApi | POST /product-offer-service-point-availabilities | StorefrontAPI |
| ProductPricesRestApi | GET /abstract-products/{id}/abstract-product-prices, GET /concrete-products/{id}/concrete-product-prices | StorefrontAPI |
| ProductReviewsRestApi | GET,POST /abstract-products/{id}/product-reviews, GET /abstract-products/{id}/product-reviews/{id} | StorefrontAPI |
| ProductsRestApi | GET /abstract-products/{id}, GET /concrete-products/{id} | StorefrontAPI |
| ProductTaxSetsRestApi | GET /abstract-products/{id}/product-tax-sets | StorefrontAPI |
| QuoteRequestAgentsRestApi | GET,POST /agent-quote-requests, GET,PATCH /agent-quote-requests/{id}, POST /agent-quote-requests/{id}/agent-quote-request-cancel, POST /agent-quote-requests/{id}/agent-quote-request-revise, POST /agent-quote-requests/{id}/agent-quote-request-send-to-customer | StorefrontAPI |
| QuoteRequestsRestApi | GET,POST /quote-requests, GET,PATCH /quote-requests/{id}, POST /quote-requests/{id}/quote-request-cancel, POST /quote-requests/{id}/quote-request-revise, POST /quote-requests/{id}/quote-request-send-to-user, POST /quote-requests/{id}/quote-request-convert-to-quote | StorefrontAPI |
| RelatedProductsRestApi | GET /abstract-products/{id}/related-products | StorefrontAPI |
| SalesReturnsRestApi | GET /return-reasons, GET,POST /returns, GET /returns/{id} | StorefrontAPI |
| ServicePointsRestApi | GET /service-points, GET /service-points/{id}, GET /service-points/{id}/service-point-addresses/{id} | StorefrontAPI |
| SharedCartsRestApi | POST /carts/{id}/shared-carts, PATCH,DELETE /shared-carts/{id} | StorefrontAPI |
| ShipmentTypesRestApi | GET /shipment-types, GET /shipment-types/{id} | StorefrontAPI |
| ShoppingListsRestApi | GET,POST /shopping-lists, GET,PATCH,DELETE /shopping-lists/{id}, POST /shopping-lists/{id}/shopping-list-items, PATCH,DELETE /shopping-lists/{id}/shopping-list-items/{id} | StorefrontAPI |
| StoresRestApi | GET /stores, GET /stores/{id} | StorefrontAPI |
| StoresApi | GET /stores | StorefrontAPI |
| TaxAppRestApi | POST /tax-id-validate | StorefrontAPI |
| UpSellingProductsRestApi | GET /carts/{id}/up-selling-products, GET /guest-carts/{id}/up-selling-products | StorefrontAPI |
| UrlsRestApi | GET /url-resolver | StorefrontAPI |
| WishlistsRestApi | GET,POST /wishlists, GET,PATCH,DELETE /wishlists/{id}, POST /wishlists/{id}/wishlist-items, PATCH,DELETE /wishlists/{id}/wishlist-items/{id} | StorefrontAPI |

**Total: 57 modules**

## BackendAPI Modules

| Module | Endpoints | API Type |
|--------|-----------|----------|
| CategoriesBackendApi | GET,POST /categories, GET,PATCH /categories/{id} | BackendAPI |
| DynamicEntityBackendApi | GET,POST,PATCH,PUT /dynamic-entity/{entity-name} (~62 auto-generated entity endpoints) | BackendAPI |
| OauthBackendApi | POST /token | BackendAPI |
| PickingListsBackendApi | GET /picking-lists, GET /picking-lists/{id}, PATCH /picking-lists/{id}/picking-list-items/{id}, POST /start-picking | BackendAPI |
| ProductAttributesBackendApi | GET,POST /product-attributes, GET,PATCH /product-attributes/{id} | BackendAPI |
| ProductsBackendApi | GET,POST /product-abstract, DELETE,GET,PATCH /product-abstract/{id} | BackendAPI |
| PushNotificationsBackendApi | GET,POST /push-notification-providers, PATCH,DELETE /push-notification-providers/{id}, POST /push-notification-subscriptions | BackendAPI |
| ServicePointsBackendApi | GET,POST /service-points, GET,PATCH /service-points/{id}, GET,POST /service-point-addresses, PATCH /service-points/{id}/service-point-addresses/{id}, GET,POST /service-types, GET,PATCH /service-types/{id}, GET,POST /services, GET,PATCH /services/{id} | BackendAPI |
| ShipmentTypesBackendApi | GET,POST /shipment-types, GET,PATCH /shipment-types/{id} | BackendAPI |
| ProductImageSetsBackendApi | GET /concrete-product-image-sets | BackendAPI |
| SalesOrdersBackendApi | GET /sales-orders | BackendAPI |
| ShipmentsBackendApi | GET /sales-shipments | BackendAPI |
| StoresBackendApi | GET,POST,PATCH /stores | BackendAPI |
| UsersBackendApi | GET /users | BackendAPI |
| WarehouseOauthBackendApi | POST /warehouse-tokens | BackendAPI |
| WarehousesBackendApi | GET /warehouses | BackendAPI |
| WarehouseUsersBackendApi | GET,POST /warehouse-user-assignments, GET,PATCH,DELETE /warehouse-user-assignments/{id} | BackendAPI |

**Total: 17 modules**

## Extension-Only Glue RestAPI (StorefrontAPI) Modules

These modules do not register their own routes. They extend other modules via plugins (relationship plugins, expander plugins, mapper plugins, validator plugins, etc.).

| Module | Extends | Plugins |
|--------|---------|---------|
| DiscountPromotionsRestApi | CartsRestApi, CartCodesRestApi | `DiscountPromotionCartItemExpanderPlugin`, `DiscountPromotionDiscountMapperPlugin`, `PromotionItemByQuoteTransferResourceRelationshipPlugin` |
| EntityTagsRestApi | GlueApplication | `EntityTagRestRequestValidatorPlugin`, `EntityTagFormatResponseHeadersPlugin` |
| GiftCardsRestApi | GlueApplication | `GiftCardByQuoteResourceRelationshipPlugin` |
| MerchantCategoriesRestApi | MerchantsRestApi | `MerchantCategoryMerchantRestAttributesMapperPlugin` |
| MerchantProductOfferServicePointAvailabilitiesRestApi | *(transfer-only)* | No plugins — only transfer definitions |
| MerchantProductOfferShoppingListsRestApi | *(transfer-only)* | No plugins — only transfer definitions |
| MerchantProductOfferWishlistRestApi | WishlistsRestApi | `ProductOfferRestWishlistItemsAttributesMapperPlugin`, `ProductOfferRestWishlistItemsAttributesDeleteStrategyPlugin` |
| MerchantProductShoppingListsRestApi | *(transfer-only)* | No plugins — only transfer definitions |
| MerchantProductsRestApi | CartsRestApi | `MerchantProductCartItemExpanderPlugin` |
| MerchantRelationshipProductListsRestApi | CustomersRestApi | `CustomerProductListCustomerExpanderPlugin`, `CustomerProductListOauthCustomerIdentifierExpanderPlugin` |
| MerchantSalesReturnsRestApi | *(transfer-only)* | No plugins — only transfer definitions |
| MerchantShipmentsRestApi | ShipmentsRestApi | `MerchantReferenceQuoteItemExpanderPlugin` |
| MultiCartsRestApi | CartsRestApi | Validation configuration only (`carts.validation.yaml`) |
| OmsRestApi | OrdersRestApi | `OmsRestOrderItemsAttributesMapperPlugin` |
| OrderAmendmentsRestApi | OrdersRestApi, CartsRestApi, CartReorderRestApi | `OrderAmendmentsByOrderResourceRelationshipPlugin`, `OrderAmendmentRestCartAttributesMapperPlugin`, `OrderAmendmentRestCartReorderAttributesMapperPlugin` |
| PriceProductOfferVolumesRestApi | ProductOfferPricesRestApi | `RestProductOfferPricesAttributesMapperPlugin` |
| PriceProductVolumesRestApi | ProductPricesRestApi | `PriceProductVolumeRestProductPricesAttributesMapperPlugin` |
| ProductBundleCartsRestApi | CartsRestApi, ShipmentsRestApi | `BundleItemByQuoteResourceRelationshipPlugin`, `BundledItemByQuoteResourceRelationshipPlugin`, `ProductBundleCartItemFilterPlugin` |
| ProductConfigurationShoppingListsRestApi | ShoppingListsRestApi | `ProductConfigurationShoppingListItemRequestMapperPlugin`, `ProductConfigurationRestShoppingListItemsAttributesMapperPlugin` |
| ProductConfigurationsPriceProductVolumesRestApi | ProductConfigurationsRestApi, ProductConfigurationShoppingListsRestApi, ProductConfigurationWishlistsRestApi | `ProductConfigurationVolumePriceProductConfigurationPriceMapperPlugin` (multiple variants) |
| ProductConfigurationsRestApi | ProductsRestApi, CartsRestApi, OrdersRestApi | `ProductConfigurationConcreteProductsResourceExpanderPlugin`, `CartItemProductConfigurationRestRequestValidatorPlugin`, `ProductConfigurationRestOrderItemsAttributesMapperPlugin`, `ProductConfigurationCartItemExpanderPlugin` |
| ProductConfigurationWishlistsRestApi | WishlistsRestApi | `ProductConfigurationWishlistItemRequestMapperPlugin`, `ProductConfigurationRestWishlistItemsAttributesMapperPlugin` |
| ProductDiscontinuedRestApi | ProductsRestApi | `ProductDiscontinuedConcreteProductsResourceExpanderPlugin` |
| ProductOfferSalesRestApi | *(transfer-only)* | No plugins — only transfer definitions |
| ProductOfferShoppingListsRestApi | *(transfer-only)* | No plugins — only transfer definitions |
| ProductOffersRestApi | ProductsRestApi | `ProductOffersByProductOfferReferenceResourceRelationshipPlugin` (routes registered by MerchantProductOffersRestApi) |
| ProductOptionsRestApi | CartsRestApi, OrdersRestApi, ProductsRestApi, QuoteRequestsRestApi | `ProductOptionsByProductConcreteSkuResourceRelationshipPlugin`, `ProductOptionsByProductAbstractSkuResourceRelationshipPlugin`, `ProductOptionCartItemExpanderPlugin`, `ProductOptionRestOrderItemsAttributesMapperPlugin` |
| SalesOrderThresholdsRestApi | CartsRestApi, CheckoutRestApi | `SalesOrderThresholdRestCartAttributesMapperPlugin`, `SalesOrderThresholdReadCheckoutDataValidatorPlugin` |
| SecurityBlockerRestApi | GlueApplication | `SecurityBlockerCustomerRestRequestValidatorPlugin`, `SecurityBlockerAgentRestRequestValidatorPlugin`, `SecurityBlockerCustomerControllerAfterActionPlugin` |
| ServicePointCartsRestApi | CheckoutRestApi | `ReplaceServicePointQuoteItemsQuoteMapperPlugin` |
| ShipmentsRestApi | CheckoutRestApi, OrdersRestApi, QuoteRequestsRestApi | `ShipmentsByCheckoutDataResourceRelationshipPlugin`, `ShipmentMethodsByShipmentResourceRelationshipPlugin`, `OrderShipmentByOrderResourceRelationshipPlugin`, `ShipmentDataCheckoutRequestValidatorPlugin` |
| ShipmentTypeProductOfferServicePointAvailabilitiesRestApi | ProductOfferServicePointAvailabilitiesRestApi | Validation configuration only |
| ShipmentTypeServicePointsRestApi | CheckoutRestApi, ShipmentsRestApi, ShipmentTypesRestApi | `ServiceTypeByShipmentTypesResourceRelationshipPlugin`, `ShipmentTypeServicePointCheckoutRequestExpanderPlugin` |

**Total: 34 modules**

## Extension-Only BackendAPI Modules

| Module | Extends | Plugins |
|--------|---------|---------|
| CartNotesBackendApi | SalesOrdersBackendApi | `CartNoteOrdersBackendApiAttributesMapperPlugin` |
| PickingListsUsersBackendApi | PickingListsBackendApi | `UsersByPickingListsBackendResourceRelationshipPlugin` |
| PickingListsWarehousesBackendApi | PickingListsBackendApi | `WarehousesByPickingListsBackendResourceRelationshipPlugin` |
| ProductPackagingUnitsBackendApi | PickingListsBackendApi | `ProductPackagingUnitPickingListItemsBackendApiAttributesMapperPlugin` |

**Total: 4 modules**

## Summary

| API Type                                    | Module Count |
|---------------------------------------------|--------------|
| Glue RestAPI (StorefrontAPI)                | 57           |
| BackendAPI                                  | 17           |
| Extension-Only Glue RestAPI (StorefrontAPI) | 34           |
| Extension-Only BackendAPI                   | 4            |
| **Total**                                   | **112**      |
