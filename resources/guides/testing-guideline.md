# API Platform Testing Guideline

This guideline provides explicit instructions for implementing tests for Storefront and BackendAPI endpoints in the API Platform.

## Test Structure Requirements

### Base Classes
- **BackendAPI tests**: Extend `SprykerTest\ApiPlatform\Test\BackendApiTestCase`
- **StorefrontAPI tests**: Extend `SprykerTest\ApiPlatform\Test\StorefrontApiTestCase`

### Test Naming Convention
Use `given/when/then` syntax with proper grammar and maximum expressiveness:
```php
public function testGivenValidDataWhenCreatingStoreViaPostThenStoreIsCreatedSuccessfully(): void
public function testGivenInvalidDataWhenCreatingCustomerViaPostThenValidationErrorIsReturned(): void
public function testGivenExistingStoreWhenRetrievingViaGetThenStoreDataIsReturned(): void
```

### Test Body Structure
**ALWAYS** use this four-part structure with comments:
```php
public function testExample(): void
{
    // Arrange

    // Act

    // Assert

    // Expect (Optional: only when testing exceptions)
}
```

### Test Annotations
**ALWAYS** include these group annotations:
```php
/**
 * @group SprykerTest
 * @group Glue
 * @group {ModuleName}
 * @group {ApiType}
 * @group {TestClassName}
 */
```

## BackendAPI Testing Patterns

### Required Tester Declaration
```php
protected BackendApiTester $tester;
```

### Essential Test Cases to Implement

#### 1. POST - Successful Creation
```php
public function testGivenValidDataWhenCreatingResourceViaPostThenResourceIsCreatedSuccessfully(): void
{
    // Arrange
    $resourceData = ['field' => 'value'];

    // Act
    $this->createClient()->request('POST', '/resources', ['json' => $resourceData]);

    // Assert
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['field' => 'value']);
}
```

#### 2. POST - Validation Error
```php
public function testGivenInvalidDataWhenCreatingResourceViaPostThenValidationErrorIsReturned(): void
{
    // Arrange
    $invalidData = ['field' => ''];

    // Act
    $this->createClient()->request('POST', '/resources', ['json' => $invalidData]);

    // Assert
    $this->assertResponseStatusCodeSame(422);
    $this->assertJsonContains(['@type' => 'ConstraintViolation']);
    $this->assertJsonContains(['violations' => [['propertyPath' => 'field']]]);
}
```

#### 3. POST - Business Rule Violation
```php
public function testGivenDuplicateNameWhenCreatingResourceViaPostThenErrorIsReturned(): void
{
    // Arrange
    $this->tester->haveResource(['name' => 'duplicate']);
    $duplicateData = ['name' => 'duplicate'];

    // Act
    $this->createClient()->request('POST', '/resources', ['json' => $duplicateData]);

    // Assert
    $this->assertResponseStatusCodeSame(422);
    $this->assertJsonContains(['@type' => 'Error']);
    $this->assertJsonContains(['detail' => 'Resource with this name already exists.']);
}
```

#### 4. GET - Single Resource
```php
public function testGivenExistingResourceWhenRetrievingViaGetThenResourceDataIsReturned(): void
{
    // Arrange
    $resourceTransfer = $this->tester->haveResource(['name' => 'test']);

    // Act
    $this->createClient()->request('GET', sprintf('/resources/%s', $resourceTransfer->getIdentifier()));

    // Assert
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['name' => 'test']);
}
```

#### 5. GET - Collection
```php
public function testGivenMultipleResourcesWhenRetrievingCollectionViaGetThenAllResourcesAreReturned(): void
{
    // Arrange
    $this->tester->haveResource(['name' => 'resource1']);
    $this->tester->haveResource(['name' => 'resource2']);
    $this->tester->haveResource(['name' => 'resource3']);

    // Act
    $response = $this->createClient()->request('GET', '/resources');

    // Assert
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['@type' => 'Collection']);

    $resourceNames = array_column($response->toArray()['member'], 'name');
    $this->assertContains('resource1', $resourceNames);
    $this->assertContains('resource2', $resourceNames);
    $this->assertContains('resource3', $resourceNames);
}
```

