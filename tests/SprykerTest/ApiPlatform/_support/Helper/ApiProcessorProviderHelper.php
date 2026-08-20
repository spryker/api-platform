<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Codeception\Module;
use Codeception\Stub;
use Codeception\Test\TestCaseWrapper;
use Codeception\TestInterface;
use Generated\Shared\Transfer\CustomerTransfer;
use PHPUnit\Framework\Assert;
use Spryker\ApiPlatform\Exception\GlueApiException;
use SprykerTest\ApiPlatform\Test\AbstractApiTestCase;
use SprykerTest\Shared\Customer\Helper\CustomerDataHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

/**
 * Codeception helper for testing API Platform processors and providers directly, with the mocked
 * services {@see setService()} registered wired in.
 *
 * Everything resolves from the container, so a test exercises the real service wiring and stubs
 * only what it names. The test class has to extend {@see AbstractApiTestCase}. Operations,
 * resources and the request context ({@see getAuthenticatedContext()}) need no kernel.
 *
 * Usage in module codeception.yml:
 * ```yaml
 * modules:
 *     enabled:
 *         - \SprykerTest\ApiPlatform\Helper\ApiProcessorProviderHelper
 * ```
 */
class ApiProcessorProviderHelper extends Module
{
    protected ?AbstractApiTestCase $testCase = null;

    public function _before(TestInterface $test): void
    {
        $testCase = $test instanceof TestCaseWrapper ? $test->getTestCase() : $test;

        if ($testCase instanceof AbstractApiTestCase) {
            $this->testCase = $testCase;
        }
    }

    public function _after(TestInterface $test): void
    {
        $this->testCase = null;
    }

    /**
     * Registers the mock as a synthetic container service, so it replaces the real one everywhere the
     * service graph references it. Call before getProcessor/getProvider.
     */
    public function setService(string $serviceId, object $mock): void
    {
        $this->assertTestCaseAvailable($serviceId);

        $this->testCase->setService($serviceId, $mock);
    }

    /**
     * Get a processor from the compiled container, with the registered mocks wired in.
     *
     * @template T of object
     *
     * @param class-string<T> $processorClass
     *
     * @return T
     */
    public function getProcessor(string $processorClass): object
    {
        $processor = $this->resolveService($processorClass);

        return Stub::make($processor, [
            'process' => function (mixed $data, Operation $operation, array $uriVariables = [], array $context = []) use ($processor) {
                if (is_array($context) && !$context) {
                    $context = $this->getContext()->toArray();
                }

                return $processor->process($data, $operation, $uriVariables, $context);
            },
        ]);
    }

    /**
     * Get a provider from the compiled container, with the registered mocks wired in.
     *
     * @template T of object
     *
     * @param class-string<T> $providerClass
     *
     * @return T
     */
    public function getProvider(string $providerClass): object
    {
        $provider = $this->resolveService($providerClass);

        return Stub::make($provider, [
            'provide' => function (Operation $operation, array $uriVariables, array $context) use ($provider) {
                if (is_array($context) && !$context) {
                    $context = $this->getContext()->toArray();
                }

                return $provider->provide($operation, $uriVariables, $context);
            },
        ]);
    }

    /**
     * Get a relationship resolver from the compiled container, with the registered mocks wired in.
     * Drive it through its public
     * {@see \Spryker\ApiPlatform\Relationship\AbstractRelationshipResolver::resolve()}.
     *
     * @template T of object
     *
     * @param class-string<T> $relationshipResolverClass
     *
     * @return T
     */
    public function getRelationshipResolver(string $relationshipResolverClass): object
    {
        return $this->resolveService($relationshipResolverClass);
    }

    /**
     * Denormalize data into a resource using PropertyNormalizer.
     *
     * @param array<string, mixed> $data
     */
    public function getResource(string $resourceClass, array $data = []): object
    {
        $normalizer = new PropertyNormalizer();

        return $normalizer->denormalize($data, $resourceClass);
    }

    public function getPostOperation(string $class = '', ?string $uriTemplate = null): Post
    {
        return OperationFactory::createPost($class, $uriTemplate);
    }

    public function getGetOperation(string $class = '', ?string $uriTemplate = null): Get
    {
        return OperationFactory::createGet($class, $uriTemplate);
    }

    public function getPatchOperation(string $class = '', ?string $uriTemplate = null): Patch
    {
        return OperationFactory::createPatch($class, $uriTemplate);
    }

    public function getDeleteOperation(string $class = '', ?string $uriTemplate = null): Delete
    {
        return OperationFactory::createDelete($class, $uriTemplate);
    }

    public function getGetCollectionOperation(string $class = '', ?string $uriTemplate = null): GetCollection
    {
        return OperationFactory::createGetCollection($class, $uriTemplate);
    }

