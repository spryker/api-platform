# Constraint Deduplication Algorithm

## Problem Statement

When validation schemas are merged from multiple layers (core, feature, project), the same validation constraints may be defined in multiple layers. This leads to duplicate constraints in the final merged schema and subsequently in generated resource files.

### Example of Duplication

**Core validation schema** (Stores.validation.yaml):
```yaml
stores:
    post:
        name:
            - NotBlank
            - Length:
                  max: 100
```

**Project validation schema** (Stores.validation.yaml):
```yaml
stores:
    post:
        name:
            - NotBlank
            - Length:
                  max: 100
            - Regex:
                  pattern: '/^[a-zA-Z0-9_-]+$/'
```

**Current merged result** (with duplicates):
```yaml
stores:
    post:
        name:
            - NotBlank          # Duplicate from core
            - Length:           # Duplicate from core
                  max: 100
            - NotBlank          # Duplicate from project
            - Length:           # Duplicate from project
                  max: 100
            - Regex:            # New constraint from project
                  pattern: '/^[a-zA-Z0-9_-]+$/'
```

## Algorithm Design

### Input
- Array of validation schemas from different layers (ordered by precedence: core, feature, project)
- Each schema has structure: `{httpMethod: {fieldName: [constraints]}}`

### Output
- Merged validation schema with deduplicated constraints per field and HTTP method

### Algorithm Steps

#### 1. Group Constraints by Property and Validation Group
For each HTTP method and field name combination, collect all constraints from all layers.

#### 2. Normalize Constraints for Comparison
Each constraint needs to be normalized into a comparable format:
- Simple constraint (string): `"NotBlank"` → `{type: "NotBlank", parameters: {}}`
- Constraint with parameters (array): `["Length", {max: 100}]` → `{type: "Length", parameters: {max: 100}}`
- Nested constraint format: `{Length: {max: 100}}` → `{type: "Length", parameters: {max: 100}}`

#### 3. Identify Exact Duplicates
Two constraints are considered duplicates if:
- They have the same constraint type (e.g., both are "NotBlank" or both are "Length")
- They have identical parameters (deep comparison of parameter arrays/objects)

#### 4. Deduplicate While Preserving Order and Precedence
- Process constraints in order of appearance
- For each constraint, check if an identical constraint already exists in the result set
- If duplicate found: skip it
- If not duplicate: add to result set
- Maintain original order of first occurrence

#### 5. Handle Constraint Precedence
When constraints have the same type but different parameters:
- Keep BOTH constraints initially
- Later layers do NOT override earlier layers for different parameters
- Example: Core has `Length: {max: 100}`, Project has `Length: {max: 200}` → Keep both (this may indicate a schema conflict that should be resolved at the schema level)

**Note:** If the intent is for later layers to override earlier layers with same constraint type, the algorithm should be modified in step 4 to replace rather than skip.

### Pseudo Code

```
function deduplicateConstraints(constraints):
    seen = {}
    result = []

    for constraint in constraints:
        normalized = normalizeConstraint(constraint)
        signature = createSignature(normalized)

        if signature not in seen:
            seen[signature] = true
            result.append(constraint)  # Keep original format

    return result

function normalizeConstraint(constraint):
    if constraint is string:
        return {type: constraint, parameters: {}}

    if constraint is array with [name, params]:
        return {type: name, parameters: params}

    if constraint is object {ConstraintName: params}:
        name = firstKey(constraint)
        return {type: name, parameters: constraint[name]}

    return constraint

function createSignature(normalized):
    return hash(normalized.type + serialize(normalized.parameters))
```

### Algorithm Complexity
- Time: O(n × m) where n = number of layers, m = average constraints per field
- Space: O(m) for the seen set per field

## Examples

### Example 1: Simple Duplicate Removal

**Input:**
```yaml
# Layer 1 (Core)
post:
    email:
        - NotBlank
        - Email

# Layer 2 (Project)
post:
    email:
        - NotBlank
        - Email
        - Length:
              max: 255
```

**Output:**
```yaml
post:
    email:
        - NotBlank    # From Layer 1
        - Email       # From Layer 1
        - Length:     # From Layer 2 (new)
              max: 255
```

### Example 2: Complex Constraints with Parameters

**Input:**
```yaml
# Layer 1 (Core)
post:
    password:
        - NotBlank
        - Length:
              min: 8
              max: 128

# Layer 2 (Feature)
post:
    password:
        - NotBlank
        - Length:
              min: 8
              max: 128
        - Regex:
              pattern: '/^(?=.*[A-Z])/'

# Layer 3 (Project)
post:
    password:
        - NotBlank
        - Regex:
              pattern: '/^(?=.*[A-Z])/'
```

