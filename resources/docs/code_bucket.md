# CodeBucket Support for API Platform

## Overview

CodeBucket support enables the API Platform to generate specialized API resource classes for specific store contexts (CodeBuckets) while maintaining a common base implementation. CodeBuckets are detected via the URL and provided to the application via the `APPLICATION_CODE_BUCKET` environment variable.

**Examples:**
- `glue.de.spryker.local` → CodeBucket = `DE`
- `glue.at.spryker.local` → CodeBucket = `AT`

## Problem Statement

Projects need to define specific API behavior when an application runs in the context of a CodeBucket without duplicating the entire resource definition. The generator must:

1. Generate base resource classes for common functionality
2. Generate CodeBucket-specific resource classes that extend base behavior
3. Merge schemas properly so CodeBucket resources inherit from base resources
4. Support CodeBucket-specific validation rules

## Schema File Structure

### Resource Schema Files

CodeBucket resource schemas use the `codeBucket` property within the `resource` element:

```yaml
resource:
    name: Stores
    shortName: stores
    description: Store management API for Backend operations
    codeBucket: DE

    properties:
        germanSpecificField:
            type: string
            required: true
```

### Validation Schema Files

CodeBucket validation schemas use the `codeBucket` property at the root level:

```yaml
codeBucket: DE

post:
    name:
        - NotBlank:
            message: Store name is required (DE)
```

### File Location Examples

**Base Schemas (No CodeBucket):**
- Core: `src/Spryker/Store/resources/api/backend/stores.resource.yml`
- Project: `src/Pyz/Store/resources/api/backend/stores.resource.yml`

**CodeBucket Schemas:**
- Project DE: `src/Pyz/StoreDE/resources/api/backend/stores.resource.yml`
- Project AT: `src/Pyz/StoreAT/resources/api/backend/stores.resource.yml`

**Key Points:**
- Module name changes for CodeBucket schemas (e.g., `Store` → `StoreDE`)
- Resource file names remain the same: `stores.resource.yml`
- CodeBucket is specified via the `codeBucket` property in YAML, not inferred from directory structure

## Schema Processing Pipeline

### Phase 1: Schema Discovery

**Location:** SchemaFinder

All `*.resource.yml` files are discovered regardless of CodeBucket. The finder does not differentiate between base and CodeBucket schemas.

### Phase 2: Schema Parsing

**Location:** SchemaParser

The parser extracts the `codeBucket` property from resource schemas:

```php
$parsedSchema = [
    'name' => $this->getValue($resource, 'name', null),
    'shortName' => $this->getValue($resource, 'shortName', null),
    'codeBucket' => $this->getValue($resource, 'codeBucket', null),
    // ... other properties
];
```

For validation schemas, the `codeBucket` is extracted from the root level.

### Phase 3: Schema Grouping

**Location:** ResourceGenerator::parseResourceSchemas()

Schemas are grouped using a composite key that includes the CodeBucket:

**Grouping Strategy:**
- Base schemas (no codeBucket): `{resourceName}` (e.g., `stores`)
- CodeBucket schemas: `{resourceName}#{codeBucket}` (e.g., `stores#DE`)

**Example:**

Input schemas:
```
- stores.resource.yml (core, no codeBucket)
- stores.resource.yml (project, no codeBucket)
- stores.resource.yml (project StoreDE, codeBucket: DE)
- stores.resource.yml (project StoreAT, codeBucket: AT)
```

Grouped as:
```
- "stores" → [core schema, project schema]
- "stores#DE" → [project DE schema]
- "stores#AT" → [project AT schema]
```

**Implementation:**

```php
protected function generateGroupKey(array $schema, string $resourceName): string
{
    if (isset($schema['codeBucket']) && $schema['codeBucket'] !== null) {
        return sprintf('%s#%s', $resourceName, $schema['codeBucket']);
    }

    return $resourceName;
}
```

