# Testing API Platform resources

How to test an API Platform provider or processor, and which lane to put a test in.

Reference implementation: the Wishlists suites in
`tests/PyzTest/Glue/Wishlists/` (`StorefrontApiLogic` = Tier 1, `StorefrontApiIntegration` = Tier 2).

Which operations and validation rules those integration tests cover is declared with
`#[CoversApiOperation]` / `#[CoversApiValidation]` attributes and enforced by a CI gate — see
[contract coverage](../src/Spryker/ApiPlatform/Contract/Coverage/README.md).

## The tiers

Tests split into two tiers by what they cover and what they cost. Put a test in the cheapest
tier that can carry it.

| Tier | Covers | Kernel | Measured cost |
|------|--------|--------|---------------|
| **1 — logic** | provider/processor mapping, error → status mapping | shared, built once per process | ~0.4ms per test after a ~250ms first boot |
| **2 — integration** | full-stack CRUD + the real error surface, auth → 401/403, validation → 422, serialization/envelope, and `?include=` compound documents | real stack, host-lane | ~2–3s per test after a one-time SQLite template build |

Tier 2 runs host-lane over SQLite: the booted Glue kernel drives the real client and facade, and
the Client→Zed RPC is dispatched **in-process** to the GatewayController (JSON wire round-trip
preserved) against a SQLite database — no docker, no second booted application. Only OAuth token
introspection is stubbed (the host-lane login seam); everything on the data path is real. Auth and
input-validation negatives that need no persisted data (401/403, 422) live here too — the firewall
and the framework validator answer before the data layer is reached.

### Integration lane caveats

The `StorefrontApiIntegration` suite in `tests/PyzTest/Glue/Wishlists/codeception.yml` is the
reference wiring. Two constraints are load-bearing:

- The core `\SprykerTest\Shared\Testify\Helper\SqliteDatabaseHelper` must be enabled **first** — it
  freezes the SQLite engine into the env before any module reads the Spryker `Config`. An externally
  set `SPRYKER_DB_ENGINE` (e.g. a MariaDB lane) still wins.
- `ContainerHelper` must **not** be enabled here. Its `_after()` nulls the shared
  `ContainerDelegator` singleton whenever its container was touched (the DB-fixture path touches
  it), discarding the `bootOnce` kernel's compiled container between methods — every method after
  the first then fails with a null-container `TypeError`. The logic lane keeps it (its container
  stays untouched).

### The umbrella helpers

`\SprykerTest\ApiPlatform\Helper\StorefrontApiIntegrationHelper` registers the shared integration
stack (database lane, bootstrap, locator, config/dependency/data-cleanup, transaction, processor
resolution, in-process Zed) from its constructor; `publish: true` adds the in-process publish leg.
`\SprykerTest\ApiPlatform\Helper\StorefrontApiLogicHelper` does the same for the Logic lane —
container resolution only, no database, no HTTP. A suite's codeception.yml therefore lists: the
umbrella first, its domain data helpers, and `ApiPlatformHelper` last. Per-child config overrides
go under `modules: config:` — except `application`, `projectNamespaces`,
`applicationPluginProvider` and `environmentModule`, which are umbrella config keys.

`environmentModule` is required and has no default: the helper that defines the `APPLICATION`
constants is project-owned, so core names no class for it. Point it at the project's helper
(`\PyzTest\Shared\Testify\Helper\Environment` in this suite) and the umbrella creates it in the one
position it must occupy — after the bootstrap, before `LocatorHelper` freezes the Config.

### Tier 1 — logic

Extend `StorefrontApiTestCase`. Register the data-layer stubs, take the SUT from the helper, call
`provide()` / `process()` directly:

```php
$wishlistTransfer = $this->tester->haveWishlistTransfer();
$this->tester->setService(
    WishlistClientInterface::class,
    $this->tester->createClientStub(WishlistClientInterface::class, [
        'getWishlistByFilter' => $this->tester->haveSuccessfulWishlistResponseTransfer($wishlistTransfer),
    ]),
);
$provider = $this->tester->getProvider(WishlistsStorefrontProvider::class);

$result = $provider->provide(
    $this->tester->getGetOperation(WishlistsStorefrontResource::class),
    ['uuid' => $wishlistTransfer->getUuid()],
    $this->tester->getAuthenticatedContext(),
);
```

