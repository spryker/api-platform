# CodeBucket Runtime Resolution Implementation

## Goal

Enable API Platform to resolve and use CodeBucket-specific resource classes at runtime without requiring separate container compilation per CodeBucket. The system must support hundreds of CodeBuckets with a single compiled container.

## Problem Statement

Previously, API Platform discovered all resource classes during container compilation:
- `StoresBackendResource` (base)
- `StoresEUBackendResource` (EU-specific with additional properties)
- Both have `shortName: 'stores'` and map to `/stores` endpoint

**Issue**: API Platform would use the last discovered resource (alphabetically: `StoresEUBackendResource`), ignoring the runtime `APPLICATION_CODE_BUCKET` value. This meant EU-specific resources were used even when `APPLICATION_CODE_BUCKET=AT` or for base requests.

**Constraint**: Cannot compile container per CodeBucket (hundreds of CodeBuckets, each taking 1+ minute to compile).

## Solution: Runtime Resource Resolution

Implemented a decorator pattern that intercepts API Platform's resource class resolution and selects the appropriate CodeBucket variant at runtime.

## Implementation Components

### 1. Shared CodeBucket Resolution Logic (Trait)

**File Created**:
- `src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResolverTrait.php`

**What it does**:
Provides shared methods for CodeBucket resolution used by both decorators:
- `buildCodeBucketClassName()`: Constructs CodeBucket-specific class names
- `removeCodeBucketFromClassName()`: Extracts base class name from CodeBucket variant
- `isCodeBucketResource()`: Checks if a class has a CODE_BUCKET constant
- `extractCodeBucketFromClass()`: Reads CODE_BUCKET constant value
- `getCurrentCodeBucket()`: Gets APPLICATION_CODE_BUCKET value

**Benefits**:
- DRY principle: Logic shared between ResourceClassResolver and ResourceNameCollectionFactory
- Consistency: Same naming patterns and detection logic everywhere
- Maintainability: Changes to resolution logic only needed in one place

### 2. CODE_BUCKET Constant Generation

**Files Modified**:
- `src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/ClassGenerator.php` (line 96)
- `src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/Template/PhpTemplateRenderer.php` (lines 15, 25, 113-120)

**What it does**:
- Adds `public const string CODE_BUCKET = 'EU';` to generated CodeBucket resource classes
- Base resources without CodeBucket do not get this constant
- Enables runtime identification of CodeBucket resources

**Example Output**:
```php
// StoresEUBackendResource.php
final class StoresEUBackendResource
{
    public const string CODE_BUCKET = 'EU';
    // ... properties
}

// StoresBackendResource.php (base - no constant)
final class StoresBackendResource
{
    // ... properties (no CODE_BUCKET constant)
}
```

### 3. CodeBucket Resource Name Collection Factory

**File Created**:
- `src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResourceNameCollectionFactory.php`

**What it does**:
Decorates API Platform's `ResourceNameCollectionFactoryInterface` to filter the resource collection at compile time and runtime based on APPLICATION_CODE_BUCKET.

**Filtering Strategy**:

When `APPLICATION_CODE_BUCKET` is **not set**:
- Include all base resources (resources without CODE_BUCKET constant)
- Exclude all CodeBucket variants (prevents duplicate registration)

When `APPLICATION_CODE_BUCKET` is set (e.g., `'EU'`):
1. Group resources by base class name
2. For each group:
   - If CodeBucket variant exists for current APPLICATION_CODE_BUCKET → include ONLY the variant
   - If no matching variant exists → include base resource (fallback)
   - Exclude all non-matching CodeBucket variants

**Example**:
```php
// APPLICATION_CODE_BUCKET = 'EU'
// Resources found:
// - StoresBackendResource (base)
// - StoresEUBackendResource (EU variant)
// - StoresATBackendResource (AT variant)
//
// Result: Only StoresEUBackendResource is included
```

**Why This Matters**:
- **OpenAPI Generation**: Only the EU resource schema appears in OpenAPI docs
- **Route Registration**: Only EU resource routes are registered
- **No Conflicts**: Base and CodeBucket variants never coexist in routing/OpenAPI

**Performance**:
- Filtering happens once during collection creation
- Cached by API Platform's metadata cache system
- No runtime overhead after initial warmup

### 4. CodeBucket Resource Class Resolver

**File Created**:
- `src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResourceClassResolver.php`

