# API contract coverage

Machine- and human-readable proof of *which* API operations and validation rules the
integration tests cover — and a CI gate that fails when an enforced resource has a gap.

Reference implementation: the Wishlists suites in
`tests/PyzTest/Glue/Wishlists/StorefrontApi/Integration/`.

## Why

A reviewer reading a test method should see, at a glance, the URL + verb it asserts on, the
validation rule it exercises and whether it round-trips the response body. A tool should be able to
diff that against the generated API Platform schema and tell us what is still uncovered. All three
come from three attributes declared on the test methods: `CoversApiOperation`,
`CoversApiValidation` and `CoversApiRequiredResponseAttributes`.

## Annotating a test

Declare the one operation the test asserts on (not the arrange/helper requests), and — only on a
test that asserts a `4xx` validation outcome — the rule it exercises. Import the attributes and
write them as short names.

```php
#[CoversApiOperation('POST', '/wishlists')]
#[CoversApiValidation('wishlists', 'name', 'NotBlank')]
public function testGivenABlankNameWhenPostWishlistThenItRespondsUnprocessableEntity(): void
```

- `CoversApiOperation(verb, uriTemplate)` — the OpenAPI `uriTemplate`, e.g.
  `/wishlists/{wishlistUuid}/wishlist-items`. Without a `status` it covers the operation's success
  response; `status: Response::HTTP_NOT_FOUND` declares the error response the test asserts on.
- `CoversApiValidation(resource, attribute, rule)` — the resource short name, the guarded attribute,
  and the rule identifier. The rule is the Symfony constraint's own short name (`'NotBlank'`,
  `'Email'`, `'Type'`), so a constraint nobody has annotated before needs no registration anywhere.
  The few that do not follow that default are `Rule` cases and are named through the enum
  (`Rule::LENGTH_MIN`, `Rule::LENGTH_MAX`). It binds to the `CoversApiOperation` on the same method —
  that names the operation the rule was exercised against, so it never appears on a method without
  one.
