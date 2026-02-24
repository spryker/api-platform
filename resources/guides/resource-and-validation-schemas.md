# Resource and Validation Schemas Guide for AI Agents

This guide explains how to work with API Platform resource and validation schemas in Spryker.

## Overview

API Platform uses YAML-based schemas to define API resources and their validation rules:

- **Resource Schemas** (`*.yml`) - Define the structure, operations, and behavior of API resources
- **Validation Schemas** (`*.validation.yml`) - Define validation constraints per operation type

Both schemas support multi-layer merging (Core → Feature → Project) and automatic code generation.

## File Locations

Schemas must be placed in the `resources/api/{api-type}/` directory:

```
src/
├── Spryker/{Module}/resources/api/           # Core layer
│   ├── backend/
│   │   ├── resource-name.yml                 # Resource schema
│   │   └── resource-name.validation.yml      # Validation schema
│   └── storefront/
│       ├── resource-name.yml
│       └── resource-name.validation.yml
├── SprykerFeature/{Feature}/resources/api/   # Feature layer
│   └── backend/
│       ├── resource-name.yml
│       └── resource-name.validation.yml
└── Pyz/Glue/{Module}/resources/api/          # Project layer (highest priority)
    └── backend/
        ├── resource-name.yml
        └── resource-name.validation.yml
```

## Resource Schema Structure

### Minimal Example

```yaml
resource:
  name: Products                      # Internal name (used for merging)
  shortName: Product                  # URL name (becomes /products)
  description: "Product resource"

  operations:
    - type: Get                       # GET /products/{id}
    - type: GetCollection             # GET /products
    - type: Post                      # POST /products
    - type: Patch                     # PATCH /products/{id}
    - type: Delete                    # DELETE /products/{id}

  properties:
    id:
      type: integer
      writable: false
      identifier: true                # Use as URL identifier

    name:
      type: string
      required: true

    price:
      type: number
      description: "Product price"
```

### Key Resource Schema Fields

| Field | Purpose | Example |
|-------|---------|---------|
| `name` | Internal resource name for schema merging | `Customers` |
| `shortName` | URL-friendly name (becomes plural endpoint) | `Customer` → `/customers` |
| `operations` | Available HTTP operations | `Get`, `Post`, `Patch`, `Delete`, `GetCollection` |
| `provider` | FQCN of the state provider class | `Pyz\Glue\Customer\Api\Backend\Provider\CustomerBackendProvider` |
| `processor` | FQCN of the state processor class | `Pyz\Glue\Customer\Api\Backend\Processor\CustomerBackendProcessor` |
| `paginationEnabled` | Enable pagination for collections | `true` |
| `security` | Security expression for access control | `is_granted('ROLE_ADMIN')` |

### Property Attributes

```yaml
properties:
  idCustomer:
    type: integer
    writable: false     # Read-only (cannot be sent in POST/PATCH)
    readable: true      # Include in responses (default: true)

  email:
    type: string
    required: true      # Must be present in requests
    writable: true      # Can be sent in requests
    readable: true      # Include in responses

  password:
    type: string
    writable: true      # Can be sent in requests
    readable: false     # NOT included in responses

  customerReference:
    type: string
    identifier: true    # Use as URL identifier: /customers/{customerReference}
    writable: false
```

### Relationships (Includes)

Define relationships between resources to enable `?include=` parameter support with JSON:API compliance.

**Parent resource defines includes:**
```yaml
resource:
  name: Customers
  shortName: customers

  # What this resource can include
  includes:
    - relationshipName: addresses
      targetResource: CustomersAddresses
      uriVariableMappings:
        customerReference: customerReference

    - relationshipName: orders
      targetResource: Orders
      uriVariableMappings:
        customerReference: customerReference
```

**Child resource declares where it can be included:**
```yaml
resource:
  name: CustomersAddresses
  shortName: customers-addresses

  # Where this resource can be included
  includableIn:
    - resource: Customers
      relationshipName: addresses
      uriVariableMappings:
        customerReference: customerReference
```

**Key fields:**
- `relationshipName` - Name used in `?include=` parameter
- `targetResource` - Resource name to include (must match resource `name`)
- `uriVariableMappings` - Maps properties from parent to child URI variables

