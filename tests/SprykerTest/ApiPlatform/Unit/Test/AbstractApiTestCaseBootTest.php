<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Test;

use Codeception\Stub;
use Codeception\Test\Unit;
use LogicException;
use ReflectionProperty;
use Spryker\Service\Container\ContainerDelegator;
use SprykerTest\ApiPlatform\Test\AbstractApiTestCase;
use SprykerTest\ApiPlatform\Test\StorefrontApiTestCase;
use SprykerTest\ApiPlatform\Test\TestMode;
use SprykerTest\ApiPlatform\Test\TestModeConfiguration;
use stdClass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Test
 * @group AbstractApiTestCaseBootTest
 * Add your own group annotations below this line
 */
class AbstractApiTestCaseBootTest extends Unit
{
    protected TestMode $suiteMode;

    protected function setUp(): void
    {
        parent::setUp();

        // Capture the suite mode; tearDown restores it so the shared Testify
        // kernel cache stays keyed on the same mode between test methods.
        $this->suiteMode = TestModeConfiguration::getTestMode();
    }

    protected function tearDown(): void
    {
        TestModeConfiguration::reset();
        TestModeConfiguration::setTestMode($this->suiteMode);
        parent::tearDown();
    }

    public function testGivenDebugFalseWhenKernelCreatedThenKernelIsNotInDebugMode(): void
    {
        // Arrange
        TestModeConfiguration::setDebug(false);
        $case = new class ('probe') extends StorefrontApiTestCase {
            public function exposeCreateKernel(): KernelInterface
            {
                defined('APPLICATION') || define('APPLICATION', 'GLUE');

                return $this->createKernel();
            }
        };

        // Act
        $kernel = $case->exposeCreateKernel();

        // Assert
        $this->assertFalse($kernel->isDebug());
    }

    public function testGivenDefaultConfigWhenKernelCreatedThenKernelIsInDebugMode(): void
    {
        // Arrange
        TestModeConfiguration::reset();
        TestModeConfiguration::setTestMode($this->suiteMode);
        $case = new class ('probe') extends StorefrontApiTestCase {
            public function exposeCreateKernel(): KernelInterface
            {
                defined('APPLICATION') || define('APPLICATION', 'GLUE');

                return $this->createKernel();
            }
        };

        // Act
        $kernel = $case->exposeCreateKernel();

        // Assert — unchanged default
        $this->assertTrue($kernel->isDebug());
    }

    public function testGivenReuseEnabledWhenResettingContainerThenContainerDelegatorNotReset(): void
    {
        // Arrange
        TestModeConfiguration::setReuseApplicationContainer(true);
        ContainerDelegator::getInstance(); // seed a singleton instance
        $case = new class ('probe') extends StorefrontApiTestCase {
            public function exposeContainerReset(): void
            {
                $this->resetContainerDelegatorUnlessReused();
            }
        };

        // Act
        $case->exposeContainerReset();

        // Assert — the singleton instance survives
        $property = new ReflectionProperty(ContainerDelegator::class, 'instance');
        $this->assertNotNull($property->getValue());
    }

    public function testGivenReuseDisabledWhenResettingContainerThenContainerDelegatorReset(): void
    {
        // Arrange — default (reuse off)
        ContainerDelegator::getInstance(); // seed a singleton instance
        $case = new class ('probe') extends StorefrontApiTestCase {
            public function exposeContainerReset(): void
            {
                $this->resetContainerDelegatorUnlessReused();
            }
        };

        // Act
        $case->exposeContainerReset();

        // Assert — default behaviour still resets the singleton
        $property = new ReflectionProperty(ContainerDelegator::class, 'instance');
        $this->assertNull($property->getValue());
    }

    public function testGivenServiceMockWhenKernelBootedThenMockAppliedToContainerWithoutGetService(): void
    {
        // Arrange — a fake kernel isolates the setService→bootKernel binding from a
        // full Symfony boot (proven end-to-end by the Wishlists contract suite).
        TestModeConfiguration::setBootOnce(true);

        $stub = new stdClass();
        $container = new Container();
        $fakeKernel = Stub::makeEmpty(KernelInterface::class, ['getContainer' => $container]);
        $case = $this->createFakeKernelCase($fakeKernel);
        $case->setService('probe.mock.service', $stub);

        // Act
        $kernel = $case->exposeBoot();

        // Assert — the mock landed in the booted container although getService() was never called
        $this->assertSame($stub, $kernel->getContainer()->get('probe.mock.service'));
    }

    public function testGivenAPerMethodBootSuiteWhenKernelBootedThenMockStillApplied(): void
    {
        // Arrange — the default, per-method-boot mode every `mode: core` provider/processor suite
        // runs in. Its mocks used to be bound by a compiler pass that no longer exists, which left
        // setService() a silent no-op and the real service answering.
        $stub = new stdClass();
        $container = new Container();
        $fakeKernel = Stub::makeEmpty(KernelInterface::class, ['getContainer' => $container]);
        $case = $this->createFakeKernelCase($fakeKernel);
        $case->setService('probe.mock.service', $stub);

        // Act
        $kernel = $case->exposeBoot();

        // Assert
        $this->assertSame($stub, $kernel->getContainer()->get('probe.mock.service'));
    }