- `CoversApiRequiredResponseAttributes` — takes no arguments; see [Response attributes](#response-attributes).

`CoversApiOperation` and `CoversApiValidation` are repeatable, but a method should carry the single
operation it asserts on.

## Validation coverage is per operation

Whether a constraint fires depends on the operation's **validation groups**: the truth set holds
one entry per rule per input operation (POST, PUT, PATCH) whose groups intersect the constraint's
groups (both default to `Default`, mirroring Symfony). The wishlist name's `NotBlank` carries the
create and the update group, so `POST /wishlists` and `PATCH /wishlists/{uuid}` each need their own
annotated test; the wishlist item's `sku` `NotBlank` carries only the create group, so it needs a
POST test and none for PATCH.

## Declared error responses

Error responses are read from the schema, never synthesised: each status a `.resource.yml` declares
under `openapiContext.responses` becomes one coverage item, and an enforced operation declaring
nothing is reported as a `SCHEMA DEFECT` naming the file to fix. Validation coverage stays separate —
a declared `422` demands one test for the status, while every constraint the operation's groups make
active demands its own.

The runtime verifier checks a status-carrying declaration by its operation being dispatched (the
recorder observes the kernel request, where no status exists yet); asserting the actual response
status stays the test body's job.

## Response attributes

Every `readable` property of a resource is part of its success response contract, so every success
operation has exactly one test that asserts each of them **against the fixture that test created**.
The identifier and relationship-link properties (`uriTemplate` links and their `…RelationshipData`
siblings) are structurally excluded rather than counted, and both are excluded on the same
envelope-check grounds. The identifier is `data.id` — it lives in the JSON:API envelope, outside the
`attributes` object these paths address, so it was never a response-attribute path to begin with.
`Contract\Envelope\JsonApiEnvelopeVerifier`, which `AbstractApiTestCase` runs over every response a
test produced, demands the `type` and the `self` link of every resource object the document carries,
and a non-empty `id` of each one whose resource declares an identifier.

An operation declaring `output: false` answers no body, so it demands no response attributes, and
the envelope check accepts its empty `202` the same way it accepts an empty `204`.

That last guarantee stops where the schema does. A resource marking no property `identifier: true`
— an action endpoint, a singleton — may answer without an `id`, and its responses are outside the
identifier check. The exemption is derived by reflecting the generated resources
(`ContractCoverageRunner::resourceShortNamesDeclaringIdentifier()`), never listed by hand, so a
schema that gains or loses an identifier moves the guarantee with it. Nested `items.required` fields and one
level of nested object properties count; a deeper object is covered as a presence path instead —
today's `.resource.yml` files
describe nested shapes too unevenly for a deeper sweep to enforce a stable set. The only actual
opt-out is a property that is legitimately absent on a given resource, declared in the
`.resource.yml`:

```yaml
updatedAt:
    readable: true
    responseOptional: true
    description: 'Set once the wishlist was changed after creation.'
```

Mark that one test — the declaration takes no arguments, because the attribute set is schema truth
and is read from the generated resource at runtime:

```php
#[CoversApiOperation('GET', '/agent-customer-search')]
#[CoversApiRequiredResponseAttributes]
public function testGivenACustomerWithAllDeclaredRequiredAttributesWhenTheCollectionIsRequestedByEmailThenAllRequiredResponseAttributesAreReturned(): void
```

Name it with the fixed Then-clause `…ThenAllRequiredResponseAttributesAreReturned` so the contract
test of an operation is greppable; the gate keys on the attribute, not on the name. Assert through
the helper, which records what it asserted:

```php
$this->assertResponseAttributes($response, [
    'customers[0].firstName' => $customerTransfer->getFirstName(),
    'pagination.numFound' => static::SINGLE_MATCH_COUNT,
]);
```

`assertPostConditions()` fails the test when a required attribute was never asserted, and
`api:contract:coverage` reports the dimension as a counter line —
`Response attributes  <n>/<m> covered · <k> uncovered` — plus an `Uncovered response attributes` gap
section. Each entry there is `  - <dispatch key>  <path>`, followed by `      covered by: …`
continuation lines naming the tests that already declare the operation, or the placeholder
`(no test declares this operation yet)` when none do. Seed values (`Sonia`, `DE--1`) never appear in
these tests; a value the server mints (uuid, timestamps) goes through
`assertResponseAttributesPresent()`.

Only one test per operation carries the marker: verification is per test method, so the marked test
must round-trip everything itself. There is deliberately no second "all attributes" declaration —
when the resource ymls declare their nested objects fully, the truth collector's recursion depth is
raised and this same marker enforces the fuller set.

There is one canonical Then-clause across four Given/When shapes, so a reviewer can grep for the
family regardless of which operation it names:

```
testGivenAnEntityWithAllDeclaredRequiredAttributesWhenTheEntityIsRequestedThenAllRequiredResponseAttributesAreReturned
testGivenEntitiesWithAllDeclaredRequiredAttributesWhenTheCollectionIsRequestedThenAllRequiredResponseAttributesAreReturned
testGivenAValidPayloadWhenTheEntityIsCreatedThenAllRequiredResponseAttributesAreReturned
testGivenAnExistingEntityWhenTheEntityIsUpdatedThenAllRequiredResponseAttributesAreReturned
```

The Given- and When-clauses may deviate where the operation warrants it — a search endpoint, a
filtered collection, an upsert — but the Then-clause never does. This is a convention enforced by
review, not by tooling: the gate keys on the `#[CoversApiRequiredResponseAttributes]` attribute,
never on a method name, so renaming a test can never silently drop its coverage.

## Enforcing a dimension

Operations, validation rules and response attributes are always enforced. The dimensions below are
computed and reported on every run, and fail the gate and the test runtime only once the
application lists them:

```php
// config/GlueStorefront/packages/spryker_api_platform.php
$sprykerApiPlatform->contractCoverageEnforcedDimensions(['error-codes', 'request-attributes']);
$sprykerApiPlatform->contractCoverageEnforcedDimensions(['all']); // every dimension
```

| Dimension | Truth | Claim |
|-----------|-------|-------|
| `error-codes` | each code a status declares | `#[CoversApiOperation(..., status:, code:)]` |
| `error-mappings` | each entry of a registered Zed error mapping | a declared code, or `notAnswered` |
| `validation-evidence` | runtime only: each `#[CoversApiValidation]` | a 422 reporting that rule for that attribute |
| `request-attributes` | each writable attribute of an input operation | `#[CoversApiRequestAttributes]` |
| `includes` | each declared include of a read | `#[CoversApiIncludes]` |
| `ownership-scenarios` | each operation guarded by an ownership voter | `#[CoversApiOperation(..., status:, scenario: Scenario::FOREIGN_OWNER)]` |
| `non-empty-arrays` | runtime only: every asserted response attribute | a non-empty value |
| `openapi-example-replay` | each resource with a servable operation | `#[ReplaysOpenApiExamples]` on a replay class |

A dimension that is not enforced lists its gaps under `Reported, not enforced
(contract_coverage_enforced_dimensions)`, so they stay visible on every CI run. Preview one before
enforcing it: `vendor/bin/glue api:contract:coverage --enforce=<dimension>` (repeatable, or `all`)
for the gate, and `API_CONTRACT_COVERAGE_ENFORCE=<dimension>,<dimension>` for a test run. Both widen
the configured set and never narrow it.

A claim of a new kind (`code:`, `scenario:`, `#[CoversApiRequestAttributes]`, `#[CoversApiIncludes]`,
a replay class) is verified at runtime whatever is enforced: a declaration is verified, never merely
claimed. Only the checks that newly bite existing declarations - validation evidence, non-empty
arrays, codes a status does not declare - wait for their dimension.

## Baseline of known product bugs

Some items stay uncovered because the product has a bug that is known and will be fixed later: an
include that is never loaded, an array that is always empty. The application lists them, per
dimension, with the bug in plain language, so the dimension can be enforced while the bug is open:

```php
// config/GlueStorefront/packages/spryker_api_platform.php
$sprykerApiPlatform->contractCoverageBaseline([
    'includes' => [
        'GET /orders  include merchants' => 'The merchants include names a relationship resolver that does not exist, so it is never loaded.',
    ],
    'non-empty-arrays' => [
        'GET /orders/{orderReference}  items[].calculatedDiscounts' => 'Order items never carry their calculated discounts.',
    ],
]);
```

- The key is the item as the report prints it under the dimension's gap section; the dimension is
  one of the table above. Operations, validation rules and response attributes take no baseline.
- A baselined item does not fail its dimension. The report lists it under `Baselined, known product
  bugs (contract_coverage_baseline)` with its reason, apart from the gaps.
- An entry fails the gate, enforced or not, once a claim covers its item (`… baseline entries now
  covered`) or, on a run without `--module`, once it names no uncovered item (`… naming no uncovered
  item`). The fix of the bug therefore removes the entry, and the baseline only shrinks.
- The reason describes the bug, not a ticket. A `%` in a reason is written `%%`, because the list is
  a container parameter.
- The runtime-only dimensions are judged by the test runtime. A `non-empty-arrays` entry,
  `<dispatch key>  <path>`, excuses a path a marked test asserted only as `[]` or `null`, together
  with the element paths of an array asserted as `[]`, and fails the test that asserts a value for
  the path or one of its elements. A `validation-evidence` entry, the `#[CoversApiValidation]`
  key `<resource>.<attribute>.<rule> on <VERB> <uriTemplate>`, excuses a declaration no 422 proves,
  and fails the test whose response proves it.

## Declared error codes

A status lists the codes it answers under `codes`, in the `.resource.yml`; a string entry is the
shorthand for a code without a description. The resource's `commonErrorCodes` join every status of it
that declares codes, so the framework codes are not repeated per operation. A common code joins a
status, it never creates one: `api:generate` rejects a common code whose status no operation of the
merged resource declares codes on, which also catches a project operation that replaced a core one
without its codes:

```yaml
commonErrorCodes:
    - status: 422
      code: '901'
      description: 'Request validation failed.'
operations:
    - type: Delete
      openapiContext:
          responses:
              422:
                  description: 'Cart code could not be removed.'
                  codes:
                      - code: '3301'
                        description: 'Cart code not found in the cart.'
                      - '3303'
```

Each code becomes a JSON:API error example in the published document and one coverage item,
`DELETE /carts/{cartUuid}/cart-codes/{code} 422 code 3301`. A test claims one with
`#[CoversApiOperation('DELETE', '/carts/{cartUuid}/cart-codes/{code}', status: 422, code: '3301')]`,
which covers its status item too, and is proven only by a response that carried the code. Once
`error-codes` is enforced, a response carrying a code its status does not declare fails the test.

## Error mappings

A resource registers the Zed error mappings it answers; the mapping resolves to the project's
override of the config first:

```yaml
errorMappings:
    - source: 'Spryker\Glue\CartsRestApi\CartsRestApiConfig::getErrorIdentifierToRestErrorMapping'
      notAnswered:
          '1507': 'Raised only by the legacy shopping-list merge endpoint.'
```

Each mapped code and status has to be declared on an operation of a registering resource, or be
`notAnswered` with a reason. A `notAnswered` code the mapping does not contain is stale. Mapping
methods of a module's Glue config that no resource registers are listed as `Unregistered error
mappings (review)` - a name-based guess, never a failure.

## Validation evidence

Once `validation-evidence` is enforced, each `#[CoversApiValidation]` needs a 422 on its operation
that reports the declared rule for the declared attribute's full path. Only a structured violation
counts, from one of three sources:

- the validator's violation list, read before it becomes the error envelope;
- a denormalization type error in the exception chain, which counts as `Type` for the path the
  serializer reports, or, where only the path inside a nested object is known, for the one
  submitted path that ends in it;
- an error the exception subscriber's augmenters synthesise, each tagged with the declared
  constraints it stands for and its full path (`SynthesizedViolation`).

The error detail is never read for it: it names no rule, and a nested leaf error names the leaf
without its parent. A type error does not prove a `GreaterThan` on the same attribute, and a
`quantity` error does not prove `prices.volumePrices.quantity`. `assertValidationFailedForAttribute()`
still demands a detail that starts with `<attribute> => `.

An exact `Length` (`min` equal to `max`) raises one code for both sides, so the violation counts as
`Length.min` for a value too short and `Length.max` for one too long; each needs its own test. A
violation on a map entry (`unitPriceMap[any-group-key]`) or a list element counts for the attribute
that declares the `All`, as the truth keys it; a `Collection` field keeps its segment.

## Ownership scenarios

An operation whose security - its own, else the resource's - grants through a voter attribute listed
in `contractCoverageOwnershipSecurityAttributes([...])` (Storefront: `CUSTOMER_OWNER`) owes a
foreign-owner test:

```php
#[CoversApiOperation('GET', '/customers/{customerReference}/carts', status: Response::HTTP_FORBIDDEN, scenario: Scenario::FOREIGN_OWNER)]
```

It is proven only by an authenticated request an access decision denied, not by a missing token or an
unknown id. The scenario item carries no status, so the test declares whichever one the schema says.

## Request attributes

Every writable attribute of an input operation - the writable children of a nested value object, the
fields of an `Assert\Collection` including optional ones, the fields of a list of collections as
`shipments[].items` - has to be sent by a successful request of some test. One level deep, like the
response side. `#[CoversApiRequestAttributes]` claims all of them for the operation on the same
method, `#[CoversApiRequestAttributes('currency', 'priceMode')]` exactly those; tests can split the
set. A property one operation ignores declares `writableOn: ['Post']`.

## Includes

Every declared include is owed by the servable reads of its resource, or by the operation types its
`includedOn` names. `#[CoversApiIncludes('vouchers')]` is proven by a successful request with
`?include=vouchers` together with `assertIncludedRelationship($response, 'vouchers', [$voucherCode])`,
which checks the referenced ids and their presence in `included`. A claim on a write is accepted but
never owed.

## Non-empty arrays

An array asserted as `[]` has no element, so its element paths (`discounts[].code`) are not owed by
that test; a test whose fixture has an element proves them. Once `non-empty-arrays` is enforced, an
attribute asserted only as `[]` or `null` no longer counts as asserted, and the element paths of an
array asserted only as `[]` are owed by that test too. The report counts the
readable arrays that declare no `items.required`, whose element shape nothing demands.

## Example replay

A replay class sends the generated example of every servable operation of the resources it names and
fails on a 5xx; a 4xx for a placeholder value is a correct answer:

```php
#[ReplaysOpenApiExamples('wishlists', 'wishlist-items')]
class WishlistsOpenApiExampleReplayStorefrontApiIntegrationTest extends AbstractOpenApiExampleReplayTestCase
{
    protected function createReplayContext(ApiOperation $operation): OpenApiExampleReplayContext
    {
        $this->tester->actingAsCustomer($this->tester->haveCustomer());

        return new OpenApiExampleReplayContext(['wishlistUuid' => $this->tester->haveWishlist()->getUuidOrFail()]);
    }
}
```

The body comes from the writable properties' `openapiContext.example`, the query from the parameter
examples. The context fills the path variables, the headers and any value that must reference a
fixture, or skips an operation with `OpenApiExampleReplayContext::skip($reason)`.

## Operations without a success response

An operation whose schema declares statuses and no 2xx among them - a bare collection URL kept only
to answer 400 or 501 - never returns a resource body. It owes no response attributes, no request
attributes, no includes and no non-empty arrays, because each of those is proven by a successful
response. Its declared error statuses and codes and its validation rules stay owed: a 4xx proves
them. An operation that declares nothing at all is a schema defect instead and keeps owing everything.

## Two guarantees

1. **Declarations are verified, not claimed.** `AbstractApiTestCase` records every operation a
   booted request actually matches and fails the test if a declared operation was never dispatched,
   and records every response attribute the assertion helpers read and fails a
   `#[CoversApiRequiredResponseAttributes]` test that left a required one unasserted. You cannot
   annotate an operation the test does not exercise, nor claim a response contract it does not
   assert.
2. **In-scope resources have no gaps.** The report tool diffs the generated `#[ApiResource]`
   classes against the collected annotations and fails the build on any uncovered operation,
   validation rule or required response attribute, or any stale claim (a declaration pointing at
   something the schema no longer defines).

## Running the report

```bash
GLUE_APPLICATION=GLUE_STOREFRONT vendor/bin/glue api:contract:coverage
```

It prints COVERED / UNCOVERED / STALE for operations and validation rules, lists NON-SERVABLE
operations (item-GETs with no provider — they only mint IRIs, so they cannot be asserted on and are
not gaps), and exits non-zero when the gate fails.

CI runs it in the `API Platform Tests` job, once per Glue application, after the generation step.
It writes the markdown report to the run's summary page. The tooling's own tests
(`src/Spryker/ApiPlatform/tests/SprykerTest/ApiPlatform/Unit/Coverage`) run in the same job's
module-suite step. They carry no coverage attributes because they test the gate, not a resource, and
a failure fails the job like any unit test.

Narrow it to the resources you are working on with `--module` / `-m`. Casing, separators and a
trailing plural are all ignored, so the module name and the resource short name both work:

```bash
vendor/bin/glue api:contract:coverage -m Wishlist                      # the wishlists resource
vendor/bin/glue api:contract:coverage -m WishlistItems                 # the wishlist-items resource
vendor/bin/glue api:contract:coverage -m wishlists -m wishlist-items   # both
```

Matching is exact once normalised, so `-m Wishlist` selects `wishlists` only — it does not pull in
`wishlist-items`. A filter that names no known resource fails the command and lists what is
selectable, so a typo cannot quietly report on nothing. Narrowing changes only what is *enforced*.

The command is **development-only**. It reads the test suites' coverage annotations, so it is
registered from each application's `config/Glue*/packages/spryker_api_platform.php` and only
when development console commands are enabled (`DEVELOPMENT_CONSOLE_COMMANDS=1`). It needs the
resources generated first — `GLUE_APPLICATION=GLUE_STOREFRONT vendor/bin/glue api:generate`.

It measures the API type of the application it runs in, so `GLUE_APPLICATION` selects the gate:
`GLUE_STOREFRONT` reflects `Generated\Api\Storefront` against the `StorefrontApi` suites,
`GLUE_BACKEND` reflects `Generated\Api\Backend` against the `BackendApi` ones. CI runs both.

The check itself stays boot-free: it only reflects generated classes and test attributes. Booting
the Glue console is the console's cost, not the report's.

## Scope

Both resources and tests are **discovered automatically** — adding a test never requires touching
the tool. Coverage is *enforced* for **every generated resource** of the API type being measured. A
project takes one back out of the gate by naming it in its own configuration:

```php
$sprykerApiPlatform->contractCoverageExcludedResources(['some-resource']);
```

The list is project data, so it lives in the application's own
`config/Glue*/packages/spryker_api_platform.php` rather than in the core runner; the runner only
defines *how* an exclusion is applied. Each application compiles its own container, so Storefront's
list and Backend's are independent and a short name the two API types share — `customers` — can be
enforced in one and excluded in the other. A name that matches no generated resource of that API
type fails the gate, so an exclusion cannot outlive the resource it was written for. Excluding a
resource is a deliberate, reviewable act — there is no way to fall out of the gate by omission,
which is what the earlier allow-list needed a separate drift check to catch.

The response-attribute dimension shares this same list — there is no separate one for it. A
resource's required response attributes are enforced exactly when the resource itself is enforced,
so excluding a resource drops its response attributes from the gate alongside its operations and
validation rules.

## Parts

| Class | Role |
|-------|------|
| `Attribute\CoversApiOperation`, `Attribute\CoversApiValidation`, `Attribute\Rule`, `Attribute\CoversApiRequiredResponseAttributes` | the declarations |
| `SchemaTruthLoader` | reflects the generated `#[ApiResource]` classes into a `TruthSet`, using `ResponseAttributeTruthCollector` for the response-attribute paths |
| `ResponseAttributeTruthCollector` | derives the required response-attribute paths of every success operation from its resource's readable properties |
| `ResponseAttribute` | one required attribute of one operation — the dispatch key paired with the path, and the `<dispatch key>  <path>` key a gap entry is printed from |
| `ResponseAttributePath` | the path grammar: what a valid path looks like, how a concrete index normalizes to the `[]` wildcard so truth and assertion compare equal, and how a concrete path decomposes for walking a response |
| `AnnotationCollector` | reflects the `#[Covers*]` declarations off the test methods |
| `ScopeResolver` | splits resources into enforced vs. existence truth |
| `CoverageCalculator` | pure diff → `CoverageReport` (covered / uncovered / stale) |
| `ContractCoverageRunner` | discovers one API type's resources and tests, applies the filter, orchestrates the above |
| `ContractCoverageResult` | one run's outcome, plus why the gate fails |
| `ResourceNameMatcher` | resolves what `--module` was given to a generated resource short name |
| `OperationCoverageRecorder`, `OperationVerifier` | the runtime side that verifies operation declarations |
| `ResponseAttributeRecorder` | the runtime side that records which response-attribute paths a `#[CoversApiRequiredResponseAttributes]` test actually asserted |
| `ApiContractCoverageCommand` | the `api:contract:coverage` console command |
| `ContractCoverageDimension`, `ContractCoverageEnforcement` | the reported dimensions and which of them fail the gate |
| `ContractCoverageBaseline`, `BaselineEntry` | the items known product bugs keep uncovered, and the entries to remove |
| `DimensionCoverage`, `CoverageItem` | one dimension's covered / uncovered / stale items, and the key every item compares by |
| `RecordedExchange`, `RecordedExchangeFactory` | one request of a test: status, error codes, sent attributes and includes, violations, access decision |
| `Attribute\Scenario`, `Attribute\CoversApiRequestAttributes`, `Attribute\CoversApiIncludes`, `Attribute\ReplaysOpenApiExamples` | the declarations of the reported dimensions |
| `ErrorMappingResolver`, `ErrorMappingDiscovery`, `ErrorMappingEntry` | reads a registered Zed error mapping, and lists the unregistered ones |
| `ValidationEvidenceVerifier`, `ValidationAttributePath` | holds a validation declaration to the violations raised |
| `RequestAttributeTruthCollector`, `RequestAttributePathExtractor`, `RequestAttributeVerifier` | the writable attributes of an input operation, and what a request sent |
| `IncludeRelationship`, `IncludedRelationshipRecorder`, `IncludeEvidenceVerifier` | the declared includes, and the proof of one |
| `Replay\OpenApiExampleRequestBuilder`, `Replay\ReplayableRequest`, `Replay\OpenApiExampleReplayContext` | the example replay |