#### 6. GET - Pagination
```php
public function testGivenPaginationParamsWhenRetrievingCollectionThenPaginatedResultsAreReturned(): void
{
    // Arrange
    $itemsPerPage = 10;
    $totalItems = $itemsPerPage * 2;

    for ($i = 1; $i <= $totalItems; $i++) {
        $this->tester->haveResource(['name' => sprintf('resource_%d', $i)]);
    }

    // Act
    $response = $this->createClient()->request('GET', sprintf('/resources?page=1&itemsPerPage=%d', $itemsPerPage));

    // Assert
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['@type' => 'Collection']);

    $responseData = $response->toArray();
    $this->assertGreaterThanOrEqual($itemsPerPage, count($responseData['member']));
    $this->assertArrayHasKey('view', $responseData);
}
```

#### 7. PATCH - Update
```php
public function testGivenExistingResourceWhenUpdatingViaPatchThenResourceIsUpdatedSuccessfully(): void
{
    // Arrange
    $resourceTransfer = $this->tester->haveResource(['name' => 'original', 'field' => 'value']);
    $updateData = ['name' => 'updated', 'field' => 'value'];

    // Act
    $this->createClient()->request('PATCH', sprintf('/resources/%s', $resourceTransfer->getIdentifier()), [
        'json' => $updateData,
        'headers' => ['Content-Type' => 'application/merge-patch+json'],
    ]);

    // Assert
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['name' => 'updated']);
}
```

#### 8. DELETE - Success
```php
public function testGivenExistingResourceWhenDeletingViaDeleteThenResourceIsDeletedSuccessfully(): void
{
    // Arrange
    $resourceTransfer = $this->tester->haveResource(['name' => 'to-delete']);

    // Act
    $this->createClient()->request('DELETE', sprintf('/resources/%s', $resourceTransfer->getIdentifier()));

    // Assert
    $this->assertResponseStatusCodeSame(204);
    $this->assertResponseHasNoContent();
}
```

#### 9. GET - Not Found
```php
public function testGivenNonExistentResourceWhenRetrievingViaGetThen404IsReturned(): void
{
    // Act
    $this->createClient()->request('GET', '/resources/NON-EXISTENT-ID');

    // Assert
    $this->assertResponseStatusCodeSame(404);
}
```

## StorefrontAPI Testing Patterns

### Required Tester Declaration
```php
protected StorefrontApiTester $tester;
```

### Key Difference: Use Service Mocks
StorefrontAPI tests **MUST** use Codeception stubs to mock Client interfaces:

```php
use Codeception\Stub;
```

### Essential Test Cases to Implement

#### 1. GET - Single Resource with Mock
```php
public function testGivenExistingResourceWhenRetrievingViaGetThenResourceDataIsReturned(): void
{
    // Arrange
    $clientStub = Stub::makeEmpty(ResourceClientInterface::class, [
        'getResource' => (new ResourceTransfer())->setName('test'),
    ]);

    $this->getContainer()->set(ResourceClientInterface::class, $clientStub);

    // Act
    $this->createClient()->request('GET', '/resources/test');

    // Assert
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['name' => 'test']);
}
```

#### 2. GET - Collection with Mock
```php
public function testGivenMultipleResourcesWhenRetrievingCollectionViaGetThenAllResourcesAreReturned(): void
{
    // Arrange
    $clientStub = Stub::makeEmpty(ResourceClientInterface::class, [
        'getResourceNames' => ['resource1', 'resource2'],
    ]);

    $this->getContainer()->set(ResourceClientInterface::class, $clientStub);

    // Act
    $this->createClient()->request('GET', '/resources');

    // Assert
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['@type' => 'Collection']);
}
```