**What it does**:
Decorates API Platform's `ResourceClassResolverInterface` to add CodeBucket awareness.

**Resolution Logic**:
1. Intercepts all resource class resolution calls
2. Reads `APPLICATION_CODE_BUCKET` constant
3. If CodeBucket is set (e.g., `'EU'`):
   - Builds CodeBucket-specific class name from base resource
   - Pattern: `{ResourceName}{CodeBucket}{ApiType}Resource`
   - Example: `StoresBackendResource` + `EU` → `StoresEUBackendResource`
4. Checks if CodeBucket variant exists:
   - Uses `class_exists()` to verify class
   - Validates it's a valid API Platform resource
   - Reads `CODE_BUCKET` constant to confirm match
5. Returns CodeBucket variant if found, otherwise falls back to base resource

**Fallback Behavior**:
- If `APPLICATION_CODE_BUCKET=AT` but `StoresATBackendResource` doesn't exist
- Returns `StoresBackendResource` (graceful degradation)
- No errors, seamless fallback

**Performance**:
- Local cache (`$codeBucketResourceCache`) prevents repeated `class_exists()` checks
- Cache is per-request (no persistent storage needed)

### 5. Service Registration

**File Modified**:
- `src/Spryker/ApiPlatform/resources/config/services.php` (lines 23-24, 155-163)

**Service Definitions**:
```php
use Spryker\ApiPlatform\Metadata\CodeBucketResourceClassResolver;
use Spryker\ApiPlatform\Metadata\CodeBucketResourceNameCollectionFactory;

// CodeBucket Resource Class Resolver
$services->set(CodeBucketResourceClassResolver::class)
    ->decorate('api_platform.resource_class_resolver')
    ->arg('$decorated', service('.inner'));

// CodeBucket Resource Name Collection Factory
$services->set(CodeBucketResourceNameCollectionFactory::class)
    ->decorate('api_platform.metadata.resource.name_collection_factory.cached')
    ->arg('$decorated', service('.inner'));
```

**What This Does**:
- `CodeBucketResourceClassResolver`: Decorates runtime resource class resolution (for request handling)
- `CodeBucketResourceNameCollectionFactory`: Decorates resource collection discovery (for OpenAPI/routing)

Both decorators work together to ensure CodeBucket resources are properly filtered at all stages.

## How It Works at Runtime

### Request Flow

```
1. Request: GET /stores
   APPLICATION_CODE_BUCKET = 'EU'

2. Symfony Routing
   → Route matched: /stores

3. API Platform Metadata Resolution
   → Needs resource class for operation
   → Calls ResourceClassResolver::getResourceClass()

4. CodeBucketResourceClassResolver (Our Decorator)
   → Intercepts the call
   → Reads APPLICATION_CODE_BUCKET = 'EU'
   → Base class from decorated resolver: StoresBackendResource
   → Builds variant name: StoresEUBackendResource
   → Checks: class_exists('Generated\Api\Backend\StoresEUBackendResource')
   → Validates: StoresEUBackendResource::CODE_BUCKET === 'EU'
   → Returns: StoresEUBackendResource

5. API Platform Execution
   → Uses StoresEUBackendResource
   → Properties: base + taxRate + germanComplianceField + gdprContactEmail
   → Validations: base + EU-specific rules
```

### CodeBucket Detection

The resolver identifies CodeBucket resources using:
1. **Class existence**: `class_exists($codeBucketClassName)`
2. **Constant check**: `defined("$className::CODE_BUCKET")`
3. **Value match**: `constant("$className::CODE_BUCKET") === $currentCodeBucket`

### Class Name Building Strategy

**Pattern**: `{ResourceName}{CodeBucket}{ApiType}Resource`

**Examples**:
- Base: `StoresBackendResource`
- EU Backend: `StoresEUBackendResource`
- AT Backend: `StoresATBackendResource`
- EU Storefront: `StoresEUStorefrontResource`

**Algorithm**:
```php
// For Backend resources
str_replace('BackendResource', $codeBucket . 'BackendResource', $baseClassName)

// For Storefront resources
str_replace('StorefrontResource', $codeBucket . 'StorefrontResource', $baseClassName)
```

## Container Compilation

### Single Container for All CodeBuckets

**Key Point**: Container compiles once and contains ALL resources:
- All base resources (no CodeBucket)
- All CodeBucket variants (EU, AT, etc.)