### Phase 4: Schema Merging with CodeBucket Inheritance

**Location:** SchemaMerger

CodeBucket schemas inherit from the merged base schema, ensuring consistency across all CodeBucket variants.

**Merging Strategy:**

1. **Base Schema Merging** (Standard Process):
   - Group: `stores`
   - Merge: core → feature → project
   - Result: Base merged schema

2. **CodeBucket Schema Merging** (Inheritance Process):
   - For each CodeBucket group (e.g., `stores#DE`):
     - Start with a deep copy of the base merged schema
     - Merge CodeBucket-specific schemas on top: core → feature → project
     - Result: CodeBucket merged schema that inherits from base

**Example:**

Base schema (merged from core + project):
```yaml
name: Stores
properties:
    name:
        type: string
        required: true
    timezone:
        type: string
```

CodeBucket DE schema:
```yaml
name: Stores
codeBucket: DE
properties:
    germanSpecificField:
        type: string
        required: true
```

Result after merging with inheritance:
```yaml
name: Stores
codeBucket: DE
properties:
    name:              # Inherited from base
        type: string
        required: true
    timezone:          # Inherited from base
        type: string
    germanSpecificField:  # Added by DE CodeBucket
        type: string
        required: true
```

**Benefits:**
- CodeBucket schemas inherit all base properties automatically
- CodeBucket schemas only define differences or additions
- Reduces duplication and maintains consistency
- Changes to base schemas propagate to all CodeBuckets

**New Method in SchemaMerger:**

```php
protected function mergeWithCodeBucketInheritance(
    array $codeBucketSchemas,
    array $baseSchema,
    string $resourceName,
    string $apiType
): array
{
    // Deep copy base schema to avoid mutation
    $result = $this->deepCopy($baseSchema);

    // Merge CodeBucket-specific schemas on top
    // Following layer precedence: core → feature → project
    $result = $this->merge($codeBucketSchemas, $resourceName, $apiType);

    return $result;
}
```

### Phase 5: Validation Schema Merging

**Location:** ResourceGenerator::parseValidationSchemas()

Validation schemas follow the same grouping strategy:

**Validation Key Generation:**

```php
protected function generateValidationKey(
    string $filePath,
    string $apiType,
    ?string $codeBucket = null
): string
{
    $fileName = basename($filePath, '.validation.yml');
    $fileName = basename($fileName, '.validation.yaml');

    $key = sprintf('%s_%s', $apiType, $fileName);

    if ($codeBucket !== null) {
        $key .= sprintf('#%s', $codeBucket);
    }

    return $key;
}
```

**Examples:**
- Base validation: `backend_stores`
- DE validation: `backend_stores#DE`
- AT validation: `backend_stores#AT`

**Validation Inheritance:**

CodeBucket validation schemas inherit from base validation schemas through the schema merging process. When a CodeBucket resource schema is merged with the base schema, the associated validation schemas are also merged, allowing CodeBucket-specific validations to extend or override base validations.

### Phase 6: Code Generation

**Location:** ClassGenerator

Class names are generated based on the presence of a CodeBucket identifier:

**Class Name Generation:**

```php
protected function generateClassName(
    string $resourceName,
    string $apiType,
    ?string $codeBucket = null
): string
{
    $resourceName = ResourceNameNormalizer::normalize($resourceName);

    if ($codeBucket !== null) {
        return sprintf('%s%s%sResource', $resourceName, $codeBucket, $apiType);
    }

    return sprintf('%s%sResource', $resourceName, $apiType);
}
```

**Generated Class Names:**
- Base: `StoresBackendResource`
- DE CodeBucket: `StoresDEBackendResource`
- AT CodeBucket: `StoresATBackendResource`

**Namespace:**

All generated classes use the same namespace structure:
```
Generated\Api\{ApiType}\
```

Example: `Generated\Api\Backend\StoresDEBackendResource`

## Complete Example