#### 3. GET - Not Found with Mock
```php
public function testGivenNonExistentResourceWhenRetrievingViaGetThen404IsReturned(): void
{
    // Arrange
    $clientStub = Stub::makeEmpty(ResourceClientInterface::class, [
        'getResourceNames' => [],
    ]);

    $this->getContainer()->set(ResourceClientInterface::class, $clientStub);

    // Act
    $this->createClient()->request('GET', '/resources/NON-EXISTENT');

    // Assert
    $this->assertResponseStatusCodeSame(404);
}
```

#### 4. GET - Pagination with Mock
```php
public function testGivenPaginationParamsWhenRetrievingCollectionThenPaginatedResultsAreReturned(): void
{
    // Arrange
    $clientStub = Stub::makeEmpty(ResourceClientInterface::class, [
        'getResourceNames' => ['resource1', 'resource2'],
    ]);

    $this->getContainer()->set(ResourceClientInterface::class, $clientStub);

    // Act
    $this->createClient()->request('GET', '/resources?page=1&itemsPerPage=10');

    // Assert
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['@type' => 'Collection']);
}
```

## Testing Relationships

### Testing Includes Parameter

Relationships enable resources to include related resources via the `?include=` parameter.

**Test Pattern:**
```php
public function testGivenCustomerWithAddressesWhenRetrievingCustomerWithIncludesThenAddressesAreIncluded(): void
{
    // Arrange
    $customerTransfer = $this->tester->haveCustomer();
    $this->tester->haveAddress(['customerReference' => $customerTransfer->getCustomerReference()]);
    $this->tester->haveAddress(['customerReference' => $customerTransfer->getCustomerReference()]);

    // Act
    $response = $this->createClient()->request(
        'GET',
        sprintf('/customers/%s?include=addresses', $customerTransfer->getCustomerReference())
    );

    // Assert
    $this->assertResponseIsSuccessful();
    $data = $response->toArray();

    // Assert main resource
    $this->assertArrayHasKey('data', $data);
    $this->assertSame('customers', $data['data']['type']);

    // Assert relationships section exists
    $this->assertArrayHasKey('relationships', $data['data']);
    $this->assertArrayHasKey('addresses', $data['data']['relationships']);

    // Assert included section contains addresses
    $this->assertArrayHasKey('included', $data);
    $this->assertCount(2, $data['included']);

    $addressTypes = array_column($data['included'], 'type');
    $this->assertContains('customers-addresses', $addressTypes);
}
```

### Testing Multiple Includes

```php
public function testGivenCustomerWhenRequestingMultipleIncludesThenAllRelatedResourcesAreIncluded(): void
{
    // Arrange
    $customerTransfer = $this->tester->haveCustomer();
    $this->tester->haveAddress(['customerReference' => $customerTransfer->getCustomerReference()]);
    $this->tester->haveOrder(['customerReference' => $customerTransfer->getCustomerReference()]);

    // Act
    $response = $this->createClient()->request(
        'GET',
        sprintf('/customers/%s?include=addresses,orders', $customerTransfer->getCustomerReference())
    );

    // Assert
    $data = $response->toArray();
    $this->assertArrayHasKey('included', $data);

    $includedTypes = array_unique(array_column($data['included'], 'type'));
    $this->assertContains('customers-addresses', $includedTypes);
    $this->assertContains('orders', $includedTypes);
}
```

### Testing Invalid Include Names

```php
public function testGivenInvalidIncludeNameWhenRequestingIncludesThenInvalidIncludesAreIgnored(): void
{
    // Arrange
    $customerTransfer = $this->tester->haveCustomer();

    // Act
    $response = $this->createClient()->request(
        'GET',
        sprintf('/customers/%s?include=invalidRelationship', $customerTransfer->getCustomerReference())
    );

    // Assert
    $this->assertResponseIsSuccessful();
    $data = $response->toArray();

    // Valid includes should work, invalid ones ignored
    $this->assertArrayNotHasKey('included', $data);
}
```

