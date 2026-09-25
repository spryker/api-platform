<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\ApiPlatform\Test;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use ApiPlatform\Symfony\Bundle\Test\ApiTestAssertionsTrait;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Codeception\ResultAggregator;
use Codeception\Test\Metadata;
use Codeception\Test\Unit;
use LogicException;
use ReflectionProperty;
use Spryker\ApiPlatform\Contract\Coverage\AnnotationCollector;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageRunner;
use Spryker\ApiPlatform\Contract\Coverage\OperationCoverageRecorder;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeRecorder;
use Spryker\ApiPlatform\Contract\Coverage\UriTemplateNormalizer;
use Spryker\ApiPlatform\Contract\Envelope\JsonApiEnvelopeRecorder;
use Spryker\ApiPlatform\SprykerApiPlatformBundle;
use Spryker\Service\Container\ContainerDelegator;
use Spryker\Shared\Kernel\Container\ContainerProxy;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use SprykerTest\Shared\Testify\Helper\BootstrapHelper;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Container as SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use TypeError;

/**
 * Base test case for API-Platform functional tests.
 *
 * Extends Codeception's Unit test with API Platform integration: actor injection, Codeception
 * metadata management, kernel lifecycle and the API Platform assertion methods.
 *
 * Do not extend this directly — extend a concrete base class ({@see StorefrontApiTestCase} /
 * {@see BackendApiTestCase}, which pick the `API_TYPE`) or, for boot-free logic tests,
 * {@see \Codeception\Test\Unit}. Which lane a test belongs in, and its cost, is documented in
 * `src/Spryker/ApiPlatform/tests/README.md`.
 */
abstract class AbstractApiTestCase extends Unit
{
    use JsonApiResponseAssertionsTrait;

    use ApiTestAssertionsTrait;

    protected ?Metadata $metadata = null;

    protected ?string $class = null;

    protected ?KernelInterface $kernel = null;

    protected bool $booted = false;

    protected const string API_TYPE = 'undefined';

    /**
     * Only the boot-lifecycle probes set this: they boot partial kernels on purpose and never
     * dispatch a request, so they have no coverage declaration to verify.
     */
    protected const bool ALLOWS_MISSING_OPERATION_RECORDING = false;

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
     * Below Symfony's RouterListener (32), which populates `_api_operation_name`, and above the
     * firewall (8), so a request the firewall rejects is recorded too.
     */
    protected const int OPERATION_RECORDING_LISTENER_PRIORITY = 16;

    protected const string API_OPERATION_NAME_ATTRIBUTE = '_api_operation_name';

    protected const string API_OPERATION_ATTRIBUTE = '_api_operation';

    /**
     * Operations the currently running test method declares it covers, read from its
     * {@see \Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation} attributes. Empty for
     * non-annotated methods, which switches the whole recording/verification path off.
     *
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected array $declaredOperations = [];

    protected ?OperationCoverageRecorder $operationRecorder = null;

    /**
     * The recorder of the currently running test method. Static because under `bootOnce` a single
     * kernel — and thus a single request listener — is shared across every method's fresh test
     * instance; the listener records into whichever method is active.
     */
    protected static ?OperationCoverageRecorder $activeOperationRecorder = null;

    protected ?JsonApiEnvelopeRecorder $envelopeRecorder = null;

    /**
     * Static for the same reason {@see AbstractApiTestCase::$activeOperationRecorder} is.
     */
    protected static ?JsonApiEnvelopeRecorder $activeEnvelopeRecorder = null;

    /**
     * Records the response attribute paths the current method asserted on. Not static: it is filled
     * by the assertion helpers the test body calls directly, not by a kernel listener, so there is
     * no shared-kernel indirection to bridge.
     */
    protected ?ResponseAttributeRecorder $responseAttributeRecorder = null;

    /**
     * Route-name → [verb, canonical uriTemplate] map per booted kernel, keyed by kernel object id.
     *
     * @var array<int, array<string, array{0: string, 1: string}>>
     */
    protected static array $operationRouteMaps = [];

