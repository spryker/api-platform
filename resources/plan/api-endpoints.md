# Spryker API Endpoints

This document lists all API endpoints from the Spryker Glue API specifications in a consolidated table format.

**Note**: DynamicEntity endpoints (304 total) are documented separately in [dynamic-api.md](./dynamic-api.md).

## Summary

- **Total Endpoints**: 408
- **DynamicEntity Endpoints**: 304 (see [dynamic-api.md](./dynamic-api.md))
- **Other Endpoints**: 104 (listed below)
- **Unique Modules**: 143

---

## All Endpoints

| Path | Method | Summary | Spec File | Module |
|------|--------|---------|-----------|--------|
| `/access-tokens` | POST | Creates access token for user. | spryker_rest_api.schema.yml | AccessTokens |
| `/agent-access-tokens` | POST | Creates agent's access token. | spryker_rest_api.schema.yml | AgentAccessTokens |
| `/agent-customer-impersonation-access-tokens` | POST | Creates customer imprsonation access token. | spryker_rest_api.schema.yml | AgentCustomerImpersonationAccessTokens |
| `/agent-customer-search` | GET | Retrieves customer list by query provided in GET parameteres. | spryker_rest_api.schema.yml | AgentCustomerSearch |
| `/agent-quote-requests` | GET | Retrieves quote request list. | spryker_rest_api.schema.yml | AgentQuoteRequests |
| `/agent-quote-requests` | POST | Creates a quote request as an agent. | spryker_rest_api.schema.yml | AgentQuoteRequests |
| `/availability-notifications` | POST | Subscribe to receive a notification by email when product is back in stock. | spryker_rest_api.schema.yml | AvailabilityNotifications |
| `/booked-services` | GET | Retrieves all booked services for the authenticated company user. | spryker_rest_api.schema.yml | BookedServices |
| `/cart-permission-groups` | GET | Retrieves collection of cart permission groups. | spryker_rest_api.schema.yml | CartPermissionGroups |
| `/cart-reorder` | POST | Makes cart reorder from existing order. | spryker_rest_api.schema.yml | CartReorder |
| `/carts` | GET | Retrieves list of all customer's carts. | spryker_rest_api.schema.yml | Carts |
| `/carts` | POST | Creates a cart. | spryker_rest_api.schema.yml | Carts |
| `/catalog-search` | GET | Catalog search. | spryker_rest_api.schema.yml | CatalogSearch |
| `/catalog-search-suggestions` | GET | Catalog search suggestions. | spryker_rest_api.schema.yml | CatalogSearchSuggestions |
| `/categories` | GET | Retrieve category collection. | spryker_backend_api.schema.yml | Categories |
| `/categories` | POST | Create category. | spryker_backend_api.schema.yml | Categories |
| `/category-trees` | GET | Retrieves category tree for specified locale. | spryker_rest_api.schema.yml | CategoryTrees |
| `/checkout` | POST | Places order. | spryker_rest_api.schema.yml | Checkout |
| `/cms-pages` | GET | Retrieves list of cms pages. | spryker_rest_api.schema.yml | CmsPages |
| `/companies` | GET | Retrieves company collection. | spryker_rest_api.schema.yml | Companies |
| `/company-business-unit-addresses` | GET | Retrieves company business unit addresses collection. | spryker_rest_api.schema.yml | CompanyBusinessUnitAddresses |
| `/company-business-units` | GET | Retrieves company business units collection. | spryker_rest_api.schema.yml | CompanyBusinessUnits |
| `/company-roles` | GET | Retrieves company role collection. | spryker_rest_api.schema.yml | CompanyRoles |
| `/company-user-access-tokens` | POST | Creates access token for company user. | spryker_rest_api.schema.yml | CompanyUserAccessTokens |
| `/company-users` | GET | Retrieves list of company users. | spryker_rest_api.schema.yml | CompanyUsers |
| `/configurable-bundle-templates` | GET | Retrieves collection of ConfigurableBundleTemplates. | spryker_rest_api.schema.yml | ConfigurableBundleTemplates |
| `/customer-access` | GET | Retrieves collection of restricted resources. | spryker_rest_api.schema.yml | CustomerAccess |
| `/customer-confirmation` | POST | Confirms customer registration. | spryker_rest_api.schema.yml | CustomerConfirmation |
| `/customer-forgotten-password` | POST | Sends password restoration email. | spryker_rest_api.schema.yml | CustomerForgottenPassword |
| `/customers` | GET | Retrieves customers collection. | spryker_rest_api.schema.yml | Customers |
| `/customers` | POST | Creates customer. | spryker_rest_api.schema.yml | Customers |
| `/guest-carts` | GET | Retrieves list of customer's guest carts. | spryker_rest_api.schema.yml | GuestCarts |
| `/merchants` | GET | Retrieves list of merchants. | spryker_rest_api.schema.yml | Merchants |
| `/multi-factor-auth-trigger` | POST | Triggers sending of multi-factor authentication code. | spryker_backend_api.schema.yml | MultiFactorAuthTrigger |
| `/multi-factor-auth-trigger` | POST | Triggers sending of multi-factor authentication code. | spryker_rest_api.schema.yml | MultiFactorAuthTrigger |
| `/multi-factor-auth-trigger` | POST | Triggers sending of multi-factor authentication code. | spryker_storefront_api.schema.yml | MultiFactorAuthTrigger |
| `/multi-factor-auth-type-activate` | POST | Activates a new multi-factor authentication type for a customer | spryker_backend_api.schema.yml | MultiFactorAuthTypeActivate |
| `/multi-factor-auth-type-activate` | POST | Activates a new multi-factor authentication type for a customer | spryker_rest_api.schema.yml | MultiFactorAuthTypeActivate |
| `/multi-factor-auth-type-activate` | POST | Activates a new multi-factor authentication type for a customer | spryker_storefront_api.schema.yml | MultiFactorAuthTypeActivate |
| `/multi-factor-auth-type-deactivate` | POST | Deactivates a multi-factor authentication type for a customer | spryker_backend_api.schema.yml | MultiFactorAuthTypeDeactivate |
| `/multi-factor-auth-type-deactivate` | POST | Deactivates a multi-factor authentication type for a customer | spryker_rest_api.schema.yml | MultiFactorAuthTypeDeactivate |
| `/multi-factor-auth-type-deactivate` | POST | Deactivates a multi-factor authentication type for a customer | spryker_storefront_api.schema.yml | MultiFactorAuthTypeDeactivate |
| `/multi-factor-auth-type-verify` | POST | Verifies a multi-factor authentication code for a specific type | spryker_backend_api.schema.yml | MultiFactorAuthTypeVerify |
| `/multi-factor-auth-type-verify` | POST | Verifies a multi-factor authentication code for a specific type | spryker_rest_api.schema.yml | MultiFactorAuthTypeVerify |
| `/multi-factor-auth-type-verify` | POST | Verifies a multi-factor authentication code for a specific type | spryker_storefront_api.schema.yml | MultiFactorAuthTypeVerify |
| `/multi-factor-auth-types` | GET | Retrieves multi-factor authentication types. | spryker_backend_api.schema.yml | MultiFactorAuthTypes |
| `/multi-factor-auth-types` | GET | Retrieves multi-factor authentication types. | spryker_rest_api.schema.yml | MultiFactorAuthTypes |
| `/multi-factor-auth-types` | GET | Retrieves multi-factor authentication types. | spryker_storefront_api.schema.yml | MultiFactorAuthTypes |
| `/my-availability-notifications` | GET | Retrieves a collection of notification subscriptions about products availability filtered by the current logged in customer. | spryker_rest_api.schema.yml | MyAvailabilityNotifications |
| `/order-payments` | POST | Updates order payment. | spryker_rest_api.schema.yml | OrderPayments |
| `/orders` | GET | Retrieves list of orders. | spryker_rest_api.schema.yml | Orders |
| `/payment-cancellations` | POST | Cancels a pre-order payment. | spryker_rest_api.schema.yml | PaymentCancellations |
| `/payment-customers` | POST | Returns customer data that should be used on the store front address page. | spryker_rest_api.schema.yml | PaymentCustomers |
| `/payments` | POST | Creates a pre-order payment and returns payment provider data that should be used on the store front payment page. | spryker_rest_api.schema.yml | Payments |
| `/picking-lists` | GET | Retrieves the picking list collection. | spryker_backend_api.schema.yml | PickingLists |
| `/product-abstract` | GET | Retrieves product abstract collection. | spryker_backend_api.schema.yml | ProductAbstract |
| `/product-abstract` | POST | Creates product abstract. | spryker_backend_api.schema.yml | ProductAbstract |
| `/product-attributes` | GET | Get Product Attribute collection. | spryker_backend_api.schema.yml | ProductAttributes |
| `/product-attributes` | POST | Creates the Product Attribute. | spryker_backend_api.schema.yml | ProductAttributes |
| `/product-management-attributes` | GET | Retrieves list of attributes. | spryker_rest_api.schema.yml | ProductManagementAttributes |
| `/push-notification-providers` | GET | Retrieves a collection of push notification providers. | spryker_backend_api.schema.yml | PushNotificationProviders |
| `/push-notification-providers` | POST | Creates a push notification provider. | spryker_backend_api.schema.yml | PushNotificationProviders |
| `/push-notification-subscriptions` | POST | Creates a push notification subscription. | spryker_backend_api.schema.yml | PushNotificationSubscriptions |
| `/quote-requests` | GET | Retrieves quote request list. | spryker_rest_api.schema.yml | QuoteRequests |
| `/quote-requests` | POST | Creates a quote request as a company user. | spryker_rest_api.schema.yml | QuoteRequests |
| `/refresh-tokens` | POST | Refreshes customer's auth token. | spryker_rest_api.schema.yml | RefreshTokens |
| `/return-reasons` | GET | Retrieves list of return reasons. | spryker_rest_api.schema.yml | ReturnReasons |
| `/returns` | GET | Retrieves list of returns. | spryker_rest_api.schema.yml | Returns |
| `/returns` | POST | Creates a return. | spryker_rest_api.schema.yml | Returns |
| `/service-point-addresses` | GET | Retrieves service point addresses collection. | spryker_backend_api.schema.yml | ServicePointAddresses |
| `/service-point-addresses` | POST | Creates service point address. | spryker_backend_api.schema.yml | ServicePointAddresses |
| `/service-points` | GET | Retrieves service points collection. | spryker_backend_api.schema.yml | ServicePoints |
| `/service-points` | POST | Creates service point. | spryker_backend_api.schema.yml | ServicePoints |
| `/service-points` | GET | Retrieves service points collection. | spryker_rest_api.schema.yml | ServicePoints |
| `/service-types` | GET | Retrieves service types collection. | spryker_backend_api.schema.yml | ServiceTypes |
| `/service-types` | POST | Creates service type. | spryker_backend_api.schema.yml | ServiceTypes |
| `/services` | GET | Retrieves services collection. | spryker_backend_api.schema.yml | Services |
| `/services` | POST | Creates service. | spryker_backend_api.schema.yml | Services |
| `/shipment-types` | GET | Retrieves shipment types collection. | spryker_backend_api.schema.yml | ShipmentTypes |
| `/shipment-types` | POST | Creates shipment type. | spryker_backend_api.schema.yml | ShipmentTypes |
| `/shipment-types` | GET | Retrieves a shipment types collection. | spryker_rest_api.schema.yml | ShipmentTypes |
| `/shopping-lists` | GET | Retrieves list of all customer's shopping lists. | spryker_rest_api.schema.yml | ShoppingLists |
| `/shopping-lists` | POST | Creates a shopping list. | spryker_rest_api.schema.yml | ShoppingLists |
| `/ssp-assets` | GET | Retrieves SSP assets collection. | spryker_backend_api.schema.yml | SspAssets |
| `/ssp-assets` | POST | Creates SSP asset. | spryker_backend_api.schema.yml | SspAssets |
| `/ssp-assets` | GET | Retrieves all assets for the authenticated company user. | spryker_rest_api.schema.yml | SspAssets |
| `/ssp-assets` | POST | Creates an asset. | spryker_rest_api.schema.yml | SspAssets |
| `/ssp-inquiries` | GET | Retrieves all inquiries for the authenticated company user. | spryker_rest_api.schema.yml | SspInquiries |
| `/ssp-inquiries` | POST | Creates an inquiry. | spryker_rest_api.schema.yml | SspInquiries |
| `/start-picking` | POST | Assigns the warehouse user to the picking list and updates the picking list to indicate that picking has started. | spryker_backend_api.schema.yml | StartPicking |
| `/stores` | GET | Retrieves current store data in case of Dynamic Store is off and all stores - if the Dynamic Store is on. | spryker_rest_api.schema.yml | Stores |
| `/stores` | GET | Retrieves store collection. | spryker_storefront_api.schema.yml | Stores |
| `/tax-id-validate` | POST | Create tax id validate. | spryker_rest_api.schema.yml | TaxIdValidate |
| `/token` | POST | Creates access token for user. | spryker_backend_api.schema.yml | Token |
| `/token` | POST | Create token. | spryker_rest_api.schema.yml | Token |
| `/token` | POST | Creates access token for customer. | spryker_storefront_api.schema.yml | Token |
| `/url-resolver` | GET | Retrieves collection of urls by the `url` parameter provided in GET request. | spryker_rest_api.schema.yml | UrlResolver |
| `/warehouse-tokens` | POST | Creates warehouse access tokens. | spryker_backend_api.schema.yml | WarehouseTokens |
| `/warehouse-user-assignments` | GET | Retrieves warehouse user assignments collection. | spryker_backend_api.schema.yml | WarehouseUserAssignments |
| `/warehouse-user-assignments` | POST | Creates warehouse user assignment. | spryker_backend_api.schema.yml | WarehouseUserAssignments |
| `/wishlists` | GET | Retrieves all customer wishlists. | spryker_rest_api.schema.yml | Wishlists |
| `/wishlists` | POST | Creates wishlist. | spryker_rest_api.schema.yml | Wishlists |
