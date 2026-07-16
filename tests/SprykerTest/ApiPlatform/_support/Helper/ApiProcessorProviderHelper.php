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
use SprykerTest\ApiPlatform\Test\AbstractApiTestCase;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

/**
 * Codeception helper for testing API Platform processors and providers
 * with mock services injected via synthetic container definitions.
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
     * Store a mock to be injected as a synthetic service.
     * Call before getProcessor/getProvider.
     */
    public function setService(string $serviceId, object $mock): void
    {
        $this->assertTestCaseAvailable();
        $this->testCase->setService($serviceId, $mock);
    }

    /**
     * Get a processor from the compiled container with mocks wired in.
     *
     * @template T of object
     *
     * @param class-string<T> $processorClass
     *
     * @return T
     */
    public function getProcessor(string $processorClass): object
    {
        $this->assertTestCaseAvailable();

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
     * Get a provider from the compiled container with mocks wired in.
     *
     * @template T of object
     *
     * @param class-string<T> $providerClass
     *
     * @return T
     */
    public function getProvider(string $providerClass): object
    {
        $this->assertTestCaseAvailable();

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
     * @template T of object
     *
     * @param class-string<T> $serviceClass
     *
     * @return T
     */
    protected function resolveService(string $serviceClass): object
    {
        return $this->testCase->getService($serviceClass);
    }

    protected function assertTestCaseAvailable(): void
    {
        if ($this->testCase === null) {
            $this->fail(
                sprintf(
                    'ApiProcessorProviderHelper requires the test class to extend %s.',
                    AbstractApiTestCase::class,
                ),
            );
        }
    }
}
