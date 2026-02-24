# API Platform Module - Class Hierarchy

## Overview

The API Platform module provides schema-based API resource generation and integrates API Platform into Spryker. Resources are defined in YAML schemas and automatically generated into PHP classes with API Platform attributes.

## Core Components

### Symfony Integration

**SprykerApiPlatformBundle** - Main Symfony bundle
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/SprykerApiPlatformBundle.php
- Registers the `ApiResourceServiceRegistrationPass` compiler pass

**DependencyInjection**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/DependencyInjection/SprykerApiPlatformExtension.php - Bundle extension
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/DependencyInjection/Configuration.php - Configuration tree

**Compiler Passes:**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/DependencyInjection/Compiler/ApiResourceServiceRegistrationPass.php - Registers generated API resource services
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/DependencyInjection/Compiler/RelationshipConfigurationPass.php - Compiles relationship configurations into container parameters
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/DependencyInjection/Compiler/ApiPlatformDecoratorPass.php - Registers API Platform decorators

**Cache Management**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Cache/ApiResourceCacheWarmer.php - Warms API resource cache

### Console Commands

**ApiGenerateCommand** - Generate API resources from schema files
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Command/ApiGenerateCommand.php
- Command name: `api:generate`
- Supports: dry-run, validate-only, resource filtering

**ApiDebugCommand** - Debug API configuration and schemas
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Command/ApiDebugCommand.php

### Configuration

**ApiPlatformConfig** - Central configuration
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Configuration/ApiPlatformConfig.php
- Provides: source directories, cache directory, generated directory, API types, debug mode

### Resource Generation

**ResourceGenerator** - Orchestrates resource generation
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/ResourceGenerator.php (implements ResourceGeneratorInterface)
- Main method: `generateResources(string $apiType): Generator`
- Supports debug logging via LoggerInterface (optional, defaults to NullLogger)
- Uses result objects to communicate success and failure information
- Tracks multiple contributing source files from merged schemas via `extractSourceFilesFromMetadata()`
- Organized into distinct phases:

**Generation Pipeline:**
1. **Preparation Phase** (`prepareGeneration`)
   - Normalizes API type
   - Cleans output directory
   - Logs preparation details

2. **Schema Parsing Phase** (`parseSchemas`)
   - Orchestrates validation and resource schema parsing
   - Separates loading from parsing for clarity
   - Calls `loadValidationSchemas` to find validation schema files
   - Calls `parseValidationSchemas` to parse validation rules
   - Calls `loadResourceSchemas` to find all resource schema files
   - Calls `parseResourceSchemas` to parse and group schemas by resource
   - Returns `ParseResult` containing grouped schemas and any parsing failures

3. **Schema Merging Phase** (`mergeResourceSchemas`)
   - Merges schemas from multiple layers (core, feature, project)
   - Handles merge failures gracefully
   - Logs merge progress per resource
   - Returns `MergeResult` containing merged schemas and any merge failures

4. **Validation Phase** (`validateMergedSchemas`)
   - Validates each merged schema using SchemaValidator
   - Returns only valid schemas
   - Logs validation failures with details
   - Returns `ValidationResult` containing validated schemas and any validation failures

5. **Code Generation Phase** (`generateResourceFiles`)
   - Generates PHP classes for each validated schema
   - Writes files to output directory
   - Yields generation results with metadata
   - Handles generation errors per resource

**Result Objects:**
- `ParseResult` (@src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/Result/ParseResult.php)
  - Contains: groupedSchemas, failedValidationFiles, failedSchemaFiles
  - Purpose: Encapsulates parsing phase results with clear separation of success and failure cases
- `MergeResult` (@src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/Result/MergeResult.php)
  - Contains: mergedSchemas, failedMerges
  - Purpose: Encapsulates merging phase results
- `ValidationResult` (@src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/Result/ValidationResult.php)
  - Contains: validatedSchemas, failedValidations
  - Purpose: Encapsulates validation phase results