### Testing Empty Relationships

```php
public function testGivenCustomerWithoutAddressesWhenRequestingIncludesThenEmptyRelationshipIsReturned(): void
{
    // Arrange
    $customerTransfer = $this->tester->haveCustomer();

    // Act
    $response = $this->createClient()->request(
        'GET',
        sprintf('/customers/%s?include=addresses', $customerTransfer->getCustomerReference())
    );

    // Assert
    $data = $response->toArray();
    $this->assertArrayHasKey('relationships', $data['data']);
    $this->assertArrayHasKey('addresses', $data['data']['relationships']);

    // Empty relationship returns empty array
    $this->assertSame(['data' => []], $data['data']['relationships']['addresses']);
}
```

### Testing JSON:API Compliance

**Verify correct structure:**
```php
public function testGivenIncludedResourcesWhenRetrievingThenJsonApiStructureIsValid(): void
{
    // Arrange
    $customerTransfer = $this->tester->haveCustomer();
    $this->tester->haveAddress(['customerReference' => $customerTransfer->getCustomerReference()]);

    // Act
    $response = $this->createClient()->request(
        'GET',
        sprintf('/customers/%s?include=addresses', $customerTransfer->getCustomerReference())
    );

    // Assert
    $data = $response->toArray();

    // Verify main resource structure
    $this->assertArrayHasKey('data', $data);
    $this->assertArrayHasKey('type', $data['data']);
    $this->assertArrayHasKey('id', $data['data']);
    $this->assertArrayHasKey('attributes', $data['data']);
    $this->assertArrayHasKey('relationships', $data['data']);

    // Verify included resource structure
    foreach ($data['included'] as $includedResource) {
        $this->assertArrayHasKey('type', $includedResource);
        $this->assertArrayHasKey('id', $includedResource);
        $this->assertArrayHasKey('attributes', $includedResource);
    }

    // Verify relationship linkage
    $relationshipData = $data['data']['relationships']['addresses']['data'];
    foreach ($relationshipData as $linkage) {
        $this->assertArrayHasKey('type', $linkage);
        $this->assertArrayHasKey('id', $linkage);
    }
}
```

## Codeception Configuration

### Directory Structure
```
tests/
└── SprykerTest/
    └── Glue/
        └── {Module}/
            ├── codeception.yml
            ├── BackendApi/
            │   └── {Resource}BackendApiTest.php
            ├── StorefrontApi/
            │   └── {Resource}StorefrontApiTest.php
            └── _support/
                ├── BackendApiTester.php
                └── StorefrontApiTester.php
```

### codeception.yml Template
```yaml
namespace: SprykerTest\Glue\{Module}
paths:
    tests: .
    data: ../../../_data
    support: _support
    output: ../../../_output

suites:
    BackendApi:
        path: BackendApi
        actor: BackendApiTester
        modules:
            enabled:
                - \SprykerTest\Shared\Testify\Helper\Environment
                - \SprykerTest\Shared\Testify\Helper\LocatorHelper
                - \SprykerTest\Service\Container\Helper\ContainerHelper
                - \SprykerTest\ApiPlatform\Helper\ApiPlatformHelper:
                      mode: 'core'
                - \SprykerTest\Shared\Propel\Helper\TransactionHelper
                - \SprykerTest\Shared\{Module}\Helper\{Module}DataHelper
                - \SprykerTest\Shared\Testify\Helper\DataCleanupHelper

    StorefrontApi:
        path: StorefrontApi
        actor: StorefrontApiTester
        modules:
            enabled:
                - \SprykerTest\Shared\Testify\Helper\Environment
                - \SprykerTest\Shared\Testify\Helper\LocatorHelper
                - \SprykerTest\Service\Container\Helper\ContainerHelper
                - \SprykerTest\ApiPlatform\Helper\ApiPlatformHelper:
                      mode: 'core'
                - \SprykerTest\Shared\Propel\Helper\TransactionHelper
                - \SprykerTest\Shared\{Module}\Helper\{Module}DataHelper
                - \SprykerTest\Shared\Testify\Helper\DataCleanupHelper
```