    public function getContext(array $seedData = []): ApiContext
    {
        return (new ApiContext())->getContext($seedData);
    }

    /**
     * The default authenticated customer, built (non-persisting) by the core CustomerDataHelper —
     * the API test lanes carry no customer code of their own.
     */
    protected function getDefaultAuthenticatedCustomer(): CustomerTransfer
    {
        /** @var \SprykerTest\Shared\Customer\Helper\CustomerDataHelper $customerDataHelper */
        $customerDataHelper = $this->getModule('\\' . CustomerDataHelper::class);

        return $customerDataHelper->haveCustomerTransfer();
    }

    /**
     * Context for a provider/processor call that reads the authenticated customer off the request
     * (`AbstractStorefrontProvider::getCustomer()`). Pass no customer to authenticate as a
     * DataBuilder-generated default customer.
     *
     * @return array<string, mixed>
     */
    public function getAuthenticatedContext(?CustomerTransfer $customerTransfer = null): array
    {
        return $this->getContext()
            ->withCustomer($customerTransfer ?? $this->getDefaultAuthenticatedCustomer())
            ->toArray();
    }

    /**
     * Authenticated context whose request body carries the given JSON:API `data.attributes`, so a
     * processor that reads the raw request body (e.g.
     * {@see \Spryker\Glue\WishlistsRestApi\Api\Storefront\Processor\WishlistItemsStorefrontProcessor})
     * sees them.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public function getAuthenticatedContextWithAttributes(array $attributes, ?CustomerTransfer $customerTransfer = null): array
    {
        return $this->getAuthenticatedContextWithRawContent(
            json_encode(['data' => ['attributes' => $attributes]], JSON_THROW_ON_ERROR),
            $customerTransfer,
        );
    }

    /**
     * Authenticated context whose request body is the given raw string — for exercising a
     * processor's request-parsing branches (empty / non-JSON / non-array bodies).
     *
     * @return array<string, mixed>
     */
    public function getAuthenticatedContextWithRawContent(string $content, ?CustomerTransfer $customerTransfer = null): array
    {
        return $this->getContext(['request' => new Request([], [], [], [], [], [], $content)])
            ->withCustomer($customerTransfer ?? $this->getDefaultAuthenticatedCustomer())
            ->toArray();
    }

    /**
     * Builds an empty test double for a client interface with the given methods stubbed, e.g.
     * `createClientStub(WishlistClientInterface::class, ['getWishlistCollection' => $collection])`.
     * Replaces the per-test `createXClientStub()` helpers each fast-lane test used to repeat.
     *
     * @template T of object
     *
     * @param class-string<T> $clientInterfaceClassName
     * @param array<string, mixed> $methods
     *
     * @return T
     */
    public function createClientStub(string $clientInterfaceClassName, array $methods = []): object
    {
        return Stub::makeEmpty($clientInterfaceClassName, $methods);
    }

    /**
     * Asserts that running `$act` throws a {@see GlueApiException} whose HTTP status equals
     * `$expectedStatusCode`. Keeps the status-code assertion (which PHPUnit's `expectException`
     * cannot make — the status lives on `HttpException::getStatusCode()`, not `getCode()`) while
     * removing the try/catch boilerplate each error test used to repeat.
     */
    public function assertThrowsGlueApiExceptionWithStatus(int $expectedStatusCode, callable $act): void
    {
        try {
            $act();
        } catch (GlueApiException $glueApiException) {
            Assert::assertSame(
                $expectedStatusCode,
                $glueApiException->getStatusCode(),
                sprintf(
                    'Expected a %s with HTTP status %d, got %d.',
                    GlueApiException::class,
                    $expectedStatusCode,
                    $glueApiException->getStatusCode(),
                ),
            );

            return;
        }

        Assert::fail(sprintf(
            'Expected a %s with HTTP status %d, but no exception was thrown.',
            GlueApiException::class,
            $expectedStatusCode,
        ));
    }

    /**
     * Resolves the processor/provider from the container, with the registered mocks wired in, so
     * the test covers the real service wiring. Anything a test does not stub is the real service.
     *
     * @template T of object
     *
     * @param class-string<T> $serviceClass
     *
     * @return T
     */
    protected function resolveService(string $serviceClass): object
    {
        $this->assertTestCaseAvailable($serviceClass);

        return $this->testCase->getService($serviceClass);
    }

    /**
     * @phpstan-assert !null $this->testCase
     */
    protected function assertTestCaseAvailable(string $serviceClass): void
    {
        if ($this->testCase !== null) {
            return;
        }

        Assert::fail(sprintf(
            'Cannot resolve %s: the test class has to extend %s so the container is available. '
            . 'Register the collaborators the test stubs with setService() before resolving.',
            $serviceClass,
            AbstractApiTestCase::class,
        ));
    }
}