**Supporting Methods:**
- `loadValidationSchemas(string $apiType): array<SplFileInfo>` - Finds validation schema files
- `parseValidationSchemas(array $validationFiles, string $apiType, array &$failedFiles): array` - Parses validation schemas
- `loadResourceSchemas(string $apiType): array<SplFileInfo>` - Finds resource schema files
- `parseResourceSchemas(array $schemaFiles, array $validationSchemas, array &$failedFiles): array` - Parses resource schemas

**ClassGenerator** - Generates PHP classes from schemas
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/ClassGenerator.php (implements ClassGeneratorInterface)
- Uses: PhpTemplateRenderer to render class templates
- Collaborates with specialized generators:
  - UseStatementCollector - Collects and formats use statements
  - ResourceAttributeGenerator - Generates ApiResource attributes with operations
  - ValidationAttributeGenerator - Generates validation attributes with groups and deduplication
  - OpenApiOperationBuilder - Builds OpenAPI Operation with RequestBody and examples
- Key methods:
  - `generate(array $schema, string $apiType): string` - Main generation method
  - `extractSourceFiles(array $schema): array` - Extracts all contributing source files from merged schema metadata

**UseStatementCollector** - Collects use statements for generated classes
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/UseStatementCollector.php (implements UseStatementCollectorInterface)
- Analyzes schema and properties to determine needed imports

**ResourceAttributeGenerator** - Generates ApiResource attributes
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/ResourceAttributeGenerator.php (implements ResourceAttributeGeneratorInterface)
- Generates operations, provider, processor, and other resource metadata

**ValidationAttributeGenerator** - Generates validation attributes
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/ValidationAttributeGenerator.php (implements ValidationAttributeGeneratorInterface)
- Key methods:
  - `generate(...)` - Generates validation constraint attributes with deduplication
  - `deduplicateConstraintsByGroups(array $constraintsWithGroups): array` - Deduplicates constraints by signature and groups validation groups
  - `generateConstraintKey(mixed $constraint): string` - Creates unique signature for constraint comparison

**OpenApiOperationBuilder** - Builds OpenAPI operations
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/OpenApiOperationBuilder.php (implements OpenApiOperationBuilderInterface)
- Handles RequestBody, MediaType, and example generation for operations

**TagGenerator** - Generates OpenAPI tags for resources
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/TagGenerator.php
- Groups resources by tags for API documentation organization

**RelationshipPhpDocGenerator** - Generates PHPDoc for relationship properties
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/RelationshipPhpDocGenerator.php
- Creates type hints and documentation for included relationships

**PropertyAttributeGenerator** - Generates property attributes
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/PropertyAttributeGenerator.php (implements PropertyAttributeGeneratorInterface)
- Handles property-level API Platform attributes

**MediaType Formatters**

**MediaTypeFormatterRegistry** - Registry for format-specific formatters
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/MediaType/MediaTypeFormatterRegistry.php
- Manages collection of media type formatters

**JsonApiMediaTypeFormatter** - JSON:API format-specific formatter
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/MediaType/JsonApiMediaTypeFormatter.php (implements MediaTypeFormatterInterface)
- Generates JSON:API specific request body schema with attributes structure

**Template Rendering**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/Template/PhpTemplateRenderer.php (implements TemplateRendererInterface)

### Schema Processing

**Schema Lifecycle:**
1. **Load** - Find schema files (SchemaFinder → loadResourceSchemas / loadValidationSchemas)
2. **Load** - Read YAML schemas (YamlSchemaLoader)
3. **Parse** - Parse and enrich with validation schemas (SchemaParser → parseResourceSchemas)
   - Returns ParseResult with grouped schemas and parsing failures
4. **Merge** - Merge multi-layer schemas (SchemaMerger → mergeResourceSchemas)
   - Returns MergeResult with merged schemas and merge failures
5. **Validate** - Validate merged schemas (SchemaValidator → validateMergedSchemas with validation rules)
   - Returns ValidationResult with validated schemas and validation failures
6. **Generate** - Generate PHP classes (ClassGenerator → generateResourceFiles)

**Finder**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Finder/SchemaFinder.php (implements SchemaFinderInterface)
- Locates `*.resource.yml` and `*.resource.yaml` files in configured source directories

