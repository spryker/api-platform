<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\ApiPlatform\Test;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use ApiPlatform\Symfony\Bundle\Test\ApiTestAssertionsTrait;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Codeception\ResultAggregator;
use Codeception\Test\Metadata;
use Codeception\Test\Unit;
use LogicException;
use ReflectionProperty;
use Spryker\ApiPlatform\SprykerApiPlatformBundle;
use Spryker\Service\Container\ContainerDelegator;
use Spryker\Shared\Kernel\Container\ContainerProxy;
use SprykerTest\ApiPlatform\Helper\ApiResourceGeneratorHelper;
use SprykerTest\Shared\Testify\Helper\BootstrapHelper;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Base test case for API-Platform functional tests.
 *
 * This class extends Codeception's Unit test and provides API Platform integration.
 * It handles actor injection, metadata management for Codeception tests, and provides
 * kernel management and API Platform assertion methods.
 *
 * Concrete test classes should extend BackendApiTestCase or StorefrontApiTestCase,
 * not this class directly.
 */
abstract class AbstractApiTestCase extends Unit
{
    use ApiTestAssertionsTrait;

    protected ?Metadata $metadata = null;

    protected ?string $class = null;

    protected ?KernelInterface $kernel = null;

    protected bool $booted = false;

    protected const string API_TYPE = 'undefined';

    protected const string MEDIA_TYPE_JSON_LD = 'application/ld+json';

    protected const string MEDIA_TYPE_JSON_API = 'application/vnd.api+json';

    protected const string MEDIA_TYPE_JSON = 'application/json';

    protected const string MEDIA_TYPE_XML = 'application/xml';

    protected const string MEDIA_TYPE_TEXT_XML = 'text/xml';

    protected const string MEDIA_TYPE_YAML = 'application/x-yaml';

    protected const string MEDIA_TYPE_CSV = 'text/csv';

    protected const string MEDIA_TYPE_JSON_HAL = 'application/hal+json';

    protected static ?ApiResourceGeneratorHelper $resourceHelper = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (TestModeConfiguration::isProjectMode()) {
            static::validateProjectModeResources();

            return;
        }

        static::$resourceHelper = new ApiResourceGeneratorHelper();
        $moduleRoot = static::getModuleRoot();

