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
use SprykerTest\ApiPlatform\DependencyInjection\Compiler\MockServiceCompilerPass;
use SprykerTest\Shared\Testify\Helper\BootstrapHelper;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Container as SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;
use TypeError;

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

    protected const string SERVICE_ID_REQUEST_STACK = 'request_stack';

    /**
     * @var array<\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface>
     */
    protected array $testCompilerPasses = [];

    /**
     * @var array<string, object>
     */
    protected array $serviceMocks = [];

    /**
     * @var array<string, string>
     */
    protected array $defaultRequestHeaders = [];

    /**
     * @var array<string, \Symfony\Component\HttpKernel\KernelInterface>
     */
    protected static array $sharedKernels = [];

    protected static int $bootCount = 0;

    protected const string MEDIA_TYPE_JSON_LD = 'application/ld+json';

    protected const string MEDIA_TYPE_JSON_API = 'application/vnd.api+json';

    protected static function getModuleRoot(): string
    {
        $dataDir = rtrim(codecept_data_dir(), DIRECTORY_SEPARATOR);

        // Ensure the _data directory exists before using realpath
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $dataDir = realpath($dataDir);

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
     */
    protected function getProjectRoot(): string
    {
        return defined('APPLICATION_ROOT_DIR')
            ? APPLICATION_ROOT_DIR
            : dirname(codecept_data_dir(), 3);
    }

    /**
     * Returns the number of real kernel boots performed since the last reset.
     */
    public static function getBootCount(): int
    {
        return static::$bootCount;
    }

    /**
     * Shuts down and clears every shared kernel and resets the boot counter. Called at suite end by
     * {@see \SprykerTest\ApiPlatform\Helper\ApiPlatformHelper::_afterSuite()}.
     */
    public static function resetSharedKernel(): void
    {
        foreach (static::$sharedKernels as $kernel) {
            $kernel->shutdown();
        }

        static::$sharedKernels = [];
        static::$bootCount = 0;
    }

    protected function bootKernel(): KernelInterface
    {
        if (TestModeConfiguration::isBootOnce() && isset(static::$sharedKernels[static::API_TYPE])) {
            $this->kernel = static::$sharedKernels[static::API_TYPE];
            $this->booted = true;

            $this->registerKernelService();
            $this->applyServiceMocks();

            return $this->kernel;
        }

        $this->ensureKernelShutdown();

        $kernel = $this->createKernel();
        $kernel->boot();
        static::$bootCount++;

        $this->kernel = $kernel;
        $this->booted = true;

        $this->registerKernelService();
        $this->applyServiceMocks();

        if (TestModeConfiguration::isBootOnce()) {
            static::$sharedKernels[static::API_TYPE] = $kernel;
        }

        return $this->kernel;
    }

    /**
     * Sets every registered service mock on the booted container.
     *
     * Bind through the test container where there is one: most Spryker services are private, and
     * only TestContainer::set() knows to put those into the container's privates. That is what lets
     * a container compiled with no knowledge of a test's mocks still accept them. Without it (a
     * kernel whose container is not in test mode) the real container still takes public and
     * synthetic ids, and refuses private ones with an explicit message.
     *
     * Boot-once suites ONLY, because there are two mock-binding mechanisms and they must not both
     * run for one service. {@see getService()} declares the mocked ids to
     * {@see \SprykerTest\ApiPlatform\DependencyInjection\Compiler\MockServiceCompilerPass} and binds
     * them itself, which is what a per-method-boot suite (every `mode: core` provider/processor
     * test) uses. Binding here as well would try to replace a service that path has already
     * initialized, which Symfony refuses outright. A boot-once suite cannot use that mechanism —
     * its kernel is compiled once, before most methods register anything — so it binds here, after
     * each boot or shared-kernel reuse.
     */
    protected function applyServiceMocks(): void
    {
        if ($this->serviceMocks === [] || !TestModeConfiguration::isBootOnce()) {
            return;
        }

        $container = $this->kernel->getContainer();
        $mockTarget = $container->has('test.service_container')
            ? $container->get('test.service_container')
            : $container;

        foreach ($this->serviceMocks as $serviceId => $mock) {
            $mockTarget->set($serviceId, $mock);
        }
    }

    /**
     * Registers the kernel as the `kernel` service on the booted container.
     *
     * Symfony's own Kernel::boot() does this; the Spryker container-delegator boot path used here
     * does not, which leaves the `kernel` service synthetic-and-unset. The API Platform router needs
     * it to generate resource IRIs, so any serialized success (2xx) response — as opposed to the
     * pre-serialization 4xx short-circuits — fails without this. Guarded on `has('kernel')`, so it
     * is safe to call repeatedly without overwriting an already-registered service.
     */
    protected function registerKernelService(): void
    {
        if ($this->kernel === null) {
            return;
        }

        // A stubbed container may violate KernelInterface::getContainer()'s non-nullable return
        // type; PHP surfaces that as a TypeError. Catch only that narrow case.
        try {
            $container = $this->kernel->getContainer();
        } catch (TypeError) {
            return;
        }

        if ($container->has('kernel')) {
            return;
        }

        $container->set('kernel', $this->kernel);
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
            if (!$this->booted) {
                return;
            }

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
     * The bundles the application under test registers.
     *
     * In project mode these are read from the application's own `config/<App>/bundles.php`, because
     * the set is not the same for every application and a missing bundle does not fail loudly. The
     * Backend application registers `SprykerKernelFeatureBundle` (plus the Falcon and Backoffice UI
     * bundles) on top of the Storefront set, and that bundle registers the Zed services a facade
     * pulls out of the container through `getService()` — without it the facade receives `null` and
     * the failure surfaces far from its cause, as a method call on null inside the facade.
     *
     * Core mode has no project config directory, so the minimal set stays the fallback there.
     *
     * @return array<class-string>
     */
    public static function getTestKernelBundles(): array
    {
        return static::getApplicationBundles() ?: static::getMinimalKernelBundles();
    }

    /**
     * @return array<class-string>
     */
    protected static function getMinimalKernelBundles(): array
    {
        return [
            FrameworkBundle::class,
            SecurityBundle::class,
            ApiPlatformBundle::class,
            SprykerApiPlatformBundle::class,
        ];
    }

    /**
     * Reads `config/<App>/bundles.php`, keeping the bundles enabled for `all` or for the test
     * environment. Returns an empty array when the file cannot be located, which is the signal to
     * fall back to the minimal set.
     *
     * @return array<class-string>
     */
    protected static function getApplicationBundles(): array
    {
        if (!TestModeConfiguration::isProjectMode() || !defined('APPLICATION_ROOT_DIR') || !defined('APPLICATION')) {
            return [];
        }

        $bundlesFile = sprintf(
            '%s/config/%s/bundles.php',
            APPLICATION_ROOT_DIR,
            static::toConfigDirectoryName((string)APPLICATION),
        );

        if (!is_file($bundlesFile)) {
            return [];
        }

        /** @var array<class-string, array<string, bool>> $declaredBundles */
        $declaredBundles = require $bundlesFile;

        $bundles = [];

        foreach ($declaredBundles as $bundleClass => $environments) {
            if (!class_exists($bundleClass)) {
                continue;
            }

            if (($environments['all'] ?? false) || ($environments['test'] ?? false)) {
                $bundles[] = $bundleClass;
            }
        }

        return $bundles;
    }

    /**
     * Mirrors the `APPLICATION` to `config/` directory mapping of
     * {@see \Spryker\Shared\Application\Kernel::getConfigDir()}, whose own converter is private:
     * `GLUE_BACKEND` becomes `GlueBackend`.
     */
    protected static function toConfigDirectoryName(string $application): string
    {
        $separator = str_contains($application, '_') ? '_' : '.';

        $fragments = array_map(
            static fn (string $fragment): string => ucfirst(strtolower($fragment)),
            explode($separator, $application),
        );

        return implode('', $fragments);
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
            'security' => [
                'password_hashers' => [
                    'SprykerTest\ApiPlatform\Test\Security\Customer' => 'plaintext',
                ],
                'providers' => [
                    'test_customer_provider' => [
                        'id' => 'SprykerTest\ApiPlatform\Test\Security\CustomerProvider',
                    ],
                ],
                'firewalls' => [
                    'dev' => [
                        'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                        'security' => false,
                    ],
                    'main' => [
                        'lazy' => true,
                        'provider' => 'test_customer_provider',
                        'custom_authenticators' => [
                            'SprykerTest\ApiPlatform\Test\Security\TokenAuthenticator',
                        ],
                    ],
                ],
                'access_control' => [
                    ['path' => '^/', 'roles' => 'PUBLIC_ACCESS'],
                ],
            ],
            'api_platform' => [
                'doctrine' => ['enabled' => false],
                'doctrine_mongodb_odm' => ['enabled' => false],
                'mapping' => ['paths' => $resourcePaths],
                'formats' => [
                    'jsonapi' => ['mime_types' => ['application/vnd.api+json']],
                    'jsonld' => ['mime_types' => ['application/ld+json']],
                ],
            ],
        ];
    }

    public function setService(string $serviceId, object $service): void
    {
        $this->serviceMocks[$serviceId] = $service;
    }

    /**
     * Registers a header that {@see handleApiRequest()} applies to every request until the test
     * ends. Per-call headers still take precedence. Used by test-support helpers (e.g.
     * {@see \SprykerTest\ApiPlatform\Helper\BackendApiLoginHelper}) that need to authenticate the
     * next request without the test having to pass the header itself.
     */
    public function addDefaultRequestHeader(string $name, string $value): void
    {
        $this->defaultRequestHeaders[$name] = $value;
    }

    public function getService(string $serviceClass): object
    {
        $this->addTestCompilerPass(
            new MockServiceCompilerPass(array_keys($this->serviceMocks)),
        );

        $testContainer = $this->getTestContainer();
        $realContainer = $this->getTestKernel()->getContainer();

        foreach ($this->serviceMocks as $serviceId => $mock) {
            $realContainer->set($serviceId, $mock);
        }

        return $testContainer->get($serviceClass);
    }

    public function addTestCompilerPass(CompilerPassInterface $pass): void
    {
        $this->testCompilerPasses[] = $pass;
    }

    public function getTestContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    public function getTestKernel(): KernelInterface
    {
        if (!$this->booted) {
            $this->bootKernel();
        }

        return $this->kernel;
    }

    protected function createKernel(): KernelInterface
    {
        $debug = TestModeConfiguration::isDebug();

        $container = new ContainerProxy(['test' => true, 'debug' => $debug, 'environment' => 'test']);

        foreach (BootstrapHelper::getApplicationPlugins() as $applicationPlugin) {
            $container = $applicationPlugin->provide($container);
        }

        $kernel = new ApiTestKernel($container, $debug);

        $kernel->addBundles($this->getTestKernelBundles());

        $resourcePaths = $this->getApiPlatformResourcePaths();

        $kernel->setResourcePaths($resourcePaths);
        $kernel->setApiType(static::API_TYPE);

        $kernel->addBundleConfigurations($this->getTestKernelBundleConfigurations($resourcePaths));

        foreach ($this->testCompilerPasses as $pass) {
            $kernel->addTestCompilerPass($pass);
        }

        return $kernel;
    }

    /**
     * Dispatches a request straight through the HTTP kernel and returns the Response.
     *
     * This is the project-mode contract-test entry point: unlike createClient(), it does not require
     * the `test.service_container` (which the real project config does not expose), so it works
     * against the full pre-generated resource set. A relative `$uri` is resolved against the suite's
     * default base URL.
     *
     * @param array<string, string> $headers
     */
    protected function handleApiRequest(string $method, string $uri, ?string $content = null, array $headers = []): Response
    {
        $kernel = $this->getTestKernel();

        if (str_starts_with($uri, '/')) {
            $uri = rtrim(static::DEFAULT_BASE_URL, '/') . $uri;
        }

        $request = Request::create($uri, $method, [], [], [], [], $content);
        $request->headers->set('Accept', static::DEFAULT_ACCEPT_HEADER);

        if ($content !== null) {
            $request->headers->set('Content-Type', static::DEFAULT_CONTENT_TYPE_HEADER);
        }

        // Per-call headers win over the defaults registered by helpers (e.g. the Authorization
        // header set by BackendApiLoginHelper::actingAsUser()).
        foreach (array_merge($this->defaultRequestHeaders, $headers) as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $kernel->handle($request);
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

    /**
     * `Container::reset()` drops every service instance, so the next method rebuilds them — but not
     * everything on the request path is rebuilt with it. `http_kernel` takes its event dispatcher and
     * controller resolver from the `ContainerDelegator` singleton, which under
     * `reuseApplicationContainer` outlives the reset and keeps handing out the listeners and
     * controllers built for the *first* generation of the container. The rebuilt `http_kernel` then
     * pushes the request onto a brand-new `RequestStack` that those stale collaborators cannot see.
     *
     * Anything reading the current request from the container therefore goes blind from the second
     * test method onwards — most visibly a security voter behind a `security:` expression, which
     * silently denies because it cannot resolve the uri variable it compares against.
     *
     * A RequestStack is a per-process singleton in this lane and holds no test-visible state between
     * methods (the kernel pushes and pops symmetrically), so carrying the same instance across the
     * reset keeps every generation looking at one stack.
     */
    protected function resetContainerKeepingRequestStack(ResetInterface&ContainerInterface $container): void
    {
        $requestStack = $container->has(static::SERVICE_ID_REQUEST_STACK)
            ? $container->get(static::SERVICE_ID_REQUEST_STACK)
            : null;

        $container->reset();

        if ($requestStack instanceof RequestStack && $container instanceof SymfonyContainer) {
            $container->set(static::SERVICE_ID_REQUEST_STACK, $requestStack);
        }
    }

    protected function tearDown(): void
    {
        $this->_after();

        if (TestModeConfiguration::isBootOnce() && isset(static::$sharedKernels[static::API_TYPE])) {
            // Keep the shared kernel alive for the next method; only reset the container's stateful
            // services and clear this method's mocks.
            if ($this->kernel !== null) {
                $container = $this->kernel->getContainer();
                if ($container instanceof ResetInterface) {
                    $this->resetContainerKeepingRequestStack($container);
                }
            }

            $this->class = null;
            $this->testCompilerPasses = [];
            $this->serviceMocks = [];
            $this->defaultRequestHeaders = [];
            $this->booted = true; // kernel stays live for the next method

            parent::tearDown();

            return;
        }

        $this->ensureKernelShutdown();

        $this->resetContainerDelegatorUnlessReused();

        $this->class = null;
        $this->kernel = null;
        $this->booted = false;
        $this->testCompilerPasses = [];
        $this->serviceMocks = [];
        $this->defaultRequestHeaders = [];

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

    /**
     * Resets the ContainerDelegator singleton unless container reuse is enabled.
     *
     * When reuseApplicationContainer is on, the delegator survives between test methods so its
     * compiled application container can be reused.
     */
    protected function resetContainerDelegatorUnlessReused(): void
    {
        if (TestModeConfiguration::isReuseApplicationContainer()) {
            return;
        }

        static::resetContainerDelegator();
    }

    /**
     * Reuse is a within-suite concern (survives between test methods so the compiled application
     * container is not rebuilt per method); it must not survive the suite itself. Called
     * unconditionally from {@see \SprykerTest\ApiPlatform\Helper\ApiPlatformHelper::_afterSuite()}
     * so a stale, memoized service (e.g. an OAuth stub bound by a different module's suite) cannot
     * leak into the next suite's `ContainerDelegator::$resolvedServices` cache when both suites run
     * `reuseApplicationContainer` in the same `codecept run:filtered` process.
     */
    public static function resetContainerDelegator(): void
    {
        if (!class_exists(ContainerDelegator::class)) {
            return;
        }

        $reflectedProperty = new ReflectionProperty(
            ContainerDelegator::class,
            'instance',
        );
        $reflectedProperty->setValue(null);
    }
}