## Test Infrastructure and Helpers

### Test Data Management

**Codeception Helpers:**

The ApiPlatform module provides specialized helpers for test infrastructure:

**ApiPlatformHelper** - Test suite cleanup
- Location: `tests/SprykerTest/ApiPlatform/_support/Helper/ApiPlatformHelper.php`
- Purpose: Manages Symfony test kernel cache cleanup between test suites
- Automatically cleans compiled cache after test suite execution
- Ensures clean state and prevents cache pollution

**ApiResourceGeneratorHelper** - Resource generation testing
- Location: `tests/SprykerTest/ApiPlatform/_support/Helper/ApiResourceGeneratorHelper.php`
- Purpose: Simplifies testing of resource generation pipeline
- Creates temporary test directories
- Generates sample schemas for testing
- Verifies generated output
- Automatic cleanup after tests

**ApiSchemaHelper** - Schema manipulation
- Location: `tests/SprykerTest/ApiPlatform/_support/Helper/ApiSchemaHelper.php`
- Purpose: Provides schema creation and manipulation utilities
- Creates valid test schemas programmatically
- Applies schema transformations
- Validates schema structures
- Loads schema fixtures from `_data/` directory

**ApiPlatformConfigBuilder** - Configuration builder
- Location: `tests/SprykerTest/ApiPlatform/_support/Helper/ApiPlatformConfigBuilder.php`
- Purpose: Fluent interface for building test configurations
- Example usage:
  ```php
  $config = ApiPlatformConfigBuilder::create()
      ->withSourceDirectory('/path/to/test/schemas')
      ->withCacheDir($this->tester->getTempDir())
      ->withDebugMode(true)
      ->build();
  ```

### Test Fixtures

**Fixture Location:**
```
tests/SprykerTest/ApiPlatform/_data/
├── schemas/              # Sample YAML resource schemas
│   ├── valid/
│   ├── invalid/
│   └── relationships/
├── validation/           # Sample validation schemas
└── generated/            # Generated output (gitignored)
```

**Creating Test Schemas:**
```php
// Use helper for consistent schema creation
$schema = $this->tester->haveResourceSchema([
    'name' => 'TestResource',
    'shortName' => 'test-resource',
    'properties' => [
        'id' => ['type' => 'integer', 'identifier' => true],
        'name' => ['type' => 'string', 'required' => true],
    ],
]);
```

**Loading Fixture Files:**
```php
$schemaContent = $this->tester->loadFixture('schemas/valid/customer.resource.yml');
```

### Codeception Actors

**ApiUnitTester** - Unit test actor
- Location: `tests/SprykerTest/ApiPlatform/_support/ApiUnitTester.php`
- Available methods from all configured helpers
- Used in unit tests extending `ApiUnitTestCase`

**ApiIntegrationTester** - Integration test actor
- Location: `tests/SprykerTest/ApiPlatform/_support/ApiIntegrationTester.php`
- Available methods from all configured helpers
- Used in integration tests with Symfony kernel

**BackendApiTester** - Backend API test actor
- Used in full-stack Backend API endpoint tests
- Provides `have*()` methods for creating test data
- Database transaction management
- Authentication helpers

**StorefrontApiTester** - Storefront API test actor
- Used in full-stack Storefront API endpoint tests
- Customer session management
- Cart and wishlist helpers

### Performance Optimizations

The ApiPlatform test infrastructure includes several performance optimizations:

**1. Symfony Test Kernel Cache Reuse**
- Test kernel cache is compiled once per test suite
- Cache persists across tests in the same suite
- Reduces overhead from ~500ms to ~50ms per test
- ApiPlatformHelper ensures cleanup after suite completion

