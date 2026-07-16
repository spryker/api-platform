# ApiPlatform Module

[![Latest Stable Version](https://poser.pugx.org/spryker/api-platform/v/stable.svg)](https://packagist.org/packages/spryker/api-platform)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net/)

## Installation

```
composer require spryker/api-platform
```

## Canonical nested objects (`*.object.yml`)

A project can define the canonical inner shape of a nested object once and have it flow into every resource that tags the matching `objectName`. With no object files present the generator output is byte-for-byte identical to the default behavior — it is a pure project opt-in.

### File location

Canonical object files live in a **dedicated, reserved subdirectory literally named `objects/`** — distinct from resource definition files. The directory name is always `objects`, never named after a resource or module.

To contrast the two kinds of files clearly:

```
resources/api/storefront/
├── checkout.resource.yml          # a resource definition
├── checkout.validation.yml        # its validation
└── objects/                       # reserved dir — canonical objects only
    ├── address.object.yml
    └── address.object.validation.yml
```

Only `*.object.yml` and `*.object.validation.yml` files belong in `objects/`. Resource files (`*.resource.yml`) are placed **directly** in the per-`apiType` directory, never inside `objects/`.

**Naming** The `<dashed-name>.<kind>.yml` pattern is shared by both file types — only the *kind* word differs. `address.object.yml` is the canonical-object analog of `checkout.resource.yml`; `address.object.validation.yml` is the analog of `checkout.validation.yml`. The `object` vs `resource` distinction marks the artifact kind, not a different naming scheme.

Full path patterns:

```
resources/api/<apiType>/objects/<dashed-name>.object.yml
resources/api/<apiType>/objects/<dashed-name>.object.validation.yml   # optional validation
```

Example paths:
- `src/Pyz/resources/api/storefront/objects/address.object.yml`
- `src/Pyz/resources/api/storefront/objects/address.object.validation.yml`

#### Central directory

A project may also keep canonical object files in one central, configured location instead of (or in addition to) the per-module `objects/` directories. Both locations are scanned and supported simultaneously.

Enable it via the **Symfony bundle config node** `spryker_api_platform.canonical_object_search_directories`, keyed by API type. Relative paths are resolved against the project root; `%kernel.project_dir%` is also supported:

```yaml
# config/packages/spryker_api_platform.yaml
spryker_api_platform:
    canonical_object_search_directories:
        storefront:
            - '%kernel.project_dir%/config/api/objects/storefront'
```

```
config/api/objects/<apiType>/<dashed-name>.object.yml
config/api/objects/<apiType>/<dashed-name>.object.validation.yml   # optional validation
```

The same `*.object.yml` / `*.object.validation.yml` filename rules apply. The core default is an empty list, so without this configuration behavior is identical to scanning module locations only. Files in a central directory are always treated as the **project** layer (their path carries no `/Pyz/` segment for path-based detection), so they participate in the standard project > feature > core precedence.

Defining the same `objectName` more than once **within the same layer** (for example one module file and one central-directory file, both project) is a fail-loud error: generation aborts with an `ApiSchemaGenerationException` naming both source files. The same name across *different* layers is fine — that is the normal override.

### File format

```yaml
# address.object.yml
object:
    name: Address                            # CamelCase; matches objectName: Address in resource YAMLs
    properties:
        salutation: { type: string, description: 'Address salutation.' }
        firstName:  { type: string, description: 'First name.' }
        zipCode:    { type: string, description: 'ZIP / postal code.' }
```

Key | Type | Required | Notes
----|------|----------|------
`object.name` | string | yes | CamelCase; must match the `objectName:` join tag in resource YAMLs
`object.properties` | map | yes | Field definitions — same syntax as resource properties
`object.extends` | string | no | CamelCase name of another canonical object; its resolved fields are inherited first
`object.omit` | string[] | no | Field names to drop from the `extends` base before applying own properties

### Composition (`extends` / `omit`)

```yaml
# address-snapshot.object.yml
object:
    name: AddressSnapshot
    extends: Address                         # inherits all Address fields
    omit: [id, idCompanyBusinessUnitAddress] # drops write-only fields
    properties:
        country: { type: string, description: 'Country name.' }  # adds read-only field
```

Resolution order: base `extends` fields → `omit` removals → own `properties` (own wins). Cycles throw `ApiSchemaGenerationException`.

### `objectName` join tag

Every resource property that declares `objectName: Address` is the *join key* for this feature:

```yaml
# checkout.resource.yml (core — ships dormant tags)
properties:
  billingAddress:
    type: object
    objectName: Address    # dormant when no address.object.yml exists; activates when the file is present
    readable: false
    writable: true
    properties:
      zipCode: { type: string }
```

When a canonical file for `Address` exists:
- The property's inner `properties` are replaced with the canonical shape.
- The mount attributes (`readable`, `writable`, `required`, `nullable`) stay on the reference site — they are **not** owned by the canonical.
- One shared `Generated\Api\<ApiType>\Address` class is emitted; no per-resource companion class is generated for that property.

When no canonical file exists, the inline `properties` block is used exactly as today (no change).

### Validation

Field-level validation is authored in the parallel `*.object.validation.yml` using the same format as resource validation files. On a canonicalized property the reference site's own `Collection` constraint is superseded by an `Assert\Valid` cascade to the canonical class, which carries the field-level constraints.

### Layer precedence

Layer detection uses the same path rules as resource files: `/Pyz/` → project, `/SprykerFeature/` → feature, else core. Merge precedence: project > feature > core — so a project can add one field to a feature-layer canonical without redefining the whole object.

Core ships no `*.object.yml` files. The feature is available for project, feature, and core layers; today only projects use it.

### Generated output

One shared class per canonical object is emitted to `Generated\Api\<ApiType>\<ObjectName>` (e.g. `Generated\Api\Storefront\Address`). All resource classes that reference the canonical use this single shared class.

---

## Documentation

The authoritative documentation lives in spryker-docs. Start here:

- **[API Platform overview](https://docs.spryker.com/docs/dg/dev/architecture/api-platform.html)** — concepts, architecture, and the resource generation workflow.
- **[Resource schemas](https://docs.spryker.com/docs/dg/dev/architecture/api-platform/resource-schemas.html)** — `*.resource.yml` reference.
- **[Validation schemas](https://docs.spryker.com/docs/dg/dev/architecture/api-platform/validation-schemas.html)** — `*.validation.yml` reference.
- **[Relationships](https://docs.spryker.com/docs/dg/dev/architecture/api-platform/relationships.html)** — declaring includes between resources.
- **[CodeBucket support](https://docs.spryker.com/docs/dg/dev/architecture/api-platform/code-buckets.html)** — region-specific resource variants.
- **[Testing](https://docs.spryker.com/docs/dg/dev/architecture/api-platform/testing.html)** — writing tests for API Platform resources.
- **[IDE integration](https://docs.spryker.com/docs/dg/dev/architecture/api-platform/ide-integration.html)** — PHPStorm and VSCode setup for YAML autocomplete.
- **[Integration guide](https://docs.spryker.com/docs/dg/dev/upgrade-and-migrate/integrate-api-platform.html)** — installing and configuring API Platform in a project.
- **[Migration from Glue REST](https://docs.spryker.com/docs/dg/dev/upgrade-and-migrate/migrate-to-api-platform.html)** — moving legacy endpoints to API Platform.
- **[Troubleshooting](https://docs.spryker.com/docs/dg/dev/architecture/api-platform/troubleshooting.html)** — common issues and solutions.
