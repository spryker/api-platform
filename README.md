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

## JSON:API request bodies

API Platform does not produce a usable request-body schema for a JSON:API write operation.
`ApiPlatform\Hydra\JsonSchema\SchemaFactory::buildSchema()` rewrites the format to `json` for **every**
input schema, so the JSON:API factory below it is never asked for one; the generic factory then sees
`json` + `PATCH` and describes the body as a JSON merge patch — flat, no `data` envelope, named
`*.jsonMergePatch`. A POST body gets the flat resource schema. Sending either as documented answers
`400 Post data is invalid.`, because both media types of the `jsonapi` format
(`application/vnd.api+json` **and** `application/json`) are read by the JSON:API denormalizer.

`JsonApiInputSchemaFactory` decorates `api_platform.json_schema.backward_compatible_schema_factory` —
above Hydra, where the real format is still known — and wraps the flat schema into the JSON:API
document:

- `<resource>.jsonapi-post` — `data.type` + `attributes`; no identifier, the server assigns it.
- `<resource>.jsonapi-patch` / `-put` — `data.type` + `data.id`, both required, as JSON:API demands of
  an update.
- the flat schema's `required` list becomes `data.attributes.required`, so Swagger marks the same
  fields the validator enforces.
- properties the flat schema marks `readOnly` are left out: OpenAPI defines those as response-only, so
  listing them in a request body describes fields the endpoint will not accept. A property that is both
  read-only and required is dropped from `required` too.
- a resource with nothing writable — an action endpoint addressed by its URI, such as
  `POST /quote-requests/{reference}/quote-request-cancel` — documents the bare envelope, with no
  `attributes` member at all.

Because the schema is built per operation, a write-only resource (no `.jsonapi` read schema) is covered
by the same path, and no definition is left orphaned.

## Per-operation write scope (serialization groups)

`#[ApiProperty(writable: ...)]` is one flag for the whole resource, so a property that only one write
operation accepts would otherwise be documented on the others. Serialization groups scope it per
operation, and the serializer enforces what the document promises:

```yaml
operations:
    - type: Post
      denormalizationContext:
          groups: ['customers:write', 'customers:write:create']
          disable_json_schema_serializer_groups: false
    - type: Patch
      denormalizationContext:
          groups: ['customers:write']
          disable_json_schema_serializer_groups: false

properties:
    email:
        type: string
        groups: ['customers:write']
    sendRegistrationToken:
        type: boolean
        groups: ['customers:write:create']   # accepted on create only
```

`disable_json_schema_serializer_groups: false` is what makes the groups shape the generated schema as
well as the runtime; left at its default the documented body lists every property.

A property outside an operation's groups is **dropped silently** by the serializer — there is no error.
That makes the group list load-bearing: every writable property needs one, and a property missing its
group stops being writable without any signal. Cover the writable surface with a functional test.

## Schema and API class discovery

Schema files and Glue API classes are discovered by `ApiDirectoryLocator` at **conventional, fixed-depth locations** inside the configured `source_directories` — the filesystem is not scanned recursively.

Discovered layouts for resource schemas (`resources/api/{apiType}`):

```
{sourceDirectory}/resources/api/{apiType}                     # source directory is a module root
{sourceDirectory}/{Module}/resources/api/{apiType}            # conventional module layout
{sourceDirectory}/{Org}/{Module}/resources/api/{apiType}      # organization nesting (e.g. vendor)
{sourceDirectory}/Glue/{Module}/resources/api/{apiType}       # project-level layout (src/Pyz)
```

Discovered layouts for API classes (`Glue/{Module}/Api/{ApiType}`):

```
{sourceDirectory}/Glue/{Module}/Api/{ApiType}                 # project-level layout (src/Pyz)
{sourceDirectory}/src/{Org}/Glue/{Module}/Api/{ApiType}       # module source root
{sourceDirectory}/{Module}/src/{Org}/Glue/{Module}/Api/{ApiType}  # module checkout / vendor package
```

The following invariants are enforced by the locator and pinned by unit tests (`ApiDirectoryLocatorTest`, `SchemaFileDiscoveryTest`, `SprykerApiPlatformBundleTest`) — keep them in mind when changing discovery:

- **Case-sensitive matching on every filesystem.** Literal path segments (`resources`, `api`, the API type, `Glue`, `Api`) must match with the exact requested casing. A miscased directory that would be found on a case-insensitive development machine but not on case-sensitive CI/production hosts is rejected everywhere.
- **Symlinked path segments are not followed.** Directories reached through a symlink below a source directory are not discovered (matching Symfony Finder's default used previously).
- **Fixed depth.** Directories nested deeper than the layouts above are not discovered. Point `source_directories` one level deeper instead of relying on recursive lookup.
- **Memoized per instance.** All compiler passes share one discovery instance per container build; repeated lookups with identical input return the first result. Code that creates schema directories mid-process must not expect a re-lookup on the same instance to see them.

## Resource class index (compile-time metadata)

The container compiler pass `ResourceClassIndexPass` scans the generated resources of each
configured API type and compiles an index of every resource's short name, class, and
`includedSortPriority` extra property into the container parameter
`spryker_api_platform.resource_class_index`, grouped by base resource class with the code bucket
as inner key (`''` for the base resource). Runtime consumers (`ResourceClassIndexProvider` for
short-name-to-class lookups, `CodeBucketResourceNameCollectionFactory` for code bucket filtering)
read that parameter — an opcache-served part of the compiled container with zero per-request
reflection.

Contracts:

- **The index shares the compiled container's lifecycle.** It is produced at deployment warmup,
  baked into the immutable application image, and can never be stale relative to the container that
  serves it: adding or removing resources requires `api:generate` plus the container rebuild that
  recompiles the parameter — the same rebuild routes and API Platform metadata already require.
- **`api:generate` must be followed by `cache:clear` for each Glue application.** The command's own
  boot compiles the container before the classes it generates exist, and with runtime debugging
  disabled neither the container nor the frozen router dump is ever recompiled on its own — a plain
  `cache:warmup` keeps the pre-generation container. The install recipes (`config/install/*.yml`)
  already run the three `cache:clear` steps right after the `api:generate` steps; a manual
  `api:generate` run needs a manual `vendor/bin/glue cache:clear` (per `GLUE_APPLICATION`) after it.
- **Code bucket resolution happens at read time.** Each group carries the base entry and its
  code-bucket variants; consumers pick the current code bucket's variant with the base entry as
  fallback in a plain array lookup, so one compiled container serves all code buckets.
- **Classes outside the generated index pass through the code bucket filter unfiltered** (for
  example project-level resources) — code bucket variants are a generated-resource concept.

## Serializer decorators

`CXmlEncoder` and `CXmlNormalizer` decorate serializer-aware services (`serializer.encoder.xml`, `api_platform.serializer.normalizer.item`) and therefore take their place in the serializer chain. Two contracts apply to these (and any future) serializer decorators; both were the source of production bugs and are pinned by `CXmlSerializerAwarenessTest` and `CXmlNormalizerDelegationTest`:

- The decorator must implement `SerializerAwareInterface` and **forward `setSerializer()` to the decorated service** — the serializer only injects itself into the objects registered in the chain, so a decorator that keeps it to itself leaves the decorated service without a serializer (every `application/xml` request then fails with HTTP 500).
- The decorator must **support everything the decorated service supports** (`supportsNormalization()`, `supportsDenormalization()`, `getSupportedTypes()` delegate to the decorated service). Narrowing support to one format removes the decorated normalizer from the chain for all other formats — `text/csv` responses then bypass API Platform property metadata and expose `readable: false` properties.

## OpenAPI error responses

`ErrorResponseOpenApiDecorator` — the outermost decorator of `api_platform.openapi.factory` — documents every 4xx/5xx response of both applications, and a `default` response for the rest, with the error document the runtime really sends, so nothing has to be declared per resource:

- **Schema.** Every error response carries `#/components/schemas/GlueApiError.jsonapi` (`errors[].{code?, status, detail?, message?}`, with `code` nullable because a legacy Glue error without a code serializes it as `null`; the schema is written in the OpenAPI 3.1 form and a document requested as 3.0.0 gets the `nullable` keyword from API Platform's own normalizer, which downgrades every component schema) under each `api_platform.error_formats` media type. API Platform's own `Error` / `ConstraintViolation` schemas describe a shape Spryker never sends; they are removed once nothing references them.
- **Examples.** Status-specific, application-aware and verified against live responses of both applications. The Backend API shows the 401 of a missing bearer (`Authorization header is required`), the 403/`802` denial of a failed security expression and the ACL validator's `Access denied by ACL rules`; the Storefront API shows 403/`002` for a missing bearer and the resource's `securityCode` / `securityMessage` denial. Every operation's 400 shows the Glue convention's rejection (code `011`) of a `filter` parameter outside the `filter[resource.property]` form, which the filter parser answers on any method; a write operation's 400 adds the request subscribers' `Post data is invalid.`, `Invalid type.` and `Post data missing or invalid.` bodies, and its 422 example names the first required attribute of the request schema. The `default` response shows the framework's 405 `Method Not Allowed`, the 406 that lists the configured formats and, on a write operation, the 415 that lists the operation's input formats. On the Storefront API the 404 example comes from the `notFoundCode` / `notFoundMessage` extra properties the operation declares, or else from the provider's `ERROR_MESSAGE_<X>_NOT_FOUND` / `ERROR_CODE_<X>_NOT_FOUND` constants — the same pair `GlueApiExceptionSubscriber` renders at runtime, through the shared `ProviderNotFoundErrorResolver`; without either it is the generic `Not Found` body a provider returning `null` produces. The Backend API documents every 404 with one body, the legacy Glue application's resource-not-found code `007` and message `Not found`, and does not introduce resource-specific codes.
- **No example rather than a wrong one.** A 401 or 403 declared on a public operation (a token endpoint answering a credential failure) is module-specific, so it carries the schema and the declared description only. A module-specific 400, 403 or 404 (an unsupported sort field, a missing `X-Anonymous-Customer-Unique-Id` header, a cart the customer does not own) is described in the declared response text; its example stays the framework body every operation shares.
- **Descriptions.** A declared description is kept. A missing one, or one of the texts API Platform's factory emits (`Invalid input`, `Forbidden`, `Not found`, `Unprocessable entity`, `An error occurred`, `Unexpected error`), is replaced with the Glue text for that status and application.
- **Coverage.** Every operation receives 400, every operation with a `security` expression (or `securityBearerAuthRequired`) 401 and 403, every item operation 404, unless the resource YAML declares them, and every operation a `default` response for any other status the framework answers: 405 for a method the path does not support, 406 for an Accept header outside the configured formats, 415 for a request body in a Content-Type outside the operation's input formats, 500 for an unexpected failure — the last one as API Platform's error resource, whose `id`, `title` and `type` members come in addition. Public operations get an empty `security` requirement, so Swagger UI no longer shows a padlock on them.
- **Design.** The decorator works on the finished document rather than on the operation metadata, for three reasons: the documentation payload (six responses with examples per operation) stays out of the metadata collection every runtime request loads; it runs after the format transformers, so nothing rewrites its content; and a component schema can only be registered at this layer. Each documented operation is joined to its metadata by `operationId`, the key the factory itself writes (`openapi.operationId` when declared, otherwise the normalised operation name), so no path is rebuilt and a custom `routeName` or `routePrefix` changes nothing (`OperationMetadataResolver`). API Platform's own hooks were evaluated and do not fit: `errors` on an operation takes RFC 7807 problem classes and emits schema-only content, no examples; `openapi.error_resource_class` swaps the schema of the four responses the factory emits itself only, requires a problem class whose reflected members Spryker never sends, and is not wrapped into `errors[]` by the JSON:API schema factory, which special-cases the vendor `Error` class. The factory still emits its `Error` / `ConstraintViolation` schemas for its own 403, 400 and 422, which is why they are removed afterwards (`ApiPlatformErrorSchemaRemover`); `PathItemOperationAccessor` is the OpenAPI model's per-method accessor shape, the same dynamic call the factory makes. The composition is covered at integration tier: `OpenApiDocumentAssertionsTrait` in the module's test support asserts that every `$ref` of a produced document resolves, that the Glue error schema is the only error schema and every error response references it, that every operation carries the added responses and that `code` is rendered in the requested wire format; the project suite `tests/PyzTest/Glue/ApiPlatform` runs it over the real Backend application's document, in both spec versions and through the `api:openapi:export --spec-version=3.0.0` command the documentation generator uses.
- **Authoring.** Declare a status in `openapiContext.responses` only when its description is resource-specific. Error `content` written by hand (anything not referencing API Platform's own error schemas) is kept as is. An operation whose processor demands a bearer without a `security` expression declares `securityBearerAuthRequired: true` in its `extraProperties`, so the document shows its 401 and 403. On the Storefront API, a provider that returns `null` for a missing item declares its pair as `ERROR_MESSAGE_<X>_NOT_FOUND` / `ERROR_CODE_<X>_NOT_FOUND` constants, so the document and the runtime share it, one pair per provider since a `null` return cannot say which entity is missing and a second pair is refused, and a module that raises its own not-found exception declares what each operation answers — per operation, because the collection of a sub-resource misses its parent while its item misses itself. Backend API resources declare no not-found pair; their 404 is documented with the single body above.

  ```yaml
  - type: GetCollection
    uriTemplate: '/abstract-products/{abstractProductSku}/abstract-product-prices'
    extraProperties:
        notFoundCode: '307'
        notFoundMessage: 'Can`t find abstract product prices.'
  ```

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