**2. Database Transaction Rollback**
- Each test runs in a transaction
- Transactions are rolled back after test completion
- Eliminates need for database rebuild between tests
- Execution speed: ~100-200ms per test vs. seconds for rebuild

**3. Lazy Fixture Loading**
- Fixtures loaded on-demand rather than in setup
- Reduces memory footprint
- Only loads fixtures actually needed by test

**4. Temporary Directory Management**
- Tests use isolated temporary directories
- Prevents filesystem contention
- Automatic cleanup via helper methods

**5. Schema Caching**
- Parsed schemas cached during test execution
- Cache cleared between test suites
- Reduces redundant YAML parsing

**Performance Targets:**
- Unit tests: < 1 second total per test class
- Integration tests: < 5 seconds per test
- Full API tests: < 10 seconds per test (including database)

**Measuring Performance:**
```bash
# Run with timing information
docker/sdk testing vendor/bin/codecept run --steps

# Profile with Xdebug
docker/sdk testing -x XDEBUG_MODE=profile vendor/bin/codecept run

# Identify slow tests
docker/sdk testing vendor/bin/codecept run -g slow
```

### Test Organization Best Practices

**Directory Structure by Test Type:**

```
tests/SprykerTest/{Module}/
├── Unit/                          # Fast, isolated tests
│   ├── Generator/                 # < 1s per class
│   ├── Schema/
│   └── Utility/
├── Integration/                   # Medium speed tests
│   ├── Command/                   # 1-5s per test
│   └── Api/
└── BackendApi/                    # Full-stack tests
    └── {Resource}BackendApiTest   # 5-10s per test
```

**Test Grouping:**
- Use `@group` annotations for test categorization
- Common groups: `SprykerTest`, `Glue`, `ApiPlatform`, `slow`, `BackendApi`, `StorefrontApi`
- Run fast tests frequently during development
- Run slow tests before commits

**Test Isolation:**
- Each test must be independent
- No shared state between tests
- Use fresh test data per test
- Avoid global setup methods

## Common Assertions

### Response Status
```php
$this->assertResponseIsSuccessful();        // 2xx status
$this->assertResponseStatusCodeSame(200);   // OK
$this->assertResponseStatusCodeSame(201);   // Created
$this->assertResponseStatusCodeSame(204);   // No content
$this->assertResponseStatusCodeSame(400);   // Bad request
$this->assertResponseStatusCodeSame(404);   // Not found
$this->assertResponseStatusCodeSame(422);   // Validation error
```

### JSON Content
```php
$this->assertJsonContains(['field' => 'value']);
$this->assertJsonContains(['@type' => 'Collection']);
$this->assertJsonContains(['@type' => 'ConstraintViolation']);
$this->assertJsonContains(['@type' => 'Error']);
```

### Response Content
```php
$this->assertResponseHasNoContent();
$response = $this->createClient()->request('GET', '/resources');
$responseData = $response->toArray();
$this->assertArrayHasKey('member', $responseData);
```

## Running Tests

### Run specific test suite
```bash
# BackendAPI tests
docker/sdk testing vendor/bin/codecept run -g BackendApi

# StorefrontAPI tests
docker/sdk testing vendor/bin/codecept run -g StorefrontApi

# Specific module
docker/sdk testing vendor/bin/codecept run -g {Module}
```

## Critical Implementation Rules

1. **NEVER** skip the Arrange-Act-Assert structure
2. **NEVER** test multiple unrelated things in one test
3. **NEVER** use setup methods for test class. Setup **MUST** be done per test method
4. **ALWAYS** use meaningful test data (realistic values)
5. **ALWAYS** test both success and error scenarios
6. **ALWAYS** use `$this->createClient()->request()` for HTTP requests
7. **BackendAPI**: Use `$this->tester->have*()` helpers for test data
8. **StorefrontAPI**: Use `Codeception\Stub` for mocking Client interfaces
9. **NEVER** test internal method flows, only test entry points and outcomes
10. **ALWAYS** keep tests concise (maximum three lines per section when possible)