**Loader**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Loader/YamlSchemaLoader.php (implements SchemaLoaderInterface)
- Loads YAML schema files

**Parser**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Parser/SchemaParser.php (implements SchemaParserInterface)
- Parses raw schemas and applies validation schemas
- Property normalization: Only includes explicitly defined attributes (no default values), enabling proper schema merging across layers

**Merger**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Merger/SchemaMerger.php (implements SchemaMergerInterface)
- Merges schemas from core, feature, and project layers
- Tracks all contributing source files per layer via `_layerSourceFiles` metadata
- `createSourceInfo()` returns array of files instead of single file to support multiple sources per layer

**Validator**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Validator/SchemaValidator.php (implements SchemaValidatorInterface)
- Validates schemas using validation rules in @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Validator/Rules/

**Validation Rules** (all implement ValidationRuleInterface):
- MergeValidationRule - Validates merge configurations
- OperationValidationRule - Validates operations (GET, POST, etc.)
- PaginationValidationRule - Validates pagination settings
- ProcessorValidationRule - Validates processors
- PropertyValidationRule - Validates resource properties
- ProviderValidationRule - Validates providers
- ResourceNameValidationRule - Validates resource names
- ResourceNamingValidationRule - Validates naming conventions
- SecurityExpressionValidationRule - Validates security expressions
- RelationshipValidationRule - Validates relationship configurations (includes/includableIn bi-directional consistency)

### Validation Schema Support

**Validation Schema Lifecycle:**
1. Find validation files (ValidationSchemaFinder)
2. Load validation YAML (ValidationSchemaLoader)
3. Map validation groups (ValidationGroupMapper)
4. Merge validation schemas (ValidationSchemaMerger)

**Validation Components**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Validation/Finder/ValidationSchemaFinder.php (implements ValidationSchemaFinderInterface)
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Validation/Loader/ValidationSchemaLoader.php (implements ValidationSchemaLoaderInterface)
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Validation/Mapper/ValidationGroupMapper.php (implements ValidationGroupMapperInterface)
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Schema/Validation/Merger/ValidationSchemaMerger.php (implements ValidationSchemaMergerInterface)

### Relationship System

The relationship system enables resources to include related resources via the `?include=` query parameter with JSON:API compliance.

**Compiler Pass**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/DependencyInjection/Compiler/RelationshipConfigurationPass.php
- Compiles relationship configurations from resource schemas into container parameters
- Validates bi-directional consistency (includes ↔ includableIn)
- Creates ServiceLocator for provider access

**Provider Layer**

**RelationshipProvider** - Main relationship resolution service
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Provider/RelationshipProvider.php
- Uses container parameters for relationship configuration
- Maps URI variables from parent to child resources
- Calls child providers with proper context

**BatchLoadableProviderInterface** - Interface for batch loading optimization
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Provider/BatchLoadableProviderInterface.php
- Optional interface for providers to support batch loading
- Reduces N+1 query problems for relationship collections

**Relationship Resolver**

**ApiPlatformRelationshipResolver** - Core relationship resolution logic
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Relationship/ApiPlatformRelationshipResolver.php (implements ApiPlatformRelationshipResolverInterface)
- Key methods:
  - `resolveRelationships(string $mainResourceType, array $mainResources, array $requestedIncludes, array $context): array` - Resolves relationships for resources
  - `parseIncludeParameter(array $context): array` - Parses `?include=` query parameter

**RelationshipData** - Data transfer object for relationships
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Relationship/RelationshipData.php
- Contains relationship name, resources, and pagination info

**RelationshipPagination** - Pagination information for relationships
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Relationship/RelationshipPagination.php
- Stores pagination metadata for related resource collections

**YAML Configuration:**
```yaml
# Parent resource
includes:
  - relationshipName: addresses
    targetResource: CustomersAddresses
    uriVariableMappings:
      customerReference: customerReference

# Child resource
includableIn:
  - resource: Customers
    relationshipName: addresses
```

See [Relationships.md](Relationships.md) for comprehensive documentation.