    /**
     * Keyed by API type, like {@see AbstractApiTestCase::$sharedKernels}: each type reflects its own
     * generated resource set, so one shared entry would serve a Backend case the Storefront truth.
     *
     * @var array<string, array<string, array<int>>>
     */
    protected static array $schemaDeclaredResponses = [];

    /**
     * @var array<string, array<string, array<string>>>
     */
    protected static array $schemaResponseAttributes = [];

    /**
     * @var array<string, array<string>>
     */
    protected static array $schemaResourceShortNames = [];

    /**
     * @var array<string, array<string>>
     */
    protected static array $schemaIdentifierDeclaringResourceShortNames = [];

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
        static::$operationRouteMaps = [];
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
        $this->registerOperationRecordingListener($kernel);
        $this->applyServiceMocks();

        if (TestModeConfiguration::isBootOnce()) {
            static::$sharedKernels[static::API_TYPE] = $kernel;
        }

        return $this->kernel;
    }

    /**
     * Recording is what turns a `#[CoversApiOperation]` declaration into a verified fact: without
     * it {@see AbstractApiTestCase::assertPostConditions()} has nothing to check a declaration
     * against and every coverage assertion of the case passes vacuously. A kernel that cannot carry
     * the listener therefore fails the test, except for the boot-lifecycle probes, which boot
     * deliberately partial kernels and dispatch no request.
     *
     * @throws \LogicException
     */
    protected function assertOperationRecordingOptional(string $reason, ?Throwable $cause = null): void
    {
        if (static::ALLOWS_MISSING_OPERATION_RECORDING) {
            return;
        }

        throw new LogicException(
            sprintf(
                'Operation recording could not be installed for %s because %s, so its coverage '
                . 'declarations could not be verified.',
                static::class,
                $reason,
            ),
            0,
            $cause,
        );
    }

    /**
     * Installs a kernel.request listener that records the operation each request matches, so the
     * runtime verifier ({@see assertPostConditions()}) can prove every declared operation was
     * actually exercised. Registered once per freshly booted kernel; under `bootOnce` the listener
     * outlives individual methods and records into the active method's recorder.
     */
    protected function registerOperationRecordingListener(KernelInterface $kernel): void
    {
        try {
            $container = $kernel->getContainer();
            $dispatcher = $container->has('event_dispatcher') ? $container->get('event_dispatcher') : null;
        } catch (Throwable $throwable) {
            $this->assertOperationRecordingOptional('the kernel exposes no container', $throwable);

            return;
        }

        if (!$dispatcher instanceof EventDispatcherInterface) {
            $this->assertOperationRecordingOptional('the container carries no event dispatcher');

            return;
        }

        $dispatcher->addListener(
            KernelEvents::REQUEST,
            static function (RequestEvent $event) use ($kernel): void {
                $recorder = static::$activeOperationRecorder;
                if ($recorder === null) {
                    return;
                }

                $routeName = $event->getRequest()->attributes->get(static::API_OPERATION_NAME_ATTRIBUTE);
                if (!is_string($routeName)) {
                    return;
                }

                $routeMap = static::operationRouteMap($kernel);
                if (!isset($routeMap[$routeName])) {
                    return;
                }

                [$verb, $uriTemplate] = $routeMap[$routeName];
                $recorder->record($verb, $uriTemplate);
            },
            static::OPERATION_RECORDING_LISTENER_PRIORITY,
        );

        $dispatcher->addListener(
            KernelEvents::RESPONSE,
            static function (ResponseEvent $event) use ($kernel): void {
                $recorder = static::$activeOperationRecorder;
                if ($recorder === null) {
                    return;
                }

                $routeName = $event->getRequest()->attributes->get(static::API_OPERATION_NAME_ATTRIBUTE);
                if (!is_string($routeName)) {
                    return;
                }

                $routeMap = static::operationRouteMap($kernel);
                if (!isset($routeMap[$routeName])) {
                    return;
                }

                [$verb, $uriTemplate] = $routeMap[$routeName];
                $recorder->recordResponse($verb, $uriTemplate, $event->getResponse()->getStatusCode());
            },
        );

        $dispatcher->addListener(
            KernelEvents::RESPONSE,
            static function (ResponseEvent $event) use ($kernel): void {
                $envelopeRecorder = static::$activeEnvelopeRecorder;
                if ($envelopeRecorder === null) {
                    return;
                }

                $routeName = $event->getRequest()->attributes->get(static::API_OPERATION_NAME_ATTRIBUTE);
                if (!is_string($routeName)) {
                    return;
                }

                $routeMap = static::operationRouteMap($kernel);
                if (!isset($routeMap[$routeName])) {
                    return;
                }

                $operation = $event->getRequest()->attributes->get(static::API_OPERATION_ATTRIBUTE);
                if (!$operation instanceof Operation) {
                    return;
                }

                $shortName = (string)$operation->getShortName();
                if ($shortName === '') {
                    return;
                }

                [$verb, $uriTemplate] = $routeMap[$routeName];

                $envelopeRecorder->record(
                    new ApiOperation($verb, $uriTemplate),
                    $event->getResponse(),
                    $shortName,
                );
            },
        );
    }

    /**
     * The coverage runner for this case's API type, so a Backend case reflects the resources under
     * `Generated\Api\Backend` and a Storefront case those under `Generated\Api\Storefront`.
     */
    protected function createContractCoverageRunner(): ContractCoverageRunner
    {
        return ContractCoverageFactory::createContractCoverageRunner(static::API_TYPE);
    }

    /**
     * The schema-declared statuses of every generated resource. Static because the reflection sweep
     * over the whole generated resource set is process-wide constant, and re-running it per test
     * method would dominate the runtime of an annotated suite.
     *
     * @return array<string, array<int>>
     */
    protected function schemaDeclaredResponses(): array
    {
        if (!isset(static::$schemaDeclaredResponses[static::API_TYPE])) {
            static::$schemaDeclaredResponses[static::API_TYPE] = $this->createContractCoverageRunner()
                ->declaredResponses($this->getProjectRoot());
        }

        return static::$schemaDeclaredResponses[static::API_TYPE];
    }

    /**
     * The schema-derived response attribute paths the given operations must carry, unioned and
     * de-duplicated. Static for the same reason {@see AbstractApiTestCase::schemaDeclaredResponses()}
     * is: one reflection sweep per process.
     *
     * @param array<string> $dispatchKeys
     *
     * @return array<string>
     */
    protected function schemaResponseAttributePaths(array $dispatchKeys): array
    {
        if (!isset(static::$schemaResponseAttributes[static::API_TYPE])) {
            static::$schemaResponseAttributes[static::API_TYPE] = $this->createContractCoverageRunner()
                ->responseAttributes($this->getProjectRoot());
        }

        $paths = [];
        foreach ($dispatchKeys as $dispatchKey) {
            $paths = array_merge($paths, static::$schemaResponseAttributes[static::API_TYPE][$dispatchKey] ?? []);
        }

        return array_values(array_unique($paths));
    }

    /**
     * The dispatch keys of the current method's success declarations — the operations whose
     * response body a `#[CoversApiRequiredResponseAttributes]` marker claims. An error declaration
     * carries a status and no required response attributes.
     *
     * @return array<string>
     */
    protected function declaredSuccessDispatchKeys(): array
    {
        return array_values(array_map(
            static fn (ApiOperation $operation): string => $operation->dispatchKey(),
            array_filter($this->declaredOperations, static fn (ApiOperation $operation): bool => $operation->status === null),
        ));
    }

    /**
     * The short name of every generated resource. Static for the same reason
     * {@see AbstractApiTestCase::schemaDeclaredResponses()} is: one reflection sweep per process.
     *
     * @return array<string>
     */
    protected function schemaResourceShortNames(): array
    {
        if (!isset(static::$schemaResourceShortNames[static::API_TYPE])) {
            static::$schemaResourceShortNames[static::API_TYPE] = $this->createContractCoverageRunner()
                ->resourceShortNames($this->getProjectRoot());
        }

        return static::$schemaResourceShortNames[static::API_TYPE];
    }

    /**
     * The short name of every generated resource whose schema declares an identifier — the ones a
     * response owes a `data.id`. Static for the same reason
     * {@see AbstractApiTestCase::schemaDeclaredResponses()} is: one reflection sweep per process.
     *
     * @return array<string>
     */
    protected function schemaIdentifierDeclaringResourceShortNames(): array
    {
        if (!isset(static::$schemaIdentifierDeclaringResourceShortNames[static::API_TYPE])) {
            static::$schemaIdentifierDeclaringResourceShortNames[static::API_TYPE] = $this->createContractCoverageRunner()
                ->resourceShortNamesDeclaringIdentifier($this->getProjectRoot());
        }

        return static::$schemaIdentifierDeclaringResourceShortNames[static::API_TYPE];
    }

    /**
     * Builds (and caches per kernel) the route-name → [verb, canonical uriTemplate] map from the
     * router's route collection. This is the same generated route set the request matched against,
     * so a recorded uriTemplate is authoritative rather than re-derived.
     *
     * @throws \LogicException
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected static function operationRouteMap(KernelInterface $kernel): array
    {
        $kernelId = spl_object_id($kernel);
        if (isset(static::$operationRouteMaps[$kernelId])) {
            return static::$operationRouteMaps[$kernelId];
        }

        $map = [];
        // Only reachable from the recording listener, so the kernel is a booted one that has
        // already dispatched a request. A router missing there would silently empty the map and
        // turn every declaration of the run into a vacuous pass, so it fails instead.
        try {
            /** @var \Symfony\Component\Routing\RouterInterface $router */
            $router = $kernel->getContainer()->get('router');
            foreach ($router->getRouteCollection() as $routeName => $route) {
                $methods = $route->getMethods();
                $map[$routeName] = [$methods[0] ?? 'GET', UriTemplateNormalizer::normalize($route->getPath())];
            }
        } catch (Throwable $throwable) {
            throw new LogicException(
                'The booted kernel exposes no router, so the operations a request matches cannot be '
                . 'recorded and no coverage declaration could be verified.',
                0,
                $throwable,
            );
        }

        static::$operationRouteMaps[$kernelId] = $map;

        return $map;
    }

    /**
     * Sets every registered service mock on the booted container, after every boot and in every
     * mode. Binding goes through the test container where there is one, because most Spryker
     * services are private and only `TestContainer::set()` reaches the container's privates.
     * {@see setService()} covers the other order, a mock registered against a running kernel.
     */
    protected function applyServiceMocks(): void
    {
        if ($this->serviceMocks === []) {
            return;
        }

        foreach ($this->serviceMocks as $serviceId => $mock) {
            $this->bindServiceMock($serviceId, $mock);
        }
    }

    /**
     * Binding a mock that the container has already handed out is what Symfony refuses, so a caller
     * only reaches this once per service per boot: either through {@see applyServiceMocks()} while
     * the kernel is still fresh, or through {@see setService()} before anything asked for it.
     */
    protected function bindServiceMock(string $serviceId, object $mock): void
    {
        if ($this->kernel === null) {
            return;
        }

        $mockTarget = $this->resolveServiceMockTarget();

        $mockTarget->set($serviceId, $mock);
    }

    protected function resolveServiceMockTarget(): ContainerInterface
    {
        $container = $this->kernel->getContainer();

        return $container->has('test.service_container')
            ? $container->get('test.service_container')
            : $container;
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

            unset(static::$operationRouteMaps[spl_object_id($this->kernel)]);

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

    /**
     * A mock registered before the kernel boots is bound by {@see applyServiceMocks()}; one
     * registered against a kernel that is already up would otherwise sit in the list unused, which
     * is silent — the real service keeps answering and the test fails somewhere far away.
     */
    public function setService(string $serviceId, object $service): void
    {
        $this->serviceMocks[$serviceId] = $service;

        // A test arranges its stubs after setUp() has already booted the kernel, and
        // {@see applyServiceMocks()} only runs from {@see bootKernel()}. Without binding here the
        // stub is recorded and never reaches the container, and the test silently exercises the
        // real service - which is how two health-check cases spent a release asserting against the
        // real reader's 403.
        //
        // Only this one id is bound, and only while the shared kernel has not built it yet: Symfony
        // refuses to replace an initialized service, and forcing one out from under the consumers
        // that already hold it would swap the reference in the container without swapping it in
        // them. A stub arranged that late still cannot take effect, which is the same outcome as
        // before this binding existed.
        if (!$this->booted || $this->resolveServiceMockTarget()->initialized($serviceId)) {
            return;
        }

        try {
            $this->bindServiceMock($serviceId, $service);
        } catch (InvalidArgumentException $invalidArgumentException) {
            // A private service the container has already built refuses replacement the same way,
            // but only from set(): TestContainer::initialized() answers for the public container
            // alone, so the guard above cannot see it.
        }
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
        // setService() and bootKernel() both bind through applyServiceMocks(), so whichever came
        // first, the mocks are already in the container by the time it is retrieved here.
        return $this->getTestContainer()->get($serviceClass);
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
     * Public for exactly one caller, {@see \SprykerTest\ApiPlatform\Helper\ApiRequestHelper}, which
     * is the actor action a test dispatches through. A test never calls this directly, and neither
     * does any other helper - they reach the seam through
     * {@see \SprykerTest\ApiPlatform\Helper\ApiRequestHelperTrait}.
     *
     * @param array<string, string> $headers
     */
    public function handleApiRequest(string $method, string $uri, ?string $content = null, array $headers = []): Response
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

        $this->initializeOperationCoverageRecording();

        $this->_before();
    }

    /**
     * Arms a recorder for the current method when it declares operations, so the request listener
     * records what runs and {@see assertPostConditions()} can verify each declaration. No
     * declarations keeps recording off, at no cost to non-annotated suites.
     */
    protected function initializeOperationCoverageRecording(): void
    {
        $this->declaredOperations = [];
        $this->operationRecorder = null;
        $this->envelopeRecorder = null;
        $this->responseAttributeRecorder = null;
        static::$activeOperationRecorder = null;
        static::$activeEnvelopeRecorder = null;

        if (!method_exists($this, 'name')) {
            return;
        }

        $this->declaredOperations = AnnotationCollector::operationsForMethod(static::class, $this->name());
        if ($this->declaredOperations === []) {
            return;
        }

        $this->operationRecorder = ContractCoverageFactory::createOperationCoverageRecorder();
        static::$activeOperationRecorder = $this->operationRecorder;

        $this->envelopeRecorder = ContractCoverageFactory::createJsonApiEnvelopeRecorder(
            $this->schemaResourceShortNames(),
            $this->schemaIdentifierDeclaringResourceShortNames(),
        );
        static::$activeEnvelopeRecorder = $this->envelopeRecorder;

        $this->responseAttributeRecorder = new ResponseAttributeRecorder();
    }

    /**
     * Turns a passing test's `#[CoversApiOperation]` claims into verified facts, and holds the run
     * to the resource schema: a declared response that never came back, or a response the schema
     * does not declare, fails the test. Post-conditions are skipped after a failure, so this never
     * piles onto an unrelated one.
     */
    protected function assertPostConditions(): void
    {
        parent::assertPostConditions();

        if ($this->declaredOperations === [] || $this->operationRecorder === null) {
            return;
        }

        $verification = $this->operationRecorder->verify($this->declaredOperations, $this->schemaDeclaredResponses());

        $this->failOnUnverifiedDeclarations($verification->unverified);
        $this->failOnUndeclaredObservations($verification->undeclaredObservations);
        $this->failOnEnvelopeViolations($this->envelopeRecorder?->violations() ?? []);
        $this->failOnUnassertedResponseAttributes();
    }

    /**
     * Holds a `#[CoversApiRequiredResponseAttributes]` test to the response attributes its declared
     * success operations must carry: claiming the coverage without ever reading a path through the
     * assertion helpers fails the test rather than counting as covered.
     */
    protected function failOnUnassertedResponseAttributes(): void
    {
        if ($this->responseAttributeRecorder === null) {
            return;
        }

        if (!AnnotationCollector::coversRequiredResponseAttributes(static::class, $this->name())) {
            return;
        }

        $missing = $this->responseAttributeRecorder->verify(
            $this->schemaResponseAttributePaths($this->declaredSuccessDispatchKeys()),
        );
        if ($missing === []) {
            return;
        }

        $this->fail(sprintf(
            '%s::%s is marked #[CoversApiRequiredResponseAttributes] but never asserted: %s. Assert each through '
            . 'assertResponseAttributes() against the fixture the test created; a value the server mints '
            . '(uuid, timestamps) goes through assertResponseAttributesPresent(). An attribute that is '
            . 'legitimately absent here opts out with responseOptional: true in the .resource.yml.',
            static::class,
            $this->name(),
            implode(', ', $missing),
        ));
    }

    /**
     * @param array<string> $violations
     */
    protected function failOnEnvelopeViolations(array $violations): void
    {
        if ($violations === []) {
            return;
        }

        $this->fail(sprintf(
            "%s::%s produced responses that break the JSON:API envelope:\n- %s\nThe envelope is part of "
            . 'every resource\'s contract, so fix the response rather than the test.',
            static::class,
            $this->name(),
            implode("\n- ", $violations),
        ));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $unverified
     */
    protected function failOnUnverifiedDeclarations(array $unverified): void
    {
        if ($unverified === []) {
            return;
        }

        $this->fail(sprintf(
            'These #[CoversApiOperation] declarations were never produced by %s::%s: %s. The resource '
            . 'schema is the contract: either the schema is wrong (edit the .resource.yml and rerun '
            . 'vendor/bin/glue api:generate) or the implementation is. Decide and fix one of them.',
            static::class,
            $this->name(),
            implode(', ', array_map(static fn ($operation): string => $operation->key(), $unverified)),
        ));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $undeclaredObservations
     */
    protected function failOnUndeclaredObservations(array $undeclaredObservations): void
    {
        if ($undeclaredObservations === []) {
            return;
        }

        $schemaDeclaredResponses = $this->schemaDeclaredResponses();

        $details = array_map(
            static fn ($observation): string => sprintf(
                '%s (declared: %s)',
                $observation->key(),
                implode(', ', $schemaDeclaredResponses[$observation->dispatchKey()] ?? []),
            ),
            $undeclaredObservations,
        );

        $this->fail(sprintf(
            '%s::%s observed %s, which the resource schema does not declare. The schema is the source '
            . 'of truth — either declare this response in the .resource.yml and rerun '
            . 'vendor/bin/glue api:generate, or fix the implementation to return a declared response.',
            static::class,
            $this->name(),
            implode('; ', $details),
        ));
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

        $this->declaredOperations = [];
        $this->operationRecorder = null;
        $this->envelopeRecorder = null;
        $this->responseAttributeRecorder = null;
        static::$activeOperationRecorder = null;
        static::$activeEnvelopeRecorder = null;

        if (TestModeConfiguration::isBootOnce() && isset(static::$sharedKernels[static::API_TYPE])) {
            // Keep the shared kernel alive for the next method; only reset the
            // container's stateful services and clear this method's mocks.
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