`getProvider()` / `getProcessor()` resolve the SUT **from the container**, so the test exercises the
real service wiring and only the collaborators it names are stubbed. Everything else is the real
service. There is no reflective construction and no auto-doubling: a collaborator a test wants to
control has to be registered with `setService()`.

The kernel is shared across the suite's methods (`bootOnce` + `reuseApplicationContainer` in the
suite's `codeception.yml`), so it is built once per codecept process, not per test. The container is
reset between methods, which is what lets each method bind its own stubs — a service already bound
in the container cannot be replaced.

`setService()` has to be called **before** the first `getProvider()` / `getProcessor()` in a method:
the mocks are bound when the kernel is taken for that method, so a later registration for the same
id does not take effect.

The container is built from the project's own configuration, so the generated resources and the
`framework.test` setting come from `config/<App>/packages/*`. Test mode has to be on for the
environment the lane runs in — see "Running the suites" below.

### Tier 2 — full-stack integration

Extend `StorefrontApiTestCase` / `BackendApiTestCase`, enable the SQLite lane (see the caveats
above), provision the data the scenario needs through the module data helpers, then drive real
requests:

```php
$sku = $this->tester->haveProduct()->getSku();
$customerTransfer = $this->tester->haveCustomer();
$wishlistTransfer = $this->tester->haveWishlist([WishlistTransfer::FK_CUSTOMER => $customerTransfer->getIdCustomer()]);
$this->tester->actingAsCustomer($customerTransfer);
$response = $this->handleApiRequest('GET', '/wishlists/' . $wishlistTransfer->getUuid());
```

Everything on the data path is real — the request crosses the booted kernel, the compiled
container, the real client and facade and the SQLite database. Provision fixtures through the data
helpers (`haveCustomer()`, `haveWishlist()`, `haveProduct()`, …), never hand-rolled; a test owns the
products, customers and wishlists it needs. Auth negatives use the login seams
(`actingWithInvalidToken()`, `actingWithScopes([])`); a request left anonymous exercises the real
firewall.

**Scope matters.** A project-mode boot loads every Storefront resource, so the first run after a
cache wipe pays the full container compile. The template DB is built once — schema plus the
standard bring-up (`setup:init-db` and the store/currency/locale importers) — and copied per run.

## Running the suites

The suites need generated code that is not in git. Once per checkout, and again after any schema
change:

```bash
vendor/bin/console transfer:generate
vendor/bin/console transfer:databuilder:generate
vendor/bin/console propel:schema:copy
vendor/bin/console propel:model:build
vendor/bin/console transfer:entity:generate
vendor/bin/console search:setup:source-map
GLUE_APPLICATION=GLUE_STOREFRONT vendor/bin/glue api:generate
vendor/bin/console testify:build:sqlite-template
```

The order matters: entity transfers derive from the merged Propel schema, so the copy and the model
build come first. `transfer:databuilder:generate` and `testify:build:sqlite-template` are registered
only when development console commands are enabled (`ENABLE_DEVELOPMENT_CONSOLE_COMMANDS`).

`search:setup:source-map` writes the `Generated\Shared\Search\*IndexMap` classes. Only search-backed
resources need them, but the catalog query plugins reference them while the query is *built*, so a
missing map is a fatal error rather than an empty result.

**A long-lived local checkout needs `vendor/bin/console cache:class-resolver:build` after any new
`Pyz` override.** `src/Generated/Shared/Kernel/Pyz/resolvableClassCache*.php` maps every resolvable
class to the winning namespace, and it is read in preference to live resolution. It never expires,
so a `Pyz\…` class added after it was written is silently ignored and the module resolves to the
core class — no error, just core behaviour where the project's should be. CI runs from a bare
checkout, where the file is absent and resolution is live, so this bites only locally.

