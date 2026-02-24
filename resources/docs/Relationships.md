# API Platform Relationships

## Overview

The API Platform Relationship system enables resources to include related resources via the `?include=` query parameter while maintaining JSON:API format compliance. Relationships are configured declaratively in YAML files and work automatically without code changes to providers.

## Features

- 🔗 **Declarative Configuration**: Define relationships in YAML resource files
- 🚀 **Zero Provider Changes**: Automatic decoration handles everything
- 📊 **Format Agnostic**: Same backend logic for JSON:API, JSON-LD, XML
- ⚡ **Performance Optimized**: Container parameters cached during compilation
- ✅ **Validated**: Bi-directional consistency checks at build time
- 🎯 **URI Variable Mapping**: Pass context from parent to child resources

## Quick Start

### 1. Define Relationship in Parent Resource

Add an `includes` section to your parent resource YAML file:

```yaml
# src/Spryker/Customer/resources/api/storefront/customers.resource.yml
resource:
  name: Customers
  shortName: customers
  description: "Customer profile management"

  # Define what can be included
  includes:
    - relationshipName: addresses
      targetResource: CustomersAddresses
      uriVariableMappings:
        customerReference: customerReference
```

### 2. Define Reverse Relationship in Child Resource

Add an `includableIn` section to your child resource YAML file:

```yaml
# src/Spryker/Customer/resources/api/storefront/customers-addresses.resource.yml
resource:
  name: CustomersAddresses
  shortName: customers-addresses
  description: "Customer address management"

  # Define where this can be included
  includableIn:
    - resource: Customers
      relationshipName: addresses
      uriVariableMappings:
        customerReference: customerReference
```

### 3. Regenerate Container

```bash
# Clear cache to regenerate container with new relationships
docker/sdk testing -x GLUE_APPLICATION=GLUE_STOREFRONT glue cache:clear
```

### 4. Use the Relationship

```bash
# Request customer with addresses included
GET /customers/customer--35?include=addresses

# Multiple includes
GET /customers/customer--35?include=addresses,orders,wishlists
```

## Configuration Reference

### includes Section (Parent Resource)

Declares what relationships this resource can include.

**Properties:**

- `relationshipName` (required): Name used in `?include=` parameter
- `targetResource` (required): Name of the resource to include (must match resource name)
- `uriVariableMappings` (optional): Map properties from parent to child URI variables

**Example:**

```yaml
includes:
  - relationshipName: addresses
    targetResource: CustomersAddresses
    uriVariableMappings:
      customerReference: customerReference  # parent.property → child.uriVariable

  - relationshipName: orders
    targetResource: Orders
    uriVariableMappings:
      customerReference: customerReference
```

### includableIn Section (Child Resource)

Declares where this resource can be included.

**Properties:**

- `resource` (required): Name of the parent resource
- `relationshipName` (required): Must match the includes declaration
- `uriVariableMappings` (optional): Must match the includes declaration

**Example:**

```yaml
includableIn:
  - resource: Customers
    relationshipName: addresses
    uriVariableMappings:
      customerReference: customerReference
```

## How It Works

### Architecture Overview

```
┌──────────────────────────────────────────────────────────────┐
│  1. Request: GET /customers/customer--35?include=addresses   │
└──────────────────────┬───────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────┐
│  2. RelationshipProviderDecorator (Automatic)                │
│     - Wraps all providers automatically                      │
│     - Calls CustomersStorefrontProvider (unchanged!)         │
│     - Parses ?include= parameter                             │
│     - Loads relationships via resolver                       │
│     - Stores in Request attributes                           │
└──────────────────────┬───────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────┐
│  3. ApiPlatformRelationshipResolver                          │
│     - Reads config from container parameter                  │
│     - Finds 'customers.addresses' relationship               │
│     - Maps customerReference → URI variable                  │
│     - Calls CustomersAddressesStorefrontProvider             │
│     - Returns related addresses                              │
└──────────────────────┬───────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────┐
│  4. JsonApiRelationshipNormalizer                            │
│     - Reads relationships from Request attributes            │
│     - Builds "relationships" section                         │
│     - Builds "included" section                              │
│     - Returns JSON:API compliant response                    │
└──────────────────────────────────────────────────────────────┘
```

### Key Components

1. **Container Parameters** - Configuration cached during compilation
   - Location: `var/cache/.../ProjectServiceContainer.php`
   - Parameter: `api_platform.relationships`
   - Generated by: `RelationshipConfigurationPass` compiler pass

2. **RelationshipProviderDecorator** - Automatic provider wrapper
   - Decorates: `api_platform.state_provider`
   - Extracts resource type from Operation metadata
   - Stores relationships in Request attributes by object hash

