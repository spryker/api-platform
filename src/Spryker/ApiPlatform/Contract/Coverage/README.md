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
not gaps), and exits non-zero when the gate fails. CI runs it in the Standard Validation job.

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