`testify:build:sqlite-template` leaves an existing template alone. Pass `--force` to drop and
rebuild it after a Zed schema change, or `--path` to build it somewhere other than the configured
default. A suite run rebuilds a missing template on its own, so the explicit call is only needed to
front-load the cost or to force a rebuild.

Both tiers resolve services from the container, which needs Symfony's test container — so
`framework.test` has to be on for the environment the lane runs in. It is configured per
application in `config/<App>/packages/framework.php`, and the host lane's `devtest` is included
there. Without it the suites fail with
`Could not find service "test.service_container"`.

The container itself is compiled on first use and then cached, which takes roughly 45s. That is a
one-time cost per checkout and after any change to configuration or generated resources — run the
suites once after regenerating and the compile is out of the way.

`api:generate` is a Glue console command — `vendor/bin/glue`, not `vendor/bin/console`. It removes
`src/Generated/Api/{ApiType}` before it parses anything, and `--dry-run` does not suppress that, so
an interrupted run leaves no resources behind and every test fails with
`Class "Generated\Api\Storefront\…Resource" not found`. The fix is to re-run it; pass
`--keep-existing` when you only want to inspect.

Then run one module's suites — no docker, no services:

```bash
APPLICATION_ENV=devtest vendor/bin/codecept run -c tests/PyzTest/Glue/Wishlists/codeception.yml
```

### In CI

The `API Platform Tests` job in `.github/workflows/ci.yml` runs those same steps on a bare runner.

It finds its work by convention: every `tests/PyzTest/Glue/*/codeception.yml` declaring a
`StorefrontApiLogic:` or `StorefrontApiIntegration:` suite is picked up, one codecept process per
module config, and the shared Glue lane in `_codeception.yml` skips those two suite names. Use
exactly those names and a new module needs no workflow change; use different ones and the suites
either run nowhere or land in the docker lane, which is not a lane they can survive.

One process per module config is not a style choice. These suites boot the Glue kernel in-process
and need `APPLICATION=GLUE_STOREFRONT`; the constant is process-global and first-wins, so a suite
sharing a process with the docker Glue lane inherits whatever that lane set.

Test environments hash passwords at the minimum bcrypt cost — `PASSWORD_HASH_COST = 4` in
`config_default-devtest.php` and in `config_default-docker.ci.php`, which the Cypress config
inherits by requiring it. Never carry that into a production-facing config.

## Fast-path config keys

All opt-in; omitting a key keeps the historical per-method-boot, debug-on behaviour.

```yaml
- \SprykerTest\ApiPlatform\Helper\ApiPlatformHelper:
      mode: 'project'                  # or 'core'
      apiType: 'Storefront'            # or 'Backend'
      debug: false                     # Symfony debug off on warm resources (default: true)
      bootOnce: true                   # one kernel per suite, reset between methods (default: false)
      reuseApplicationContainer: true  # keep the ContainerDelegator singleton (default: false)
```

### `setService()` ordering caveat

`setService($id, $stub)` must be called **before** the first `createClient()` / `getTestKernel()` /
`getProcessor()` / `getProvider()` in a test method. The mocks are bound when the kernel is taken
for that method, so registering an id after that point has no effect. The container knows nothing
about mocks at compile time — they are bound afterwards through Symfony's test container, which is
also why the same id cannot be re-bound within one method once the container has it.

## Helper inventory

Enable these through the actor in the suite's `codeception.yml`.

### Both tiers

| Helper | Provides |
|--------|----------|
| `ApiProcessorProviderHelper` | `setService()`, `getProcessor()`, `getProvider()`, `getResource()`, `getXOperation()`, `getContext()`, `getAuthenticatedContext()`, `createClientStub()`, `assertThrowsGlueApiExceptionWithStatus()` |
| `ApiLoginHelper` | `actingAsCustomer()` / `actingWithScopes()` / `actingWithInvalidToken()` — stubbed-OAuth login for the booted kernel |
| `ApiPlatformHelper` | test mode, resource generation/cleanup, the fast-path config keys above |
| `OperationFactory` | `Get` / `GetCollection` / `Post` / `Patch` / `Delete` metadata objects |
| `ApiContext` | request context: `withCustomer()`, `withRouteParams()` |