**At Compile Time**:
1. API Platform discovers all resource classes in `src/Generated/Api/Backend/`
2. Builds metadata for ALL resources
3. Registers routes for ALL resources (all map to same URLs like `/stores`)
4. Caches everything

**At Runtime**:
1. Our resolver intercepts and selects correct resource
2. Only the selected resource is used for that request
3. Different CodeBuckets can be served from same container

## Important Characteristics

### URL Consistency
- All CodeBucket variants use the **same URL**
- Example: `/stores` for both `StoresBackendResource` and `StoresEUBackendResource`
- Only properties and validations differ between variants
- Routing is not affected by CodeBucket

### Fallback Mechanism
- If CodeBucket-specific resource doesn't exist → uses base resource
- No errors, no special handling needed
- Graceful degradation ensures system always works

### APPLICATION_CODE_BUCKET Source
- Set via environment constant: `APPLICATION_CODE_BUCKET`
- Typically domain-based resolution:
  - `glue.eu.spryker.local` → `APPLICATION_CODE_BUCKET='EU'`
  - `glue.at.spryker.local` → `APPLICATION_CODE_BUCKET='AT'`
- Handled by Spryker's `Environment::defineCodeBucket()` during bootstrap

## Testing

### Verify CODE_BUCKET Constant Generation

After regenerating resources:
```bash
docker/sdk cli sh -c "APPLICATION=GLUE_BACKEND php vendor/bin/glue api:generate backend"

# Check for constant
grep "CODE_BUCKET" src/Generated/Api/Backend/StoresEUBackendResource.php
# Expected: public const string CODE_BUCKET = 'EU';

# Verify base resource has no constant
grep "CODE_BUCKET" src/Generated/Api/Backend/StoresBackendResource.php
# Expected: no match (should be empty)
```

### Manual API Testing

```bash
# Test EU CodeBucket
curl -X GET http://glue-backend.eu.spryker.local/stores
# Should include: taxRate, germanComplianceField, gdprContactEmail

# Test base/fallback
curl -X GET http://glue-backend.spryker.local/stores
# Should not include EU-specific fields
```

## Current Status

### Completed
- ✅ ClassGenerator updated to generate CODE_BUCKET constant
- ✅ PhpTemplateRenderer renders constant in generated classes
- ✅ CodeBucketResourceClassResolver created and implemented
- ✅ Service registered in DI container
- ✅ Decorator pattern properly configured

### Ready for Testing
- Resources need regeneration to include CODE_BUCKET constant
- Runtime resolution ready to use immediately after cache clear
- No additional configuration needed

## Files Changed

### Created
1. `/src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResolverTrait.php` (new file, shared trait for CodeBucket logic)
2. `/src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResourceNameCollectionFactory.php` (new file, filters resource collections)
3. `/src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResourceClassResolver.php` (modified to use shared trait)

### Modified
1. `/src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/ClassGenerator.php`
   - Line 96: Added `codeBucket` to template data

2. `/src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/Template/PhpTemplateRenderer.php`
   - Line 15: Updated docblock to include `codeBucket: ?string`
   - Line 25: Added call to `renderCodeBucketConstant()`
   - Lines 113-120: New method `renderCodeBucketConstant()`

3. `/src/Spryker/ApiPlatform/resources/config/services.php`
   - Lines 23-24: Import statements for CodeBucket decorators
   - Lines 155-163: Service definitions for both decorators

4. `/src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Metadata/CodeBucketResourceClassResolver.php`
   - Added `use CodeBucketResolverTrait;` to share logic
   - Removed duplicate methods (now in trait)

## Known Limitations & Future Considerations

1. **Class Name Pattern Dependency**: Currently assumes `{Name}{CodeBucket}{ApiType}Resource` pattern
2. **No Validation of CodeBucket Value**: Assumes APPLICATION_CODE_BUCKET is always valid
3. **Cache Strategy**: Uses per-request cache; could add persistent cache for production
4. **No CodeBucket Inheritance**: CodeBucket resources don't inherit from base (full duplication in generated code)

## Next Steps for Further Development

1. Consider adding CodeBucket validation (whitelist of allowed values)
2. Add logging/debugging for CodeBucket resolution
3. Consider persistent caching strategy for resolution results
4. Add metrics for CodeBucket resource usage
5. Consider lazy loading of CodeBucket resources if memory becomes an issue