### Input Files

**Core Base Schema** (`src/Spryker/Store/resources/api/backend/stores.resource.yml`):

```yaml
resource:
    name: Stores
    shortName: stores
    description: Store management API for Backend operations

    provider: Spryker\Glue\Store\Api\Backend\Provider\StoresBackendProvider
    processor: Spryker\Glue\Store\Api\Backend\Processor\StoresBackendProcessor

    operations:
        - type: Get
        - type: GetCollection
        - type: Post

    properties:
        name:
            type: string
            required: true
            identifier: true

        timezone:
            type: string

        countries:
            type: array
```

**Core Base Validation** (`src/Spryker/Store/resources/api/backend/stores.validation.yml`):

```yaml
post:
    name:
        - NotBlank:
            message: Store name is required
        - Regex:
            pattern: '/^[A-Z][A-Z0-9_]*$/'
            message: Store name must be uppercase
```

**Project CodeBucket DE Schema** (`src/Pyz/StoreDE/resources/api/backend/stores.resource.yml`):

```yaml
resource:
    name: Stores
    shortName: stores
    codeBucket: DE

    properties:
        taxRate:
            type: number
            description: German-specific tax rate
            required: true

        germanyComplianceField:
            type: string
            required: true
```

**Project CodeBucket DE Validation** (`src/Pyz/StoreDE/resources/api/backend/stores.validation.yml`):

```yaml
codeBucket: DE

post:
    taxRate:
        - NotBlank:
            message: Tax rate is required for German stores
        - Range:
            min: 0
            max: 100
            notInRangeMessage: Tax rate must be between 0 and 100
```

### Processing Flow

**Step 1: Schema Discovery**

Found schemas:
```
- src/Spryker/Store/resources/api/backend/stores.resource.yml (core, base)
- src/Spryker/Store/resources/api/backend/stores.validation.yml (core, base)
- src/Pyz/StoreDE/resources/api/backend/stores.resource.yml (project, DE)
- src/Pyz/StoreDE/resources/api/backend/stores.validation.yml (project, DE)
```

**Step 2: Schema Parsing**

Parsed schemas with codeBucket property extracted:
```
- Schema 1: {name: "Stores", codeBucket: null, layer: "core"}
- Schema 2: {name: "Stores", codeBucket: "DE", layer: "project"}
```

**Step 3: Schema Grouping**

```
Group "stores":
  - core base schema

Group "stores#DE":
  - project DE schema
```

**Step 4: Base Schema Merging**

```
Group "stores" merged result:
  name: Stores
  operations: [Get, GetCollection, Post]
  properties:
    name: {type: string, required: true}
    timezone: {type: string}
    countries: {type: array}
  validation:
    post:
      name: [NotBlank, Regex]
```

**Step 5: CodeBucket Schema Merging with Inheritance**

```
Group "stores#DE" merged result (inherits from "stores"):
  name: Stores
  codeBucket: DE
  operations: [Get, GetCollection, Post]        # Inherited
  properties:
    name: {type: string, required: true}        # Inherited
    timezone: {type: string}                    # Inherited
    countries: {type: array}                    # Inherited
    taxRate: {type: number, required: true}     # Added by DE
    germanyComplianceField: {type: string, required: true}  # Added by DE
  validation:
    post:
      name: [NotBlank, Regex]                   # Inherited
      taxRate: [NotBlank, Range]                # Added by DE
```

**Step 6: Code Generation**

Generated files:
```
src/Generated/Api/Backend/StoresBackendResource.php
src/Generated/Api/Backend/StoresDEBackendResource.php
```

### Generated Class Example

**StoresDEBackendResource.php:**

