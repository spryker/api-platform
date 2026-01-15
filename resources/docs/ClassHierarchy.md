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
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/DependencyInjection/Compiler/ApiResourceServiceRegistrationPass.php - Service registration

**Cache Management**
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Cache/ApiResourceCacheWarmer.php - Warms API resource cache
- @src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Cache/ApiResourceCacheClearer.php - Clears API resource cache

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
- Key methods:
  - `generate(array $schema, string $apiType): string` - Main generation method
  - `generateValidationAttributes(...)` - Generates validation constraint attributes with deduplication
  - `deduplicateConstraintsByGroups(array $constraintsWithGroups): array` - Deduplicates constraints by signature and groups validation groups
  - `generateConstraintKey(mixed $constraint): string` - Creates unique signature for constraint comparison
  - `extractSourceFiles(array $schema): array` - Extracts all contributing source files from merged schema metadata
  - `generateConstraintAttribute(mixed $constraint, array $groups): string` - Generates constraint attribute with validation groups (changed from single group to array)

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

@src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/

### Base Test Classes

**ApiUnitTestCase** - Base class for unit tests
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/ApiUnitTestCase.php
- Extends: `Codeception\Test\Unit`
- Use for: isolated business logic tests without Symfony kernel or database
- Provides: type-safe mock creation, exception assertion helpers

### Codeception Actors

**ApiUnitTester** - Actor for unit tests
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/ApiUnitTester.php

**ApiIntegrationTester** - Actor for integration tests
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/ApiIntegrationTester.php

### Codeception Helpers

**ApiPlatformHelper** - Test suite cleanup helper
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/Helper/ApiPlatformHelper.php
- Cleans compiled Symfony test kernel cache after test suites

**ApiPlatformConfigBuilder** - Configuration builder for tests
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/Helper/ApiPlatformConfigBuilder.php

**ApiResourceGeneratorHelper** - Helper for resource generation testing
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/Helper/ApiResourceGeneratorHelper.php

**ApiSchemaHelper** - Helper for schema manipulation in tests
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/_support/Helper/ApiSchemaHelper.php

### Test Organization

**Unit Tests:**
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/Unit/Generator/ - Generator tests
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/Unit/Schema/ - Schema processing tests
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/Unit/Utility/ - Utility tests

**Integration Tests:**
- @src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/Integration/Command/ - Console command tests

### Writing Tests

When writing tests, extend `ApiUnitTestCase` and follow the given/when/then naming convention:

```php
public function testGivenSchemaFileWhenParsingThenReturnsValidSchema(): void
{
    // Arrange
    // Act
    // Assert
}
```