    public function testGivenAMockRegisteredAfterTheKernelBootedThenItIsBoundImmediately(): void
    {
        // Arrange
        $stub = new stdClass();
        $container = new Container();
        $fakeKernel = Stub::makeEmpty(KernelInterface::class, ['getContainer' => $container]);
        $case = $this->createFakeKernelCase($fakeKernel);
        $kernel = $case->exposeBoot();

        // Act — registered against a kernel that is already up
        $case->setService('probe.late.service', $stub);

        // Assert
        $this->assertSame($stub, $kernel->getContainer()->get('probe.late.service'));
    }

    public function testGivenAServiceTheContainerAlreadyBuiltWhenRegisteringAMockThenItIsSkippedRatherThanThrown(): void
    {
        // Arrange - TestContainer::set() is what refuses an already-built private service, and
        // TestContainer::initialized() cannot report one, so the refusal has to be tolerated here.
        $mockTarget = Stub::makeEmpty(ContainerInterface::class, [
            'initialized' => false,
            'set' => function (): void {
                throw new InvalidArgumentException('The "probe.private.service" service is already initialized, you cannot replace it.');
            },
        ]);
        $container = new Container();
        $container->set('test.service_container', $mockTarget);
        $fakeKernel = Stub::makeEmpty(KernelInterface::class, ['getContainer' => $container]);
        $case = $this->createFakeKernelCase($fakeKernel);
        $case->exposeBoot();

        // Act
        $case->setService('probe.private.service', new stdClass());

        // Assert - the registration is still recorded for the next boot
        $serviceMocks = (new ReflectionProperty(AbstractApiTestCase::class, 'serviceMocks'))->getValue($case);
        $this->assertArrayHasKey('probe.private.service', $serviceMocks);
    }

    public function testGivenBootOnceWhenTwoTestInstancesBootThenKernelBootedOnce(): void
    {
        // Arrange
        TestModeConfiguration::setBootOnce(true);
        AbstractApiTestCase::resetSharedKernel();

        // Act — two fresh instances share the static kernel registry, as separate
        // test methods would within one suite.
        $firstCase = $this->createFakeKernelCase(Stub::makeEmpty(KernelInterface::class));
        $firstCase->exposeBoot();
        $firstBootCount = AbstractApiTestCase::getBootCount();

        $secondCase = $this->createFakeKernelCase(Stub::makeEmpty(KernelInterface::class));
        $secondCase->exposeBoot();
        $secondBootCount = AbstractApiTestCase::getBootCount();

        // Assert
        $this->assertSame(1, $firstBootCount);
        $this->assertSame(1, $secondBootCount, 'Kernel must not re-boot between methods under bootOnce');

        AbstractApiTestCase::resetSharedKernel();
    }

    public function testGivenBootOnceDisabledWhenTwoTestInstancesBootThenKernelBootedTwice(): void
    {
        // Arrange — default (bootOnce off)
        AbstractApiTestCase::resetSharedKernel();

        // Act
        $this->createFakeKernelCase(Stub::makeEmpty(KernelInterface::class))->exposeBoot();
        $this->createFakeKernelCase(Stub::makeEmpty(KernelInterface::class))->exposeBoot();

        // Assert — each instance boots its own kernel
        $this->assertSame(2, AbstractApiTestCase::getBootCount());

        AbstractApiTestCase::resetSharedKernel();
    }

    public function testGivenAKernelWithoutAnEventDispatcherWhenACaseThatNeedsRecordingBootsThenItFailsLoudly(): void
    {
        // Arrange - the same empty kernel the probes boot, on a case that does not opt out of
        // operation recording.
        AbstractApiTestCase::resetSharedKernel();
        $case = new class ('probe') extends StorefrontApiTestCase {
            public KernelInterface $fakeKernel;

            protected function createKernel(): KernelInterface
            {
                defined('APPLICATION') || define('APPLICATION', 'GLUE');

                return $this->fakeKernel;
            }

            public function exposeBoot(): KernelInterface
            {
                return $this->getTestKernel();
            }
        };
        $case->fakeKernel = Stub::makeEmpty(KernelInterface::class);

        // Assert - silently switching recording off would let every coverage declaration of the
        // case pass without ever being dispatched.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Operation recording could not be installed/');

        // Act
        $case->exposeBoot();
    }

    /**
     * Builds a probe test case whose createKernel() returns the supplied fake kernel,
     * so boot-lifecycle logic can be exercised without a full Symfony boot.
     */
    protected function createFakeKernelCase(KernelInterface $fakeKernel): FakeKernelProbe
    {
        $case = new FakeKernelProbe('probe');
        $case->fakeKernel = $fakeKernel;

        return $case;
    }
}