```php
<?php

declare(strict_types=1);

namespace Generated\Api\Backend;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'stores',
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
    ],
    provider: 'Spryker\Glue\Store\Api\Backend\Provider\StoresBackendProvider',
    processor: 'Spryker\Glue\Store\Api\Backend\Processor\StoresBackendProcessor'
)]
class StoresDEBackendResource
{
    #[Assert\NotBlank(message: 'Store name is required', groups: ['post'])]
    #[Assert\Regex(pattern: '/^[A-Z][A-Z0-9_]*$/', message: 'Store name must be uppercase', groups: ['post'])]
    public string $name;

    public string $timezone;

    public array $countries;

    #[Assert\NotBlank(message: 'Tax rate is required for German stores', groups: ['post'])]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Tax rate must be between 0 and 100', groups: ['post'])]
    public float $taxRate;

    public string $germanyComplianceField;
}
```

## Design Decisions

### 1. Grouping Separator

**Decision:** Use `#` as separator for CodeBucket groups

**Format:** `{resourceName}#{codeBucket}`

**Examples:**
- `stores#DE`
- `stores#AT`
- `products#UK`

**Rationale:**
- Not used in file names or resource names
- Provides clear visual separation
- Easy to parse with `explode('#', $groupKey)`
- Standard separator for identifiers with variants

### 2. Inheritance Strategy

**Decision:** CodeBucket schemas inherit from the merged base schema

**Process:**
1. Merge all base schemas (core → feature → project) first
2. For each CodeBucket, start with base merged schema as foundation
3. Merge CodeBucket-specific schemas on top

**Rationale:**
- Ensures consistency across all CodeBuckets
- Reduces duplication in CodeBucket schema definitions
- CodeBucket schemas only define differences or additions
- Changes to base automatically propagate to all CodeBuckets
- Follows the DRY principle

### 3. Layer Precedence

**Decision:** Maintain standard Spryker layer precedence for CodeBucket schemas

**Precedence:** core → feature → project (for both base and CodeBucket)

**Process:**
- Base merging: core base → feature base → project base
- CodeBucket merging: (merged base) → core CodeBucket → feature CodeBucket → project CodeBucket

**Rationale:**
- Consistent with existing Spryker architecture
- Predictable override behavior
- Allows core and features to provide CodeBucket-specific functionality

### 4. CodeBucket Detection

**Decision:** Require explicit `codeBucket` property in YAML schemas

**Format:**
```yaml
resource:
    codeBucket: DE
```

**Rationale:**
- Explicit and clear intent
- Not dependent on directory structure
- Easy to validate and debug
- Flexible module naming (StoreDE, StoreDeutschland, etc.)
- Prevents accidental CodeBucket detection

### 5. Class Naming Convention

**Decision:** Use `{Resource}{CodeBucket}{ApiType}Resource` format

**Examples:**
- `StoresDEBackendResource`
- `ProductsATStorefrontResource`
- `OrdersUKBackendResource`

**Rationale:**
- CodeBucket appears in natural position (middle)
- Maintains readability
- Clear relationship to base resource class
- Follows existing Spryker naming patterns

### 6. Error Handling

**Decision:** Allow CodeBucket schemas without base schema

**Behavior:**
- If base schema exists: Inherit and extend
- If no base schema exists: Generate standalone CodeBucket resource

**Rationale:**
- Provides flexibility for CodeBucket-only resources
- Avoids blocking valid use cases
- Maintains backward compatibility

## Implementation Checklist

### Phase 1: Schema Parsing
- [ ] Update `SchemaParser::parse()` to extract `codeBucket` from resource element
- [ ] Update `ValidationSchemaLoader` to extract `codeBucket` from root level
- [ ] Add unit tests for codeBucket extraction

### Phase 2: Schema Grouping
- [ ] Update `ResourceGenerator::generateGroupKey()` to include codeBucket
- [ ] Update `ResourceGenerator::generateValidationKey()` to include codeBucket
- [ ] Add unit tests for grouping logic with CodeBuckets