### Tier 2 only — standing in for the missing infrastructure

The host lane has no Redis, no Elasticsearch and nothing draining the queue. These five helpers put
a real substitute behind each, so the code above them still runs unchanged. None of them stubs the
resource under test.

| Helper | Stands in for | Provides |
|--------|---------------|----------|
| `\SprykerTest\Client\StorageDatabase\Helper\SqliteStorageHelper` | Redis, read side | Points the Storage client at `StorageDatabasePlugin`, so reads hit the `spy_*_storage` tables of the lane's SQLite database. Config only — no methods |
| `\SprykerTest\Zed\Publisher\Helper\PublishHelper` | the queue | `publishPendingEvents()` drains what the test just created; `publishEntities($eventName, $ids)` publishes rows that never raised an event. The sync leg stays off — it only pushes into Redis |
| `\SprykerTest\Shared\Testify\Helper\StorageCacheHelper` | — | `resetStorageCaches()`, plus a reset before every test. The storage clients memoise in statics that survive the container reset; a read taken *before* the arrange step otherwise pins the empty result for the whole process. Further caches are added from the suite's `codeception.yml`, not this class |
| `\SprykerTest\Client\Search\Helper\SearchResponseStubHelper` | Elasticsearch | `stubSearchResult(array $formattedSearchResult)` — takes the search-adapter plugin list. Everything above the adapter (query plugins, expanders, the Catalog client, the mappers) runs for real, so the array is the **post-formatting** result, not a raw Elasticsearch body |
| `\SprykerTest\Client\Queue\Helper\QueueHelper` (config `application: Zed`) | — | Backs the publish leg's in-memory queue for a `Glue`-namespaced suite. The core client-side `QueueHelper` guesses the application from the suite namespace and would guess `Glue`, finding no `QueueConfig`; the umbrella forces `application: Zed` when `publish: true` |

Ordering is load-bearing, on top of the two constraints under "Integration lane caveats" — but the
umbrella helper (see above) now owns it: `StorefrontApiIntegrationHelper` creates
`SqliteDatabaseHelper` and `DependencyHelper` — which carries the bindings `SqliteStorageHelper`
and `SearchResponseStubHelper` write — before a suite's own child list runs, and, when
`publish: true`, registers `PublishHelper` after `EventHelper`, `QueueHelper`, `BusinessHelper` and
`EventBehaviorHelper`, whose in-memory queue and publisher subscriber it drives. A suite no longer
lists any of the publish-leg helpers itself.
`tests/PyzTest/Glue/CatalogSearch/codeception.yml` is the worked example: `publish: true` plus the
storage and search stand-ins above.

A storage resource whose name does not derive its table as `spy_<resource>_storage` needs an entry in
`SprykerTest\Client\StorageDatabase\Sqlite\SqliteStorageDatabaseConfig`. Two exist today —
`translation` → `spy_glossary_storage` and `product_search_config_extension` →
`spy_product_search_config_storage`. Miss the `translation` one and *every* error response becomes
`PDOException: no such table`, because the error provider translates its message before rendering —
the reported exception then has nothing to do with the actual failure.

The authenticated-customer transfer comes from the core `SprykerTest\Shared\Customer\Helper\CustomerDataHelper`:
`haveCustomerTransfer()` (non-persisting, for the fast lanes) and `haveCustomer()` (DB-backed, for the
integration lane) — the API test lanes carry no customer code of their own.

Module-owned data and assertions belong in that module's own helper, shipped from the core module
so projects can enable it — e.g. `SprykerTest\Shared\Wishlist\Helper\WishlistApiTestHelper` builds
the wishlist transfers a stubbed client returns and asserts a resource against them. Keep test
fixtures out of the test classes: no test should carry its own uuids, names or counts.

## Conventions

- Build transfers through DataBuilders (via a module helper), never hand-rolled in a test.
- Test method names are Given/When/Then; body comments are Arrange/Act/Assert.
- Uri variables are read with `hasUriVariable()` / `getUriVariable()`, never from
  `$request->attributes->get('_route_params')`.
- Do not test validation constraint messages or JSON structure in Tier 1 — those belong in Tier 2.