        static::$resourceHelper->cleanup($moduleRoot, static::API_TYPE);
        static::$resourceHelper->generate($moduleRoot, static::API_TYPE);
    }

    /**
     * Validates that API resources exist in project mode and prints informational message.
     */
    protected static function validateProjectModeResources(): void
    {
        $projectRoot = defined('APPLICATION_ROOT_DIR')
            ? APPLICATION_ROOT_DIR
            : dirname(codecept_data_dir(), 3);

        $resourcePath = sprintf('%s/src/Generated/Api/%s', $projectRoot, static::API_TYPE);

        echo sprintf(
            "\n[ApiPlatformTest] Running in PROJECT mode for %s API.\n",
            static::API_TYPE,
        );

        if (!is_dir($resourcePath) || count(glob($resourcePath . '/*.php')) === 0) {
            echo sprintf(
                "[ApiPlatformTest] WARNING: No resources found at %s\n" .
                "[ApiPlatformTest] Generate resources with: vendor/bin/console api:generate %s\n\n",
                $resourcePath,
                strtolower(static::API_TYPE),
            );
        } else {
            echo sprintf(
                "[ApiPlatformTest] Resources found at %s\n\n",
                $resourcePath,
            );
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (TestModeConfiguration::isCoreMode() && static::$resourceHelper !== null) {
            static::$resourceHelper->cleanup(static::getModuleRoot(), static::API_TYPE);
        }

        parent::tearDownAfterClass();
    }

    protected static function getModuleRoot(): string
    {
        $dataDir = realpath(rtrim(codecept_data_dir(), DIRECTORY_SEPARATOR));

        return dirname($dataDir, 2);
    }

    /**
     * @return array<string>
     */
    protected function getApiPlatformResourcePaths(): array
    {
        if (TestModeConfiguration::isProjectMode()) {
            return $this->getProjectModeResourcePaths();
        }

        return $this->getCoreModeResourcePaths();
    }

    /**
     * Returns resource paths for project mode (src/Generated/Api/{ApiType}).
     *
     * @return array<string>
     */
    protected function getProjectModeResourcePaths(): array
    {
        $projectRoot = $this->getProjectRoot();

        return [sprintf('%s/src/Generated/Api/%s', $projectRoot, static::API_TYPE)];
    }

    /**
     * Returns resource paths for core mode (tests/_data/Api/{ApiType}).
     *
     * @return array<string>
     */
    protected function getCoreModeResourcePaths(): array
    {
        $modulePathFragments = explode(DIRECTORY_SEPARATOR, codecept_data_dir());

        $testsDirPosition = array_search('tests', $modulePathFragments);

        $modulePathFragments = array_slice($modulePathFragments, 0, $testsDirPosition);
        $moduleRoot = implode(DIRECTORY_SEPARATOR, $modulePathFragments);

        return [sprintf('%s/tests/_data/Api/%s', $moduleRoot, static::API_TYPE)];
    }

    /**
     * Returns the project root directory.
     *
     * @return string
     */
    protected function getProjectRoot(): string
    {
        return defined('APPLICATION_ROOT_DIR')
            ? APPLICATION_ROOT_DIR
            : dirname(codecept_data_dir(), 3);
    }

    /**
     * Get all supported Accept header values.
     *
     * @return array<string>
     */
    protected function getSupportedAcceptTypes(): array
    {
        return [
            static::MEDIA_TYPE_JSON_LD,
            static::MEDIA_TYPE_JSON_API,
            static::MEDIA_TYPE_JSON,
            static::MEDIA_TYPE_XML,
            static::MEDIA_TYPE_TEXT_XML,
            static::MEDIA_TYPE_YAML,
            static::MEDIA_TYPE_CSV,
            static::MEDIA_TYPE_JSON_HAL,
        ];
    }

    /**
     * Get all supported Content-Type header values.
     *
     * @return array<string>
     */
    protected function getSupportedContentTypes(): array
    {
        return [
            static::MEDIA_TYPE_JSON_LD,
            static::MEDIA_TYPE_JSON_API,
            static::MEDIA_TYPE_JSON,
            static::MEDIA_TYPE_XML,
            static::MEDIA_TYPE_YAML,
            static::MEDIA_TYPE_CSV,
            static::MEDIA_TYPE_JSON_HAL,
        ];
    }

    protected function bootKernel(): KernelInterface
    {
        $this->ensureKernelShutdown();

        $kernel = $this->createKernel();
        $kernel->boot();

        $this->kernel = $kernel;
        $this->booted = true;

        return $this->kernel;
    }

    protected function getContainer(): ContainerInterface
    {
        if (!$this->booted) {
            $this->bootKernel();
        }

        try {
            return $this->kernel->getContainer()->get('test.service_container');
        } catch (ServiceNotFoundException $e) {
            throw new LogicException('Could not find service "test.service_container". Try updating the "framework.test" config to "true".', 0, $e);
        }
    }

    /**
     * Shuts the kernel down if it was used in the test.
     */
    protected function ensureKernelShutdown(): void
    {
        if ($this->kernel !== null) {
            $this->kernel->boot();

            $container = $this->kernel->getContainer();

            $this->kernel->shutdown();
            $this->booted = false;

            if ($container instanceof ResetInterface) {
                $container->reset();
            }
        }
    }

    /**
     * Generate the IRI of a resource item.
     */
    protected function getIriFromResource(object $resource): ?string
    {
        /** @var \ApiPlatform\Metadata\IriConverterInterface $iriConverter */
        $iriConverter = $this->getContainer()->get('api_platform.iri_converter');

        return $iriConverter->getIriFromResource($resource);
    }

    public function getResultAggregator(): ResultAggregator
    {
        throw new LogicException('This method should not be called, TestCaseWrapper class must be used instead');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultClientOptions(): array
    {
        return [
            'base_uri' => static::DEFAULT_BASE_URL,
            'headers' => [
                'Accept' => static::DEFAULT_ACCEPT_HEADER,
                'Content-Type' => static::DEFAULT_CONTENT_TYPE_HEADER,
            ],
        ];
    }

    /**
     * Returns the bundle classes to register in the test kernel.
     *
     * @return array<class-string>
     */
    public static function getTestKernelBundles(): array
    {
        return [
            FrameworkBundle::class,
            ApiPlatformBundle::class,
            SprykerApiPlatformBundle::class,
        ];
    }

    /**
     * Returns the bundle configurations for the test kernel.
     *
     * @param array<string> $resourcePaths
     *
     * @return array<string, mixed>
     */
    public static function getTestKernelBundleConfigurations(array $resourcePaths): array
    {
        return [
            'framework' => [
                'secret' => 'test_secret',
                'test' => true,
                'http_method_override' => false,
                'router' => [
                    'utf8' => true,
                    'resource' => 'api_platform',
                    'type' => 'api_platform',
                ],
            ],
            'api_platform' => [
                'doctrine' => ['enabled' => false],
                'doctrine_mongodb_odm' => ['enabled' => false],
                'mapping' => ['paths' => $resourcePaths],
            ],
        ];
    }

    protected function createKernel(): KernelInterface
    {
        $container = new ContainerProxy(['test' => true, 'debug' => true, 'environment' => 'test']);

        foreach (BootstrapHelper::getApplicationPlugins() as $applicationPlugin) {
            $container = $applicationPlugin->provide($container);
        }

        $kernel = new ApiTestKernel($container, true);

        $kernel->addBundles($this->getTestKernelBundles());

        $resourcePaths = $this->getApiPlatformResourcePaths();

        $kernel->setResourcePaths($resourcePaths);
        $kernel->setApiType(static::API_TYPE);

        $kernel->addBundleConfigurations($this->getTestKernelBundleConfigurations($resourcePaths));

        return $kernel;
    }

    protected function createClient(): Client
    {
        if (!$this->booted) {
            $this->bootKernel();
        }

        try {
            /** @var \ApiPlatform\Symfony\Bundle\Test\Client $client */
            $client = $this->getContainer()->get('test.api_platform.client');
        } catch (ServiceNotFoundException) {
            throw new LogicException('You cannot create the client used in functional tests if the "framework.test" config is not set to true.');
        }

        $client->setDefaultOptions(array_merge($this->getDefaultClientOptions()));

        $this->getHttpClient($client);
        $this->getClient($client->getKernelBrowser());

        return $client;
    }

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        defined('APPLICATION') || define('APPLICATION', 'GLUE');

        parent::setUp();

        $this->_before();
    }

    protected function tearDown(): void
    {
        $this->_after();

        $this->ensureKernelShutdown();

        $this->resetContainerDelegator();

        $this->class = null;
        $this->kernel = null;
        $this->booted = false;

        parent::tearDown();
    }

    protected function _before(): void
    {
    }

    protected function _after(): void
    {
    }

    /**
     * Assert that the response contains a validation violation for the specified property.
     *
     * @param string $propertyPath
     *
     * @return void
     */
    protected function assertResponseHasViolationForProperty(string $propertyPath): void
    {
        $violations = $this->getViolationsFromResponse();
        $actualPropertyPaths = array_column($violations, 'propertyPath');

        $this->assertContains(
            $propertyPath,
            $actualPropertyPaths,
            sprintf('Expected violation for property "%s" but it was not found. Found violations for: %s', $propertyPath, implode(', ', $actualPropertyPaths)),
        );
    }

    /**
     * Assert that the response contains validation violations for all specified properties.
     *
     * @param array<string> $propertyPaths
     *
     * @return void
     */
    protected function assertResponseHasViolations(array $propertyPaths): void
    {
        foreach ($propertyPaths as $propertyPath) {
            $this->assertResponseHasViolationForProperty($propertyPath);
        }
    }

    /**
     * Extract violations from the response.
     *
     * @return array<array<string, mixed>>
     */
    protected function getViolationsFromResponse(): array
    {
        $response = $this->getClient()->getResponse();
        $content = json_decode($response->getContent(false), true);

        return $content['violations'] ?? [];
    }

    protected function resetContainerDelegator(): void
    {
        if (!class_exists(ContainerDelegator::class)) {
            return;
        }

        $reflectedProperty = new ReflectionProperty(
            ContainerDelegator::class,
            'instance',
        );
        $reflectedProperty->setAccessible(true);
        $reflectedProperty->setValue(null);
    }
}