### Phase 3: Schema Merging
- [ ] Add `SchemaMerger::mergeWithCodeBucketInheritance()` method
- [ ] Update `ResourceGenerator::mergeResourceSchemas()` to detect and process CodeBucket groups
- [ ] Implement base schema cloning before CodeBucket merge
- [ ] Add unit tests for CodeBucket inheritance merging

### Phase 4: Code Generation
- [ ] Update `ClassGenerator::generateClassName()` to accept codeBucket parameter
- [ ] Update `ClassGenerator::generate()` to extract codeBucket from schema
- [ ] Add unit tests for class name generation with CodeBuckets

### Phase 5: Integration
- [ ] Update `ResourceGenerator::generateResources()` pipeline
- [ ] Ensure base schemas are processed before CodeBucket schemas
- [ ] Add integration tests for end-to-end generation

### Phase 6: Documentation and Testing
- [ ] Create example schema files for testing
- [ ] Verify generated classes contain inherited properties
- [ ] Test validation schema merging with CodeBuckets
- [ ] Update user documentation with CodeBucket examples

## Testing Strategy

### Unit Tests

**SchemaParser Tests:**
```php
public function testGivenSchemaWithCodeBucketPropertyWhenParsingThenCodeBucketIsExtracted(): void
{
    // Arrange
    $rawSchema = [
        'resource' => [
            'name' => 'Stores',
            'codeBucket' => 'DE',
        ],
    ];

    // Act
    $result = $this->parser->parse($rawSchema, $file);

    // Assert
    $this->assertSame('DE', $result['codeBucket']);
}
```

**ResourceGenerator Grouping Tests:**
```php
public function testGivenSchemasWithAndWithoutCodeBucketWhenGroupingThenSeparateGroupsAreCreated(): void
{
    // Arrange
    $schemas = [
        ['name' => 'Stores', 'codeBucket' => null],
        ['name' => 'Stores', 'codeBucket' => 'DE'],
        ['name' => 'Stores', 'codeBucket' => 'AT'],
    ];

    // Act
    $grouped = $this->generator->groupSchemas($schemas);

    // Assert
    $this->assertArrayHasKey('stores', $grouped);
    $this->assertArrayHasKey('stores#DE', $grouped);
    $this->assertArrayHasKey('stores#AT', $grouped);
}
```

**SchemaMerger Tests:**
```php
public function testGivenBaseSchemaAndCodeBucketSchemaWhenMergingThenCodeBucketInheritsFromBase(): void
{
    // Arrange
    $baseSchema = [
        'properties' => [
            'name' => ['type' => 'string'],
            'timezone' => ['type' => 'string'],
        ],
    ];

    $codeBucketSchema = [
        'codeBucket' => 'DE',
        'properties' => [
            'taxRate' => ['type' => 'number'],
        ],
    ];

    // Act
    $result = $this->merger->mergeWithCodeBucketInheritance(
        [$codeBucketSchema],
        $baseSchema,
        'stores',
        'backend'
    );

    // Assert
    $this->assertArrayHasKey('name', $result['properties']);
    $this->assertArrayHasKey('timezone', $result['properties']);
    $this->assertArrayHasKey('taxRate', $result['properties']);
}
```

**ClassGenerator Tests:**
```php
public function testGivenSchemaWithCodeBucketWhenGeneratingClassNameThenCodeBucketIsIncluded(): void
{
    // Arrange
    $resourceName = 'Stores';
    $apiType = 'Backend';
    $codeBucket = 'DE';

    // Act
    $className = $this->generator->generateClassName($resourceName, $apiType, $codeBucket);

    // Assert
    $this->assertSame('StoresDEBackendResource', $className);
}
```

### Integration Tests