**Output:**
```yaml
post:
    password:
        - NotBlank    # From Layer 1 (duplicates in Layer 2 & 3 removed)
        - Length:     # From Layer 1 (duplicate in Layer 2 removed)
              min: 8
              max: 128
        - Regex:      # From Layer 2 (duplicate in Layer 3 removed)
              pattern: '/^(?=.*[A-Z])/'
```

### Example 3: Different Parameters (Keep Both)

**Input:**
```yaml
# Layer 1 (Core)
post:
    description:
        - Length:
              max: 100

# Layer 2 (Project)
post:
    description:
        - Length:
              max: 500
```

**Output (if keeping both):**
```yaml
post:
    description:
        - Length:
              max: 100
        - Length:
              max: 500
```

**Alternative Output (if project overrides):**
```yaml
post:
    description:
        - Length:
              max: 500
```

**Decision:** For Phase 4 implementation, we will **keep both** as this indicates a potential schema conflict. A later phase can add validation/warnings for conflicting constraints.

## Actual Implementation

### Implementation Location
The constraint deduplication is implemented in `ClassGenerator` (@src/Spryker/ApiPlatform/src/Spryker/ApiPlatform/Generator/ClassGenerator.php:372-428), not in `ValidationSchemaMerger`.

### How It Works

**Phase 1: Collect Constraints with Groups**
- Method: `generateValidationAttributes()`
- Collects all constraints from validation schemas for each operation (GET, POST, etc.)
- Each constraint is paired with its validation group (e.g., "post", "patch")
- Structure: `[{constraint: mixed, group: string}, ...]`

**Phase 2: Deduplicate by Signature**
- Method: `deduplicateConstraintsByGroups()`
- Groups constraints by unique signature using `generateConstraintKey()`
- Multiple occurrences of the same constraint across different validation groups are merged
- Validation groups are collected and deduplicated per constraint
- Structure: `[{constraint: mixed, groups: [string, ...]}, ...]`

**Phase 3: Generate Attributes**
- Method: `generateConstraintAttribute()`
- Generates PHP attributes with all validation groups
- Example: `#[Assert\NotBlank(groups: ['post', 'patch'])]`

### Constraint Signature Generation

The `generateConstraintKey()` method creates unique signatures:

```php
// String constraint: "NotBlank" → "NotBlank"
// Array constraint with options:
//   {Length: {max: 100}} → "Length_" + md5(serialize({max: 100}))
// Same type, different options → Different signatures
//   {Length: {max: 100}} vs {Length: {max: 200}} → Different keys
```

**Key Properties:**
- Deterministic and consistent
- Uses MD5 hash of serialized parameters
- Same constraint type + same parameters = same signature
- Same constraint type + different parameters = different signatures

### Benefits of This Approach

1. **No Duplicate Constraints** - Same constraint not repeated in generated code
2. **Grouped Validation Groups** - Single attribute with multiple groups instead of multiple attributes
3. **Clean Generated Code** - More readable and maintainable
4. **Proper Validation** - All validation groups still applied correctly

### Example Output

**Before Deduplication:**
```php
#[Assert\NotBlank(groups: ['post'])]
#[Assert\NotBlank(groups: ['patch'])]
#[Assert\Length(max: 100, groups: ['post'])]
#[Assert\Length(max: 100, groups: ['patch'])]
private string $name;
```

**After Deduplication:**
```php
#[Assert\NotBlank(groups: ['patch', 'post'])]
#[Assert\Length(max: 100, groups: ['patch', 'post'])]
private string $name;
```

## Implementation Notes

### Constraint Signature Generation
The signature must be deterministic and consistent:
- Serialize parameters in a canonical order (sorted keys)
- Handle nested arrays and objects
- Uses PHP's `serialize()` and `md5()` for hashing

### Testing Strategy
1. Unit tests for `deduplicateConstraintsByGroups()` with various input combinations
2. Test exact duplicates (same type, same parameters)
3. Test different constraints (same type, different parameters)
4. Test validation group merging and sorting
5. Integration test with full ClassGenerator

### Edge Cases
- Empty constraint arrays
- Null or missing parameters
- Nested constraint structures
- Custom constraint classes
- Constraints with complex parameter types (arrays, objects)
- Multiple validation groups per constraint
