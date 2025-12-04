# API Platform Resource Generation

This document explains how to define schema files for API resources and how the generation process transforms these schemas into fully functional API Platform resource classes.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Schema File Structure](#schema-file-structure)
3. [Validation Schemas](#validation-schemas)
4. [Generation Process](#generation-process)
5. [Multi-Layer Architecture](#multi-layer-architecture)
6. [Generated Output](#generated-output)
7. [Console Commands](#console-commands)

---

## Quick Start

### 1. Create a Schema File

Place your schema file in the module's resources directory:

```
src/{Layer}/{Module}/resources/api/{apiType}/{resourceName}.yml
```

**Example:** `src/SprykerFeature/CustomerRelationManagement/resources/api/backoffice/customers.yml`

```yaml
resource:
  name: Customers
  shortName: Customer
  description: Customer resource for backoffice API

  provider: SprykerFeature\Glue\CustomerRelationManagement\Api\Provider\CustomerBackofficeProvider
  processor: SprykerFeature\Glue\CustomerRelationManagement\Api\Processor\CustomerBackofficeProcessor

  paginationEnabled: true
  paginationItemsPerPage: 10

  operations:
    - type: Post
    - type: Get
    - type: GetCollection
    - type: Patch
    - type: Delete

  properties:
    idCustomer:
      type: integer
      description: The unique identifier of the customer.
      writable: false

    email:
      type: string
      description: The email address of the customer.
      required: true

    customerReference:
      type: string
      description: A unique reference for a customer.
      writable: false
      identifier: true
```

### 2. Generate the Resource

```bash
bin/glue api:generate backoffice
```

### 3. Use the Generated Class

The system generates a PHP class at:
```
src/Generated/Api/Backoffice/CustomersBackofficeResource.php
```

---

## Schema File Structure

### File Location Pattern

```
src/{Layer}/{Module}/resources/api/{apiType}/{resourceName}.{yml|yaml}
```

**Where:**
- `{Layer}`: `Spryker`, `SprykerFeature`, or `Pyz` (Any other project namespace will also work.)
- `{Module}`: The module name (e.g., `CustomerRelationManagement`)
- `{apiType}`: Lowercase API type (e.g., `backoffice`, `storefront`)
- `{resourceName}`: Resource identifier (e.g., `customers`, `orders`)

**Supported formats:** YAML (`.yml`, `.yaml`)

### Root Structure

Every schema file must have a root `resource` key:

```yaml
resource:
  name: string                  # Full resource name (required)
  shortName: string             # Short name for URLs (required)
  description: string           # Human-readable description
  provider: string              # Fully qualified provider class
  processor: string             # Fully qualified processor class
  paginationEnabled: boolean    # Enable pagination (default: false)
  paginationItemsPerPage: int   # Items per page (default: 30)
  operations: array             # List of HTTP operations
  properties: object            # Resource properties
```

### Operations

Define which HTTP operations are supported:

```yaml
operations:
  - type: Post                  # Create new resource
  - type: Get                   # Retrieve single resource
  - type: GetCollection         # Retrieve collection
  - type: Patch                 # Update resource
  - type: Delete                # Delete resource
  - type: Put                   # Replace resource
```

### Properties

Define resource properties with detailed configuration:

```yaml
properties:
  propertyName:
    type: string|integer|boolean|array|object|mixed
    description: string         # Property description
    writable: boolean           # Can be written (default: true)
    readable: boolean           # Can be read (default: true)
    identifier: boolean         # Is identifier (default: false)
    required: boolean           # Is required (default: false)
    default: mixed              # Default value
    openapiContext:             # OpenAPI metadata
      example: mixed
      schema:
        enum: array
```

**Available types:**
- `string` → PHP `string`
- `integer` (or `int`) → PHP `int`
- `boolean` (or `bool`) → PHP `bool`
- `array` → PHP `array`
- `object` → PHP `object`
- `mixed` → PHP `mixed`

### Complete Example

```yaml
resource:
  name: Customers
  shortName: Customer
  description: Customer resource for backoffice API

  provider: SprykerFeature\Glue\CustomerRelationManagement\Api\Provider\CustomerBackofficeProvider
  processor: SprykerFeature\Glue\CustomerRelationManagement\Api\Processor\CustomerBackofficeProcessor

  paginationEnabled: true
  paginationItemsPerPage: 10

  operations:
    - type: Post
    - type: Get
    - type: GetCollection
    - type: Patch
    - type: Delete

  properties:
    idCustomer:
      type: integer
      description: The unique identifier of the customer.
      writable: false

    email:
      type: string
      description: The email address of the customer.
      required: true
      openapiContext:
        example: customer@example.com

    firstName:
      type: string
      description: The first name of the customer.
      required: true

    lastName:
      type: string
      description: The last name of the customer.
      required: true

    dateOfBirth:
      type: string
      description: The customer's date of birth.
      openapiContext:
        example: "1990-01-15"

    customerReference:
      type: string
      description: A unique reference for a customer.
      writable: false
      identifier: true

    isActive:
      type: boolean
      description: Indicates if the customer account is active.
      default: true
```

---

## Validation Schemas

Validation schemas define Symfony validation constraints for properties per HTTP operation.

### File Location

Place validation schemas alongside resource schemas:

```
{resourceName}.validation.yml
{resourceName}.validation.yaml
```

**Example:** `customers.validation.yml`

### Structure

```yaml
post:                           # Constraints for POST operations
  email:
    - NotBlank
    - Email
  firstName:
    - NotBlank
    - Length:
        max: 100

patch:                          # Constraints for PATCH operations
  email:
    - Optional:
        constraints:
          - NotBlank
          - Email
  firstName:
    - Optional:
        constraints:
          - NotBlank
          - Length:
              max: 100

delete:                         # Constraints for DELETE operations
  idCustomer:
    - NotBlank
    - Positive
```

### Available Constraints

All Symfony validation constraints are supported:
- `NotBlank`
- `NotNull`
- `Email`
- `Length` (with `min`, `max`)
- `Range` (with `min`, `max`)
- `Choice` (with `choices`)
- `Regex` (with `pattern`)
- `Positive`
- `PositiveOrZero`
- `Negative`
- `NegativeOrZero`
- `Url`
- `Uuid`
- And many more...

### Example

```yaml
post:
  email:
    - NotBlank:
        message: Email is required
    - Email:
        message: Invalid email format

  firstName:
    - NotBlank
    - Length:
        min: 2
        max: 100
        minMessage: First name must be at least 2 characters
        maxMessage: First name cannot exceed 100 characters

  dateOfBirth:
    - Date:
        message: Invalid date format

  isActive:
    - Type:
        type: boolean
        message: isActive must be a boolean value

patch:
  email:
    - Optional:
        constraints:
          - Email:
              message: Invalid email format
```

---

## Generation Process

The generation process transforms schema files into PHP resource classes through multiple stages.

### Step 1: Discovery

The system searches for schema files in configured source directories:

```
src/Spryker/*/resources/api/{apiType}/
src/SprykerFeature/*/resources/api/{apiType}/
src/Pyz/*/resources/api/{apiType}/
```

**Class:** `SchemaFinder::findSchemaFiles()`

### Step 2: Loading

Schema files are loaded and parsed using the Symfony YAML component.

**Class:**
- `YamlSchemaLoader::load()`

### Step 3: Parsing

The parser normalizes the loaded schema:

- Maps type aliases (`int` → `integer`, `bool` → `boolean`)
- Detects source layer (core/feature/project)
- Extracts API type from file path
- Associates validation schemas

**Class:** `SchemaParser::parse()`

### Step 4: Merging

When multiple schemas exist for the same resource across layers, they are merged following a priority hierarchy:

**Priority:** Core (lowest) → Feature → Project (highest)

**Class:** `SchemaMerger::merge()`

### Step 5: Validation

The merged schema is validated against rules:

- Required keys exist (`shortName`, `properties`)
- Property types are valid
- Operations are valid
- Provider and processor classes exist
- No conflicting configurations

**Class:** `SchemaValidator::validatePostMerge()`

### Step 6: Code Generation

The validated schema is transformed into PHP code:

- Namespace: `Generated\Api\{ApiType}`
- Class name: `{ResourceName}{ApiType}Resource`
- API Platform attributes added
- Properties with getters and setters
- Validation constraints as PHP attributes

**Class:** `ClassGenerator::generate()`

### Step 7: Writing

The generated PHP code is written to disk:

```
src/Generated/Api/{ApiType}/{ResourceName}{ApiType}Resource.php
```

**Class:** `ResourceGenerator::generateResources()`

### Step 8: Caching

Metadata is cached to optimize subsequent runs:

- Cache location: `{cacheDir}/{apiType}/schemas.meta`
- Tracks source files as dependencies
- Invalidates when source files change

**Class:** `GeneratorCache::isFresh()`

### Complete Flow Diagram

```
┌─────────────────────┐
│  Schema Files       │
│  *.yml, *.yaml      │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Discovery          │
│  SchemaFinder       │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Loading            │
│  YamlSchemaLoader   │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Parsing            │
│  SchemaParser       │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Merging            │
│  SchemaMerger       │
│  (Multi-layer)      │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Validation         │
│  SchemaValidator    │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Code Generation    │
│  ClassGenerator     │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Writing            │
│  ResourceGenerator  │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Generated Class    │
│  *.php              │
└─────────────────────┘
```

---

## Multi-Layer Architecture

The system supports defining schemas across three layers with automatic merging.

### Layer Priority

**Hierarchy:** Core → Feature → Project

- **Core** (`Spryker`): Base definitions (lowest priority)
- **Feature** (`SprykerFeature`): Feature-specific overrides (medium priority)
- **Project** (`Pyz`): Project-specific overrides (highest priority)

### Merging Behavior

When schemas for the same resource exist in multiple layers:

1. **Core schema** is used as base
2. **Feature schema** overrides/extends core
3. **Project schema** overrides/extends feature and core

**Properties:**
- New properties are added
- Existing properties are overridden completely
- No partial property merging (entire property definition is replaced)

**Operations:**
- Operations are merged by type
- Duplicate operation types are replaced

### Example

**Core schema** (`src/Spryker/Customer/resources/api/backoffice/customers.yml`):
```yaml
resource:
  name: Customers
  shortName: Customer

  operations:
    - type: Get
    - type: GetCollection

  properties:
    idCustomer:
      type: integer
      writable: false

    email:
      type: string
```

**Feature schema** (`src/SprykerFeature/CustomerRelationManagement/resources/api/backoffice/customers.yml`):
```yaml
resource:
  name: Customers
  description: Extended customer resource with CRM features

  provider: SprykerFeature\Glue\CustomerRelationManagement\Api\Provider\CustomerBackofficeProvider
  processor: SprykerFeature\Glue\CustomerRelationManagement\Api\Processor\CustomerBackofficeProcessor

  operations:
    - type: Post
    - type: Patch
    - type: Delete

  properties:
    email:
      type: string
      required: true
      description: The email address of the customer.

    firstName:
      type: string
      description: The first name of the customer.
```

**Merged result:**
```yaml
resource:
  name: Customers
  shortName: Customer
  description: Extended customer resource with CRM features

  provider: SprykerFeature\Glue\CustomerRelationManagement\Api\Provider\CustomerBackofficeProvider
  processor: SprykerFeature\Glue\CustomerRelationManagement\Api\Processor\CustomerBackofficeProcessor

  operations:
    - type: Get
    - type: GetCollection
    - type: Post
    - type: Patch
    - type: Delete

  properties:
    idCustomer:
      type: integer
      writable: false

    email:
      type: string
      required: true
      description: The email address of the customer.

    firstName:
      type: string
      description: The first name of the customer.
```

**Notice:**
- `description` from Feature overrides Core
- `provider` and `processor` added from Feature
- Operations merged (all 5 operations present)
- `email` property completely replaced by Feature definition
- `firstName` property added from Feature

---

## Generated Output

### File Location

```
src/Generated/Api/{ApiType}/{ResourceName}{ApiType}Resource.php
```

**Example:** `src/Generated/Api/Backoffice/CustomersBackofficeResource.php`

### Generated Class Structure

```php
<?php
/**
 * @generated 2025-11-24 07:58:45
 *
 * Source schema files:
 * - /data/src/SprykerFeature/CustomerRelationManagement/resources/api/backoffice/customers.yml
 *
 * Validation schema files:
 * - /data/src/SprykerFeature/CustomerRelationManagement/resources/api/backoffice/customers.validation.yml
 */

declare(strict_types=1);

namespace Generated\Api\Backoffice;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Validator\Constraints as Assert;
use SprykerFeature\Glue\CustomerRelationManagement\Api\Provider\CustomerBackofficeProvider;
use SprykerFeature\Glue\CustomerRelationManagement\Api\Processor\CustomerBackofficeProcessor;

#[ApiResource(
    operations: [
        new Post(),
        new Get(),
        new GetCollection(),
        new Patch(),
        new Delete(),
    ],
    shortName: 'Customer',
    provider: CustomerBackofficeProvider::class,
    processor: CustomerBackofficeProcessor::class,
    description: 'Customer resource for backoffice API',
    paginationItemsPerPage: 10
)]
final class CustomersBackofficeResource
{
    #[ApiProperty(
        description: 'The unique identifier of the customer.',
        writable: false
    )]
    public ?int $idCustomer = null;

    #[ApiProperty(description: 'The email address of the customer.')]
    #[Assert\NotBlank(groups: ['post'])]
    #[Assert\Email(groups: ['post'])]
    public ?string $email = null;

    #[ApiProperty(description: 'The first name of the customer.')]
    #[Assert\NotBlank(groups: ['post'])]
    #[Assert\Length(max: 100, groups: ['post'])]
    public ?string $firstName = null;

    #[ApiProperty(description: 'The last name of the customer.')]
    #[Assert\NotBlank(groups: ['post'])]
    #[Assert\Length(max: 100, groups: ['post'])]
    public ?string $lastName = null;

    #[ApiProperty(description: "The customer's date of birth.")]
    public ?string $dateOfBirth = null;

    #[ApiProperty(
        description: 'A unique reference for a customer.',
        writable: false,
        identifier: true
    )]
    public ?string $customerReference = null;

    #[ApiProperty(description: 'Indicates if the customer account is active.')]
    public ?bool $isActive = true;

    public function setIdCustomer(?int $idCustomer): self
    {
        $this->idCustomer = $idCustomer;

        return $this;
    }

    public function getIdCustomer(): ?int
    {
        return $this->idCustomer;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    // ... additional getters and setters for all properties

    public function toArray(): array
    {
        return [
            'idCustomer' => $this->idCustomer,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'dateOfBirth' => $this->dateOfBirth,
            'customerReference' => $this->customerReference,
            'isActive' => $this->isActive,
        ];
    }

    public static function fromArray(array $data): self
    {
        $resource = new self();

        if (isset($data['idCustomer'])) {
            $resource->setIdCustomer($data['idCustomer']);
        }
        if (isset($data['email'])) {
            $resource->setEmail($data['email']);
        }
        if (isset($data['firstName'])) {
            $resource->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $resource->setLastName($data['lastName']);
        }
        if (isset($data['dateOfBirth'])) {
            $resource->setDateOfBirth($data['dateOfBirth']);
        }
        if (isset($data['customerReference'])) {
            $resource->setcustomerReference($data['customerReference']);
        }
        if (isset($data['isActive'])) {
            $resource->setIsActive($data['isActive']);
        }

        return $resource;
    }
}
```

### Generated Elements

**1. File Header:**
- Generation timestamp
- Source schema files
- Validation schema files

**2. Namespace and Imports:**
- Namespace: `Generated\Api\{ApiType}`
- API Platform attributes
- Validation constraints
- Provider and processor classes

**3. Class-Level Attributes:**
- `#[ApiResource(...)]` with all metadata
- Operations list
- Provider and processor references
- Pagination settings

**4. Properties:**
- Public properties with null-safe types
- `#[ApiProperty(...)]` attributes
- Validation constraint attributes
- Default values

**5. Methods:**
- **Getters:** `getPropertyName()`
- **Setters:** `setPropertyName()` (fluent interface)
- **toArray():** Converts object to array
- **fromArray():** Creates object from array

---

## Console Commands

### api:generate

Generates API resources from schema files.

**Usage:**
```bash
bin/glue api:generate [apiType] [options]
```

**Arguments:**
- `apiType`: The API type to generate (e.g., `backoffice`, `storefront`)
  - If omitted, generates for all configured API types

**Options:**
- `--dry-run`: Show what would be generated without writing files
- `--validate-only`: Only validate schemas without generating
- `--force` / `-f`: Bypass cache and regenerate all resources
- `--resource=NAME` / `-r NAME`: Generate only specific resource

**Examples:**

Generate all resources for backoffice API:
```bash
bin/glue api:generate backoffice
```

Generate all resources for all API types:
```bash
bin/glue api:generate
```

Validate schemas without generating:
```bash
bin/glue api:generate backoffice --validate-only
```

Preview what would be generated:
```bash
bin/glue api:generate backoffice --dry-run
```

Force regeneration (ignore cache):
```bash
bin/glue api:generate backoffice --force
```

Generate only customers resource:
```bash
bin/glue api:generate backoffice --resource=customers
```

**Class:** `ApiGenerateCommand::execute()`

### api:debug

Debug and inspect API resources.

**Usage:**
```bash
bin/glue api:debug [resource] [options]
```

**Arguments:**
- `resource`: Resource name to inspect (optional)

**Options:**
- `--api=TYPE`: Filter by API type
- `--schema`: Show raw schema
- `--merged`: Show merged schema (all layers)
- `--validation`: Show validation rules
- `--generated`: Show generated class path
- `--all`: Show all information

**Examples:**

List all discovered resources:
```bash
bin/glue api:debug
```

List resources for specific API type:
```bash
bin/glue api:debug --api=backoffice
```

Show schema for specific resource:
```bash
bin/glue api:debug customers --schema
```

Show merged schema (all layers):
```bash
bin/glue api:debug customers --merged
```

Show validation rules:
```bash
bin/glue api:debug customers --validation
```

Show all information:
```bash
bin/glue api:debug customers --all
```

**Class:** `ApiDebugCommand::execute()`

---

## Best Practices

### Schema Organization

1. **Use descriptive resource names:** `customers.yml`, `orders.yml`
2. **Keep schemas focused:** One resource per file
3. **Use validation schemas:** Separate validation logic from resource definition
4. **Document properties:** Always add descriptions

### Property Definitions

1. **Use appropriate types:** Choose the most specific type
2. **Mark identifiers:** Set `identifier: true` for primary keys
3. **Control writability:** Use `writable: false` for read-only fields
4. **Set defaults:** Provide default values when appropriate
5. **Add examples:** Use `openapiContext.example` for documentation

### Multi-Layer Usage

1. **Core layer:** Define base structure and common properties
2. **Feature layer:** Extend with feature-specific properties and operations
3. **Project layer:** Customize for project-specific requirements
4. **Keep it minimal:** Only override what's necessary

### Validation

1. **Use appropriate constraints:** Choose constraints that match your requirements
2. **Provide clear messages:** Help users understand validation failures
3. **Organize by operation:** Different rules for POST vs PATCH
4. **Use Optional for PATCH:** Allow partial updates

### Generation

1. **Generate after changes:** Run `api:generate` after modifying schemas
2. **Use --validate-only:** Check schemas before generating
3. **Use --dry-run:** Preview changes before writing
4. **Commit generated files:** Include generated classes in version control

---

## Troubleshooting

### Schema Not Found

**Problem:** Schema file not discovered during generation.

**Solutions:**
- Check file location matches pattern: `resources/api/{apiType}/{resourceName}.yml`
- Verify API type is lowercase in directory path
- Ensure file extension is `.yml` or `.yaml`
- Check source directories configuration

### Validation Errors

**Problem:** Schema fails validation.

**Solutions:**
- Run `api:debug {resource} --schema` to inspect schema
- Check required keys are present: `shortName`, `properties`
- Verify property types are valid
- Ensure provider and processor classes exist
- Review error messages for specific issues

### Merge Conflicts

**Problem:** Unexpected result after merging multi-layer schemas.

**Solutions:**
- Run `api:debug {resource} --merged` to see merged result
- Remember: entire property definitions are replaced, not partially merged
- Check layer priority: Project > Feature > Core
- Review source files listed in generated class header

### Generation Fails

**Problem:** Generation command fails or produces errors.

**Solutions:**
- Run with `--validate-only` to check schemas first
- Check generated directory is writable
- Verify provider and processor classes exist
- Review console output for specific errors
- Try `--force` to bypass cache

### Cache Issues

**Problem:** Changes to schema not reflected in generated code.

**Solutions:**
- Use `--force` flag to bypass cache
- Clear cache manually: `rm -rf var/cache/{env}/schemas`
- Check file modification timestamps
- Verify schema file paths haven't changed

---

## Additional Resources

- **JSON Schema Definition:** `src/Spryker/ApiPlatform/resources/schemas/api-resource-schema-v1.json`
- **Example Schemas:** `src/SprykerFeature/*/resources/api/`
- **API Platform Documentation:** https://api-platform.com/
- **Symfony Validation:** https://symfony.com/doc/current/validation.html