3. **ApiPlatformRelationshipResolver** - Relationship resolution service
   - Uses container parameters for configuration
   - ServiceLocator for provider lookup
   - Maps URI variables from parent to child

4. **JsonApiRelationshipNormalizer** - Format-specific serializer
   - Reads from Request attributes
   - Builds JSON:API relationships and included sections
   - Priority: -800 (runs after standard normalizers)

## Response Format

### Single Relationship

**Request:**
```
GET /customers/customer--35?include=addresses
```

**Response:**
```json
{
  "data": {
    "type": "customers",
    "id": "customer--35",
    "attributes": {
      "email": "john@example.com",
      "firstName": "John",
      "lastName": "Doe"
    },
    "relationships": {
      "addresses": {
        "data": [
          {"type": "addresses", "id": "addr-123"},
          {"type": "addresses", "id": "addr-456"}
        ]
      }
    }
  },
  "included": [
    {
      "type": "addresses",
      "id": "addr-123",
      "attributes": {
        "address1": "123 Test St",
        "city": "Test City",
        "zipCode": "12345"
      }
    },
    {
      "type": "addresses",
      "id": "addr-456",
      "attributes": {
        "address1": "456 Other St",
        "city": "Other City",
        "zipCode": "67890"
      }
    }
  ]
}
```

### Multiple Relationships

**Request:**
```
GET /customers/customer--35?include=addresses,orders
```

**Response:**
```json
{
  "data": {
    "type": "customers",
    "id": "customer--35",
    "attributes": { ... },
    "relationships": {
      "addresses": {
        "data": [...]
      },
      "orders": {
        "data": [...]
      }
    }
  },
  "included": [
    ...addresses...,
    ...orders...
  ]
}
```

## Validation

### Build-Time Validation

The system validates relationships during code generation:

**Bi-directional Consistency:**
- If parent declares `includes`, child must declare `includableIn`
- Relationship names must match
- URI variable mappings must match

**Resource Existence:**
- Target resource must exist
- Referenced properties should exist on resources

**Example Validation Error:**

```
Validation Error in customers.resource.yml:
  - includes[0].targetResource: Resource "CustomersAddresses" declares includableIn
    for "Customers" but uses different relationshipName "customerAddresses"
    Expected: "addresses"
```

### Runtime Behavior

**Invalid Include Names:**
- Gracefully ignored
- No error thrown
- Other valid includes still work

**Provider Errors:**
- If child provider returns null → empty relationship
- If child provider throws exception → exception bubbles up
- Other relationships still load

## URI Variable Mapping

URI variable mapping passes context from parent resource to child provider.

### Example: Customer → Addresses

**Parent Resource (Customer):**
```php
class Customer {
    public string $customerReference = 'DE--123';
    public string $email = 'john@example.com';
}
```

**YAML Configuration:**
```yaml
includes:
  - relationshipName: addresses
    targetResource: CustomersAddresses
    uriVariableMappings:
      customerReference: customerReference  # parent.property → child.uriVariable
```

**What Happens:**
1. Decorator extracts `$customer->customerReference` → `'DE--123'`
2. Builds URI variables: `['customerReference' => 'DE--123']`
3. Calls `CustomersAddressesStorefrontProvider->provide($operation, ['customerReference' => 'DE--123'], $context)`
4. Provider filters addresses: `WHERE customer_reference = 'DE--123'`

### Multiple Mappings

```yaml
uriVariableMappings:
  customerReference: customerReference
  storeId: storeId
  locale: locale
```

Maps to:
```php
[
    'customerReference' => $customer->customerReference,
    'storeId' => $customer->storeId,
    'locale' => $customer->locale,
]
```

### Property Fallback

If property doesn't exist on parent resource:
```php
$value = $mainResource->$sourceProperty ?? null;
if ($value !== null) {
    $uriVariables[$targetParameter] = $value;
}
```

## Provider Requirements

### No Changes Needed!

Existing providers work unchanged:

```php
class CustomersStorefrontProvider implements ProviderInterface
{
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): object|array|null {
        // No changes needed!
        // Just return customer as before
        return $this->customerClient->findCustomer(...);
    }
}
```

**The decorator automatically:**
- Intercepts the call
- Loads relationships if `?include=` present
- Stores them in Request attributes
- Returns your original result unchanged

### For Child Providers

Child providers should already support filtering via URI variables:

```php
class CustomersAddressesStorefrontProvider implements ProviderInterface
{
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): object|array|null {
        // Already supports customerReference filtering!
        $customerReference = $uriVariables['customerReference'] ?? null;

        return $this->customerClient->getAddresses($customerReference);
    }
}
```

## Performance Considerations

### Current Implementation

**Relationship Loading:**
- Loads relationships per parent resource
- If 10 customers → 10 separate address provider calls
- Potential N+1 query problem for collections