### OpenAPI Components

**OpenAPI Decorators**

**OpenApiDecorator** - Main OpenAPI specification decorator
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/OpenApi/Decorator/OpenApiDecorator.php
- Enhances generated OpenAPI documentation with custom metadata
- Applies format-specific transformations

**Format Transformers**

**JsonApiFormatTransformer** - JSON:API format transformer
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/OpenApi/FormatTransformer/JsonApiFormatTransformer.php (implements FormatTransformerInterface)
- Transforms OpenAPI schemas to match JSON:API format structure
- Adds `data`, `attributes`, `relationships`, and `included` structures

**OpenAPI Normalizers**

Response normalizers that format API responses according to JSON:API specification:

**IdNormalizer** - Normalizes resource identifiers
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/OpenApi/Normalizer/IdNormalizer.php
- Extracts and formats resource IDs from various identifier properties
- Priority: High (runs early in normalization chain)

**SelfLinkNormalizer** - Adds self links to resources
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/OpenApi/Normalizer/SelfLinkNormalizer.php
- Generates `links.self` for each resource according to JSON:API spec
- Constructs URLs from operation metadata and URI variables

**EmptyRelationshipNormalizer** - Handles empty relationships
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/OpenApi/Normalizer/EmptyRelationshipNormalizer.php
- Ensures empty relationships return `[]` instead of null
- Maintains JSON:API compliance for relationship structures

**LinksPositionNormalizer** - Controls links position in response
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/OpenApi/Normalizer/LinksPositionNormalizer.php
- Ensures `links` section appears after `attributes` per JSON:API spec

### Code Bucket Support

Code bucket support enables multi-tenancy by organizing API resources per code bucket (e.g., per merchant or region).

**CodeBucketResourceClassResolver** - Resolves resource classes per code bucket
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResourceClassResolver.php
- Maps resource names to generated classes within specific code buckets

**CodeBucketResourceNameCollectionFactory** - Creates resource collections per code bucket
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResourceNameCollectionFactory.php
- Discovers available resources for each code bucket
- Supports filtered resource discovery

**CodeBucketResolverTrait** - Shared code bucket resolution logic
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResolverTrait.php
- Provides common methods for code bucket detection and resolution

### Utilities

**ApiTypeNormalizer** - Normalizes API type casing
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Utility/ApiTypeNormalizer.php

**ResourceNameNormalizer** - Normalizes resource names
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Utility/ResourceNameNormalizer.php

### Exceptions

All exceptions extend `ApiSchemaException`:
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Exception/ApiSchemaException.php - Base exception
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Exception/ApiSchemaGenerationException.php - Generation errors
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Exception/ApiSchemaMergeException.php - Merge errors
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Exception/ApiSchemaValidationException.php - Validation errors

## Test Infrastructure

### Test Directory Structure

```
tests/SprykerTest/ApiPlatform/
├── Unit/                              # Unit tests (no Symfony kernel)
│   ├── Generator/                     # Generator component tests
│   ├── Schema/                        # Schema processing tests
│   ├── Utility/                       # Utility tests
│   └── ...
├── Integration/                       # Integration tests (with Symfony kernel)
│   ├── Command/                       # Console command tests
│   └── ...
├── _support/                          # Test support classes
│   ├── Helper/                        # Codeception helpers
│   ├── ApiUnitTester.php             # Unit test actor
│   ├── ApiIntegrationTester.php      # Integration test actor
│   └── ApiUnitTestCase.php           # Base unit test class
└── _data/                            # Test fixtures and data
    ├── schemas/                       # Sample YAML schemas for testing
    └── symfony_test_kernel_cache/     # Symfony test kernel cache
```

### Base Test Classes

**ApiUnitTestCase** - Base class for unit tests
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/ApiUnitTestCase.php
- Extends: `Codeception\Test\Unit`
- Purpose: Isolated business logic tests without Symfony kernel or database
- Features:
  - Type-safe mock creation helpers
  - Exception assertion helpers
  - Consistent test structure enforcement