**Usage:**
```
GET /customers/customer--35?include=addresses
GET /customers/customer--35?include=addresses,orders
```

**Features:**
- ✅ Bi-directional validation at build time
- ✅ Zero provider changes needed
- ✅ Automatic JSON:API formatting
- ✅ Format agnostic (works with JSON:API, JSON-LD, XML)

For comprehensive documentation, see [Relationships.md](../../resources/docs/Relationships.md).

### Supported Property Types

| Type | PHP Type | Example |
|------|----------|---------|
| `string` | `string` | `"John"` |
| `integer` | `int` | `42` |
| `number` | `float` | `3.14` |
| `boolean` | `bool` | `true` |
| `array` | `array` | `["a", "b"]` |
| `object` | `object` | `{"key": "value"}` |

### OpenAPI Context and Examples

The `openapiContext` field allows you to add OpenAPI-specific metadata to properties, including examples that appear in the generated OpenAPI specification and Swagger UI.

**Basic syntax:**
```yaml
properties:
  customerReference:
    type: string
    identifier: true
    openapiContext:
      example: "DE--123"

  email:
    type: string
    openapiContext:
      example: "john.doe@example.com"
```

**Best practices:**
- Add examples to all properties where sensible
- Use realistic, representative example values
- Examples help developers understand the expected data format
- Examples appear in Swagger UI's "Try it" functionality
- Write-only fields (like passwords) should still include examples for documentation purposes

**Generated output:**
The examples are included in the OpenAPI specification and displayed in Swagger UI, making it easier for API consumers to understand and test endpoints.

### Automatic JSON:API Envelope Examples

For JSON:API endpoints, the ClassGenerator automatically constructs complete request examples including the `type` field. This means you only need to define examples at the property level, and the generator handles the JSON:API envelope structure.

**Property-level examples in YAML:**
```yaml
resource:
  name: Customers
  shortName: customers  # Lowercase plural - becomes the "type" field

properties:
  email:
    type: string
    openapiContext:
      example: "john@example.com"
  firstName:
    type: string
    openapiContext:
      example: "John"
```

**Auto-generated OpenAPI example:**
```json
{
  "data": {
    "type": "customers",
    "attributes": {
      "email": "john@example.com",
      "firstName": "John"
    }
  }
}
```

**How it works:**
- The `shortName` value becomes the `type` field in JSON:API responses
- The generator automatically wraps property examples in the JSON:API `data.attributes` structure
- This happens automatically for POST, PATCH, and PUT operations
- Only writable properties (not marked as `writable: false`) are included
- Properties without examples are omitted

**Manual override (rarely needed):**
You can override auto-generated examples by adding operation-level `openapiContext`:
```yaml
operations:
  - type: Post
    openapiContext:
      requestBody:
        content:
          application/vnd.api+json:
            example:
              data:
                type: custom-type-name
                attributes:
                  # custom example structure
```

## Validation Schema Structure

Validation schemas define constraints per operation using Symfony validation constraints.

### Basic Syntax

```yaml
post:
  email:
    - NotBlank:
        message: "Email is required"
    - Email:
        message: "Invalid email format"

  firstName:
    - NotBlank
    - Length:
        min: 2
        max: 100
        minMessage: "Name must be at least {{ limit }} characters"
        maxMessage: "Name cannot exceed {{ limit }} characters"

patch:
  email:
    - Optional:                       # Not required for PATCH
        constraints:
          - NotBlank
          - Email

  firstName:
    - Optional:
        constraints:
          - Length:
              min: 2
              max: 100
```

### Operation Names

Map validation rules to HTTP operations:

| Operation Key | HTTP Method | Description |
|---------------|-------------|-------------|
| `post` | POST | Create new resource |
| `get` | GET | Retrieve single resource |
| `getCollection` | GET | Retrieve collection |
| `put` | PUT | Replace entire resource |
| `patch` | PATCH | Update partial resource |
| `delete` | DELETE | Delete resource |

### Common Symfony Constraints

**String constraints:**
```yaml
- NotBlank:
    message: "This field is required"
- Email:
    message: "Invalid email format"
- Length:
    min: 2
    max: 100
- Regex:
    pattern: '/^[A-Z][a-z]+$/'
    message: "Must start with uppercase letter"
- Choice:
    choices: ["active", "inactive", "pending"]
- Url:
    message: "Invalid URL"
```