**Example:**
```php
// For each customer in collection
foreach ($mainResources as $customer) {
    // Separate provider call per customer
    $addresses = $provider->provide($operation, ['customerReference' => $customer->customerReference]);
}
```

### Optimization Opportunities

**Batch Loading (Future Phase 6):**
- Load all related resources in single query
- Requires batch provider interface
- Significant performance gain for collections

**Caching:**
- Container parameters cached by OPcache
- No runtime configuration parsing
- Relationship data stored in Request (request-scoped)

### Best Practices

1. **Use includes sparingly** - Only load what you need
2. **Avoid deep nesting** - Keep relationships shallow
3. **Consider pagination** - Limit collection sizes
4. **Monitor performance** - Profile relationship queries

## Troubleshooting

### Relationships Not Loading

**Symptoms:** `?include=addresses` returns no relationships

**Checklist:**

1. **Clear cache:**
   ```bash
   docker/sdk testing -x GLUE_APPLICATION=GLUE_STOREFRONT glue cache:clear
   ```

2. **Verify YAML configuration:**
   - Check `includes` section in parent
   - Check `includableIn` section in child
   - Verify names match exactly

3. **Check container parameter:**
   ```bash
   docker/sdk testing -x GLUE_APPLICATION=GLUE_STOREFRONT glue debug:container --parameter=api_platform.relationships
   ```

4. **Verify provider exists:**
   ```bash
   docker/sdk testing -x GLUE_APPLICATION=GLUE_STOREFRONT glue debug:container | grep AddressesProvider
   ```

5. **Check property exists:**
   - Verify `customerReference` property on parent resource
   - Verify provider accepts `customerReference` URI variable

### Validation Errors

**Error:** "Resource 'Foo' not found"

**Fix:** Ensure target resource name matches exactly:
```yaml
includes:
  - targetResource: CustomersAddresses  # Must match resource.name
```

**Error:** "Bi-directional consistency failed"

**Fix:** Ensure `includes` and `includableIn` match:
```yaml
# Parent
includes:
  - relationshipName: addresses  # Must match

# Child
includableIn:
  - relationshipName: addresses  # Must match
```

### Empty Relationships

**Symptoms:** Relationships load but are empty arrays

**Possible Causes:**

1. **Child provider returns null**
   - Check provider implementation
   - Verify data exists in database

2. **URI variables not mapped correctly**
   - Check property names in mapping
   - Verify properties exist on parent

3. **Provider filtering too strict**
   - Review filter conditions in child provider
   - Test provider directly with URI variables

### Performance Issues

**Symptoms:** Slow response with relationships

**Debug:**

1. **Enable query logging:**
   ```yaml
   # config/packages/doctrine.yaml
   doctrine:
     dbal:
       logging: true
   ```

2. **Count queries:**
   - Check for N+1 queries
   - Look for separate query per parent resource

3. **Profile request:**
   ```bash
   # Use Blackfire or Xdebug
   blackfire curl https://api.example.com/customers?include=addresses
   ```

## Migration Guide

### From Old Glue REST API Plugins

#### Before (Plugin-based)

**Step 1:** Create Plugin Class
```php
// src/Pyz/Glue/Customer/Plugin/CustomersToAddressesRelationshipPlugin.php
class CustomersToAddressesRelationshipPlugin extends AbstractPlugin implements ResourceRelationshipPluginInterface
{
    public function addResourceRelationships(
        array $resources,
        RestRequestInterface $restRequest
    ): void {
        foreach ($resources as $resource) {
            $addresses = $this->getAddresses($resource);
            // ... add to resource
        }
    }

    public function getRelationshipResourceType(): string
    {
        return 'addresses';
    }
}
```

**Step 2:** Register Plugin
```php
// src/Pyz/Glue/GlueApplication/GlueApplicationDependencyProvider.php
protected function getResourceRelationshipPlugins(): array
{
    return [
        new CustomersToAddressesRelationshipPlugin(),
        // ... more plugins
    ];
}
```

**Step 3:** Use in Controller (sometimes)
```php
$this->getFactory()
    ->getResourceBuilder()
    ->createRestResponse()
    ->addRelationship('addresses');
```

#### After (YAML-based)

**Step 1:** Add YAML Configuration
```yaml
# src/Spryker/Customer/resources/api/storefront/customers.resource.yml
includes:
  - relationshipName: addresses
    targetResource: CustomersAddresses
    uriVariableMappings:
      customerReference: customerReference
```

```yaml
# src/Spryker/Customer/resources/api/storefront/customers-addresses.resource.yml
includableIn:
  - resource: Customers
    relationshipName: addresses
    uriVariableMappings:
      customerReference: customerReference
```