- Usage: Extend for all unit tests in the ApiPlatform module

**BackendApiTestCase** - Base class for Backend API endpoint tests
- Location: @src/SprykerTest/ApiPlatform/Test/BackendApiTestCase.php
- Extends: Symfony API Platform test case
- Purpose: Full-stack API endpoint testing with authentication and database
- Features:
  - Symfony HTTP client integration
  - Database transaction management
  - Authentication helpers
  - JSON:API assertion methods
- Usage: Extend for all Backend API endpoint tests

**StorefrontApiTestCase** - Base class for Storefront API endpoint tests
- Location: @src/SprykerTest/ApiPlatform/Test/StorefrontApiTestCase.php
- Extends: Symfony API Platform test case
- Purpose: Full-stack Storefront API endpoint testing
- Features:
  - Customer authentication
  - Anonymous access testing
  - Cart and session management
- Usage: Extend for all Storefront API endpoint tests

### Codeception Actors

**ApiUnitTester** - Actor for unit tests
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/ApiUnitTester.php
- Purpose: Provides test helper methods for unit tests
- Available methods defined in Codeception helpers

**ApiIntegrationTester** - Actor for integration tests
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/ApiIntegrationTester.php
- Purpose: Provides test helper methods for integration tests
- Available methods defined in Codeception helpers

### Codeception Helpers

**ApiPlatformHelper** - Test suite cleanup helper
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/Helper/ApiPlatformHelper.php
- Purpose: Manages test environment lifecycle
- Features:
  - Cleans compiled Symfony test kernel cache after test suites
  - Ensures clean state between test runs
  - Prevents cache pollution across test suites

**ApiPlatformConfigBuilder** - Configuration builder for tests
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/Helper/ApiPlatformConfigBuilder.php
- Purpose: Fluent interface for building test configurations
- Usage:
  ```php
  $config = ApiPlatformConfigBuilder::create()
      ->withSourceDirectory('/path/to/source')
      ->withCacheDir('/path/to/cache')
      ->withDebugMode(true)
      ->build();
  ```

**ApiResourceGeneratorHelper** - Helper for resource generation testing
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/Helper/ApiResourceGeneratorHelper.php
- Purpose: Simplifies testing of resource generation pipeline
- Features:
  - Creates temporary test directories
  - Generates sample schemas
  - Verifies generated output
  - Cleans up after tests

**ApiSchemaHelper** - Helper for schema manipulation in tests
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/Helper/ApiSchemaHelper.php
- Purpose: Provides schema creation and manipulation utilities
- Features:
  - Creates valid test schemas
  - Applies schema transformations
  - Validates schema structures
  - Loads schema fixtures

### Test Organization

**Unit Tests** - Fast, isolated component tests
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/Unit/
- Subdirectories:
  - `Generator/` - ClassGenerator, ResourceGenerator, validators
  - `Schema/` - Parser, Merger, Validator, Finder, Loader
  - `Utility/` - Normalizers, formatters, helpers
  - `Relationship/` - Relationship resolver tests
  - `OpenApi/` - OpenAPI decorator and normalizer tests
- Characteristics:
  - No Symfony kernel
  - No database access
  - Uses mocks for dependencies
  - Execution time: < 1 second per test

**Integration Tests** - Full-stack tests with Symfony kernel
- Location: @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/Integration/
- Subdirectories:
  - `Command/` - Console command tests (api:generate, api:debug)
  - `Api/` - Full API endpoint tests with HTTP client
- Characteristics:
  - Symfony kernel loaded
  - Database transactions
  - Real HTTP requests
  - Execution time: 1-5 seconds per test

### Test Naming Convention

**MANDATORY**: Use `given/when/then` syntax with maximum expressiveness and proper grammar:

```php
// Unit Tests
public function testGivenValidSchemaWhenParsingThenReturnsValidParsedSchema(): void
public function testGivenInvalidPropertyTypeWhenValidatingThenValidationFails(): void
public function testGivenMultipleLayerSchemasWhenMergingThenPropertiesAreMergedCorrectly(): void

// API Integration Tests
public function testGivenValidDataWhenCreatingStoreViaPostThenStoreIsCreatedSuccessfully(): void
public function testGivenInvalidDataWhenCreatingCustomerViaPostThenValidationErrorIsReturned(): void
public function testGivenExistingResourceWhenRetrievingViaGetThenResourceDataIsReturned(): void
public function testGivenMultipleResourcesWhenRetrievingCollectionWithPaginationThenPaginatedResultsAreReturned(): void
```

### Test Body Structure

**MANDATORY**: Always use this four-part structure with section comments:

```php
public function testExample(): void
{
    // Arrange
    $schema = ['resource' => ['name' => 'Test']];
    $parser = new SchemaParser();

    // Act
    $result = $parser->parse($schema);

    // Assert
    $this->assertInstanceOf(ParsedSchema::class, $result);
    $this->assertSame('Test', $result->getName());
}
```

For exception testing:
```php
public function testExample(): void
{
    // Arrange
    $invalidSchema = ['resource' => []];

    // Act & Expect
    $this->expectException(ValidationException::class);
    $parser->parse($invalidSchema);
}
```

### Test Annotations

**MANDATORY**: Include these group annotations on every test class:

```php
/**
 * @group SprykerTest
 * @group Glue
 * @group ApiPlatform
 * @group {SpecificComponent}  // e.g., Generator, Schema, Relationship
 * @group {TestClassName}
 */
class SchemaParserTest extends ApiUnitTestCase
{
    // ...
}
```

### Test Data Management

**Test Fixtures Location:**
- Schema fixtures: `tests/SprykerTest/ApiPlatform/_data/schemas/`
- Validation fixtures: `tests/SprykerTest/ApiPlatform/_data/validation/`
- Generated output: `tests/SprykerTest/ApiPlatform/_data/generated/` (gitignored)

**Creating Test Schemas:**
```php
// Use ApiSchemaHelper for consistent schema creation
$schema = $this->tester->haveResourceSchema([
    'name' => 'TestResource',
    'shortName' => 'test-resource',
    'properties' => [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string'],
    ],
]);
```

### Performance Considerations

**Test Execution Speed:**
- Unit tests: Target < 1 second total for all tests in a class
- Integration tests: Target < 5 seconds per test
- Use `@group slow` for tests taking > 5 seconds

**Optimizations Applied:**
- Symfony test kernel cache reuse across tests
- Database transaction rollback instead of database rebuild
- Lazy loading of test fixtures
- Shared test data setup via Codeception helpers

### Testing Best Practices

**Do:**
- ✅ Test entry points (Facades, Commands, API endpoints)
- ✅ Test edge cases and error conditions
- ✅ Use descriptive test names with given/when/then
- ✅ Keep test body to max 3-5 lines per section
- ✅ Use helper methods to improve readability
- ✅ Test one behavior per test method
- ✅ Use appropriate base class (Unit vs Integration)

**Don't:**
- ❌ Test internal method flows (test outcomes, not implementation)
- ❌ Use global setup methods for test class (setup per test method)
- ❌ Create unnecessary test data
- ❌ Test framework code or third-party libraries
- ❌ Write tests that depend on execution order
- ❌ Mock what you don't own (use integration tests instead)

### Running Tests

**Run all ApiPlatform tests:**
```bash
docker/sdk testing -x MODULE=ApiPlatform
```

**Run specific test suite:**
```bash
# Unit tests only
docker/sdk testing -x MODULE=ApiPlatform vendor/bin/codecept run Unit

# Integration tests only
docker/sdk testing -x MODULE=ApiPlatform vendor/bin/codecept run Integration
```

**Run specific test class:**
```bash
docker/sdk testing -x MODULE=ApiPlatform vendor/bin/codecept run Unit Schema/Parser/SchemaParserTest
```

**Run tests with coverage:**
```bash
docker/sdk testing -x MODULE=ApiPlatform XDEBUG_MODE=coverage vendor/bin/codecept run --coverage --coverage-html
```

For comprehensive testing guidelines, see [testing-guideline.md](../guides/testing-guideline.md).