**Numeric constraints:**
```yaml
- Positive:
    message: "Must be positive"
- Range:
    min: 0
    max: 100
- GreaterThan:
    value: 0
```

**Date constraints:**
```yaml
- Date:
    message: "Invalid date format"
- DateTime:
    message: "Invalid datetime format"
```

### Using Custom Constraint Classes (FQCN)

You can reference custom constraint classes using their fully qualified class name:

```yaml
post:
  email:
    - NotBlank
    - Email
    - \Spryker\Zed\Customer\Business\Validator\UniqueEmail     # Custom constraint

  sku:
    - \Spryker\Zed\Product\Business\Validator\ValidSku:        # With options
        message: "Invalid SKU format"
        checkAvailability: true
```

**Generated code:**
```php
use Symfony\Component\Validator\Constraints as Assert;
use Spryker\Zed\Customer\Business\Validator\UniqueEmail;
use Spryker\Zed\Product\Business\Validator\ValidSku;

#[Assert\NotBlank(groups: ['customers:create'])]
#[Assert\Email(groups: ['customers:create'])]
#[UniqueEmail(groups: ['customers:create'])]
public ?string $email = null;

#[ValidSku(message: 'Invalid SKU format', checkAvailability: true, groups: ['customers:create'])]
public ?string $sku = null;
```

**Collision handling:** When multiple constraints have the same short name, the generator automatically creates aliases:

```yaml
post:
  email:
    - \Spryker\Zed\Customer\Business\Validator\Email
    - \Spryker\Glue\Product\Business\Validator\Email
```

**Generated code:**
```php
use Spryker\Zed\Customer\Business\Validator\Email as SprykerCustomerEmail;
use Spryker\Glue\Product\Business\Validator\Email as SprykerProductEmail;

#[SprykerCustomerEmail(groups: ['customers:create'])]
#[SprykerProductEmail(groups: ['customers:create'])]
public ?string $email = null;
```

### Constraint Deduplication

The generator automatically deduplicates identical constraints and combines their validation groups:

```yaml
# Schema definition
post:
  name:
    - NotBlank
    - Length: { max: 100 }

patch:
  name:
    - NotBlank
    - Length: { max: 100 }
```

**Generated code (deduplicated):**
```php
#[Assert\NotBlank(groups: ['customers:create', 'customers:update'])]
#[Assert\Length(max: 100, groups: ['customers:create', 'customers:update'])]
public ?string $name = null;
```

## Schema Merging

Spryker automatically merges schemas from multiple layers. Higher priority layers override lower priority layers.

**Priority order:** Project > Feature > Core

**Example:**

Core layer:
```yaml
# vendor/spryker/customer/resources/api/backend/customer.yml
resource:
  name: Customers
  properties:
    email:
      type: string
    firstName:
      type: string
```

Feature layer:
```yaml
# src/SprykerFeature/CRM/resources/api/backend/customer.yml
resource:
  name: Customers
  properties:
    phone:
      type: string
```

Project layer:
```yaml
# src/Pyz/Glue/Customer/resources/api/backend/customer.yml
resource:
  name: Customers
  properties:
    email:
      required: true          # Override core definition
    customField:
      type: string            # Project-specific field
```

**Merged result:**
```yaml
resource:
  name: Customers
  properties:
    email:
      type: string
      required: true          # From project layer
    firstName:
      type: string            # From core layer
    phone:
      type: string            # From feature layer
    customField:
      type: string            # From project layer
```

**Validation schema merging** works the same way:

```yaml
# Core validation
post:
  email:
    - NotBlank
    - Email

# Project validation
post:
  email:
    - NotBlank
    - Email
    - \Pyz\Zed\Customer\Business\Validator\CompanyEmailDomain

# Merged result (with deduplication)
post:
  email:
    - NotBlank              # Deduplicated from both layers
    - Email                 # Deduplicated from both layers
    - \Pyz\Zed\Customer\Business\Validator\CompanyEmailDomain  # Added
```

## Code Generation Process

The resource generation follows a structured pipeline:

```
1. Preparation Phase
   ↓
2. Schema Parsing Phase → ParseResult
   - Load validation schemas
   - Parse validation rules
   - Load resource schemas
   - Parse resource definitions
   ↓
3. Schema Merging Phase → MergeResult
   - Merge schemas (Core → Feature → Project)
   - Track contributing source files
   ↓
4. Validation Phase → ValidationResult
   - Validate merged schemas
   - Apply validation rules
   ↓
5. Code Generation Phase
   - Generate PHP resource classes
   - Write files to output directory
   ↓
6. Cache Update
```

**Result objects:** Each phase produces result objects that encapsulate both successful outcomes and failures, ensuring errors in one resource do not block generation of other valid resources.

### Generated Resource Class Example

```php
<?php

declare(strict_types=1);
namespace Generated\Api\Backend;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;

#[ApiResource(
    operations: [new Post(), new Get(), new GetCollection(), new Patch(), new Delete()],
    shortName: 'Customer',
    provider: CustomerBackendProvider::class,
    processor: CustomerBackendProcessor::class,
    paginationItemsPerPage: 10
)]
final class CustomersBackendResource
{
    #[ApiProperty(writable: false)]
    public ?int $idCustomer = null;

    #[ApiProperty(openapiContext: ['example' => 'john@example.com'])]
    #[Assert\NotBlank(groups: ['customers:create'])]
    #[Assert\Email(groups: ['customers:create'])]
    public ?string $email = null;

    #[ApiProperty(identifier: true, writable: false)]
    public ?string $customerReference = null;

    // Getters, setters, toArray(), fromArray() methods...
}
```

## CLI Commands

### Generate resources

```bash
# Generate all configured API types
docker/sdk cli glue api:generate

# Generate specific API type
docker/sdk cli glue api:generate backend
docker/sdk cli glue api:generate storefront

# Generate with options
docker/sdk cli glue api:generate --dry-run              # Preview without writing
docker/sdk cli glue api:generate --validate-only        # Only validate schemas
docker/sdk cli glue api:generate --resource=customers   # Generate single resource
```

### Debug schemas

```bash
# List all resources
docker/sdk cli glue api:debug --list

# Show specific resource
docker/sdk cli glue api:debug customers --api-type=backend

# Show merged schema
docker/sdk cli glue api:debug customers --api-type=backend --show-merged

# Show contributing source files
docker/sdk cli glue api:debug customers --api-type=backend --show-sources
```

## Best Practices

### 1. Resource Schemas

**Use semantic naming:**
```yaml
# ✅ Good - Lowercase plural shortName
resource:
  name: Customers
  shortName: customers

# ✅ Good - More examples
resource:
  name: Orders
  shortName: orders

resource:
  name: Addresses
  shortName: addresses

# ❌ Bad - CamelCase or singular shortName
resource:
  name: Customers
  shortName: Customers  # Should be lowercase

resource:
  name: Customers
  shortName: Customer   # Should be plural

# ❌ Bad - Unclear or abbreviated
resource:
  name: CustomerData
  shortName: cust
```

**IMPORTANT: shortName Convention**
- Always use lowercase plural form (e.g., `customers`, `orders`, `products`)
- The `shortName` becomes the JSON:API `type` field value
- This ensures consistency across all API responses and OpenAPI documentation

**Document all properties:**
```yaml
# ✅ Good
email:
  type: string
  description: "The customer's email address used for login and notifications"

# ❌ Bad
email:
  type: string
```

**Use readable/writable correctly:**
```yaml
# Read-only fields (IDs, timestamps)
idCustomer:
  type: integer
  writable: false

# Write-only fields (passwords)
password:
  type: string
  readable: false

# Read-write fields (normal data)
email:
  type: string
  writable: true
  readable: true
```

**Leverage schema merging:**
```yaml
# Core: Define base properties
# Project: Only override what's needed
resource:
  name: Customers
  properties:
    email:
      required: true      # ← Only the difference
```

### 2. Validation Schemas

**Use operation-specific validation:**
```yaml
# ✅ Good - Different rules per operation
post:
  password:
    - NotBlank
    - Length: { min: 12 }

patch:
  password:
    - Optional:
        constraints:
          - Length: { min: 12 }
```