**Step 2:** Clear Cache
```bash
docker/sdk testing -x GLUE_APPLICATION=GLUE_STOREFRONT glue cache:clear
```

**That's it! No code changes needed.**

#### Comparison

| Aspect | Old (Plugin) | New (YAML) |
|--------|-------------|------------|
| Configuration | PHP code | YAML declarative |
| Plugin class | Required | Not needed |
| Registration | Manual in DependencyProvider | Automatic |
| Provider changes | Sometimes needed | Never needed |
| Validation | Runtime | Build-time |
| Performance | Runtime overhead | Compiled |
| Testability | Integration tests | Unit + Integration |

#### Migration Checklist

- [ ] Identify all `ResourceRelationshipPluginInterface` implementations
- [ ] For each plugin:
  - [ ] Add `includes` to parent resource YAML
  - [ ] Add `includableIn` to child resource YAML
  - [ ] Verify URI variable mappings
  - [ ] Clear cache
  - [ ] Test `?include=` parameter
- [ ] Remove plugin classes
- [ ] Remove plugin registrations from DependencyProvider
- [ ] Update tests to use `?include=` parameter
- [ ] Update documentation

#### Deprecation Timeline

- **Current:** Both systems coexist
- **Future Minor:** Old plugins marked `@deprecated`
- **Future Major:** Old plugin system removed

## Advanced Topics

### Resource Identification

The normalizer identifies resources by these properties (in order):

1. `uuid`
2. `id`
3. `identifier`
4. `customerReference`

**Custom Identification:**

If your resource uses different property:
```php
// Override in custom normalizer or add to standard list
protected function getResourceId(object $resource): string
{
    if (property_exists($resource, 'myCustomId')) {
        return (string)$resource->myCustomId;
    }

    return parent::getResourceId($resource);
}
```

### Resource Type Derivation

Type is derived from class name with kebab-case conversion:

- `CustomerResource` → `customer-resource`
- `CustomersAddresses` → `customers-addresses`
- `Product` → `product`

**Override:**

The normalized resource's `type` field takes precedence:
```php
$normalizedResource['type'] = 'my-custom-type';
```

### Deduplication

Resources in `included` section are automatically deduplicated by type+id:

```php
$resourceKey = sprintf('%s:%s', $type, $id);
if (isset($seen[$resourceKey])) {
    continue; // Skip duplicate
}
```

### Context Preservation

Context is preserved through decoration:
```php
$context[self::ALREADY_CALLED] = true;
$normalizedResource = $this->normalizer->normalize($resource, $format, $context);
```

Prevents infinite loops in recursive normalization.

## API Reference

### RelationshipProviderDecorator

```php
public function provide(
    Operation $operation,
    array $uriVariables = [],
    array $context = []
): object|array|null
```

**Behavior:**
1. Calls inner provider
2. Returns null if provider returns null
3. Parses `?include=` parameter
4. Loads relationships if requested
5. Stores in Request attributes
6. Returns original provider result

### ApiPlatformRelationshipResolver

```php
public function resolveRelationships(
    string $mainResourceType,
    array $mainResources,
    array $requestedIncludes,
    array $context
): array

public function parseIncludeParameter(array $context): array
```

**Configuration Structure:**
```php
[
    'customers.addresses' => [
        'relationship_name' => 'addresses',
        'target_resource_type' => 'customers-addresses',
        'provider_service_id' => 'ProviderServiceId',
        'uri_variable_mappings' => [
            'customerReference' => 'customerReference',
        ],
    ],
]
```

### JsonApiRelationshipNormalizer

```php
public function normalize(
    mixed $object,
    ?string $format = null,
    array $context = []
): array|string|int|float|bool|\ArrayObject|null

public function supportsNormalization(
    mixed $data,
    ?string $format = null,
    array $context = []
): bool
```

**Supported Format:** `jsonapi`

**Priority:** `-800` (after standard normalizers)

## Contributing

### Adding New Features

1. Discuss in architecture review
2. Update design document
3. Implement with tests
4. Update this documentation
5. Create migration guide if breaking

### Reporting Issues

Include:
- YAML configuration
- Provider implementation
- Request URL
- Expected vs actual behavior
- Error messages
- Container parameter dump

## Resources

- [JSON:API Specification](https://jsonapi.org/format/)
- [API Platform Documentation](https://api-platform.com/docs/)
- [Symfony ServiceLocator](https://symfony.com/doc/current/service_container/service_subscribers_locators.html)
- [API Platform Design Document](../../docs/plans/2026-02-03-api-platform-relationship-system-design.md)

## Support

For questions or issues:
1. Check this documentation
2. Review troubleshooting section
3. Check container parameters
4. Test provider directly
5. Contact development team