**End-to-End Generation Test:**
```php
public function testGivenBaseAndCodeBucketSchemasWhenGeneratingResourcesThenBothClassesAreGenerated(): void
{
    // Arrange
    $this->createSchemaFile('stores.resource.yml', $baseSchema);
    $this->createSchemaFile('stores.resource.yml', $codeBucketDESchema, 'StoreDE');

    // Act
    $results = iterator_to_array($this->generator->generateResources('backend'));

    // Assert
    $this->assertFileExists($this->outputDir . '/StoresBackendResource.php');
    $this->assertFileExists($this->outputDir . '/StoresDEBackendResource.php');
}
```

**Inheritance Verification Test:**
```php
public function testGivenCodeBucketResourceWhenGeneratedThenInheritsBaseProperties(): void
{
    // Arrange
    $baseSchema = $this->createBaseSchema();
    $codeBucketSchema = $this->createCodeBucketSchema('DE');

    // Act
    $results = iterator_to_array($this->generator->generateResources('backend'));

    // Assert
    $generatedClass = file_get_contents($this->outputDir . '/StoresDEBackendResource.php');
    $this->assertStringContainsString('public string $name', $generatedClass);
    $this->assertStringContainsString('public string $timezone', $generatedClass);
    $this->assertStringContainsString('public float $taxRate', $generatedClass);
}
```

## Edge Cases and Considerations

### 1. CodeBucket Schema Without Base Schema

**Scenario:** CodeBucket schema exists but no base schema

**Behavior:** Generate standalone CodeBucket resource

**Example:**
```yaml
# Only this file exists
resource:
    name: GermanOnlyResource
    codeBucket: DE
    properties:
        germanField: string
```

**Result:** `GermanOnlyResourceDEBackendResource` generated without inheritance

### 2. Multiple CodeBuckets for Same Resource

**Scenario:** Multiple CodeBucket schemas for the same resource

**Behavior:** Each generates a separate resource class

**Example:**
```
stores.resource.yml (base)
stores.resource.yml (DE)
stores.resource.yml (AT)
stores.resource.yml (UK)
```

**Result:**
- `StoresBackendResource`
- `StoresDEBackendResource`
- `StoresATBackendResource`
- `StoresUKBackendResource`

### 3. CodeBucket Property Override

**Scenario:** CodeBucket schema overrides a base property

**Behavior:** CodeBucket value takes precedence (standard merge behavior)

**Example:**

Base:
```yaml
properties:
    taxRate:
        type: number
        required: false
```

CodeBucket DE:
```yaml
properties:
    taxRate:
        required: true  # Override
```

Result: `taxRate` is required in DE CodeBucket resource

### 4. Validation Schema Matching

**Scenario:** Validation schema must match resource schema CodeBucket

**Requirement:** Both files must have matching `codeBucket` values

**Example:**

Correct:
```yaml
# stores.resource.yml
resource:
    codeBucket: DE

# stores.validation.yml
codeBucket: DE
```

Incorrect:
```yaml
# stores.resource.yml
resource:
    codeBucket: DE

# stores.validation.yml
codeBucket: AT  # Mismatch - will not be applied to DE resource
```

### 5. Case Sensitivity

**Behavior:** CodeBucket values are case-sensitive

**Examples:**
- `DE` and `de` are different CodeBuckets
- Will generate `StoresDEBackendResource` and `StoresdeBackendResource`

**Recommendation:** Use uppercase for consistency (e.g., `DE`, `AT`, `UK`)

## Summary

CodeBucket support extends the API Platform schema generation system to handle store-specific resource variations while maintaining code reuse through inheritance. The implementation follows Spryker's established patterns and integrates seamlessly with the existing schema processing pipeline.

**Key Features:**
- Explicit CodeBucket declaration via YAML property
- Automatic inheritance from base schemas
- Support for CodeBucket-specific validations
- Clean, predictable class naming
- Full backward compatibility with existing schemas

**Benefits:**
- Reduces code duplication across CodeBuckets
- Maintains consistency through inheritance
- Allows fine-grained customization per CodeBucket
- Supports multiple CodeBuckets per resource
- Easy to understand and maintain