**Provide meaningful error messages:**
```yaml
# ✅ Good
- Email:
    message: "Please provide a valid email address"
- Length:
    min: 8
    minMessage: "Password must be at least 8 characters for security"

# ❌ Bad
- Email
- Length: { min: 8 }
```

**Use composite constraints for arrays:**
```yaml
# ✅ Good
items:
  - All:
      constraints:
        - NotBlank
        - Regex:
            pattern: '/^[A-Z0-9]+$/'
```

**Combine Symfony and custom constraints:**
```yaml
# ✅ Good - Mix and match as needed
email:
  - NotBlank                                              # Symfony
  - Email                                                 # Symfony
  - \Spryker\Zed\Customer\Business\Validator\UniqueEmail # Custom
```

## Common Validation Errors

The generator validates schemas and provides detailed error messages:

```bash
# Missing required fields
Error: Resource "customers" is missing required field "name"

# Invalid operation type
Error: Invalid operation type "INVALID". Must be one of: Get, Post, Put, Patch, Delete, GetCollection

# Invalid property type
Error: Property "age" has invalid type "int". Must be one of: string, integer, number, boolean, array, object

# Provider class not found
Error: Provider class "Pyz\Glue\Customer\Api\Backend\Provider\MissingProvider" does not exist
```

## Testing

API Platform provides testing infrastructure based on Codeception and PHPUnit:

- Test resources are generated into `tests/_data/Api/{ApiType}/`
- Automatic cache cleanup after test suites
- Backend and Storefront test base classes
- Rich assertion methods for API testing

**Example test:**
```php
<?php

namespace PyzTest\Glue\Customer\BackendApi;

use PyzTest\Glue\Customer\BackendApiTester;
use SprykerTest\Shared\ApiPlatform\Test\BackendApiTestCase;

class CustomersBackendApiTest extends BackendApiTestCase
{
    protected BackendApiTester $tester;

    public function testGivenValidDataWhenCreatingCustomerViaPostThenCustomerIsCreatedSuccessfully(): void
    {
        // Arrange
        $customerData = [
            'email' => 'john.doe@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];

        // Act
        static::createClient()->request('POST', '/customers', ['json' => $customerData]);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['email' => 'john.doe@example.com']);
    }
}
```

## Quick Reference

### Resource Schema Checklist

- [ ] Place in `resources/api/{api-type}/resource-name.yml`
- [ ] Define `name` (for merging) and `shortName` (for URL)
- [ ] Add `description` for documentation
- [ ] Specify `operations` (Get, GetCollection, Post, Patch, Delete)
- [ ] Define all `properties` with correct types
- [ ] Set `writable`, `readable`, `required` as needed
- [ ] Mark identifier property with `identifier: true`
- [ ] Add `provider` and `processor` class FQCNs
- [ ] Configure pagination if needed
- [ ] Define `includes` for relationships this resource can include (optional)
- [ ] Define `includableIn` if this resource can be included by others (optional)

### Validation Schema Checklist

- [ ] Place in `resources/api/{api-type}/resource-name.validation.yml`
- [ ] Define constraints per operation (post, patch, put, get, delete)
- [ ] Use Symfony constraints for common validation
- [ ] Use FQCN for custom constraint classes
- [ ] Provide meaningful error messages
- [ ] Use `Optional` wrapper for non-required fields in PATCH
- [ ] Use `All` for array element validation
- [ ] Leverage constraint deduplication for shared rules

### Generation Workflow

1. **Create/modify schemas** in appropriate layer (Core/Feature/Project)
2. **Run generation**: `docker/sdk cli glue api:generate`
3. **Debug if needed**: `docker/sdk cli glue api:debug resource-name --api-type=backend`
4. **Test**: Write Codeception tests extending `BackendApiTestCase` or `StorefrontApiTestCase`
5. **Verify**: Check generated files in `Generated/Api/{ApiType}/`

---

**See Also:**
- [Relationships.md](../../resources/docs/Relationships.md) - Comprehensive relationship system documentation
- [ClassHierarchy.md](../../resources/docs/ClassHierarchy.md) - Complete component and class documentation
- [API Platform Documentation](https://api-platform.com/docs/)
- [Symfony Validation Documentation](https://symfony.com/doc/current/validation.html)
- [Codeception Documentation](https://codeception.com/docs/Introduction)
