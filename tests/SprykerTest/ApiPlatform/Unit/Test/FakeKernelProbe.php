<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Test;

use SprykerTest\ApiPlatform\Test\StorefrontApiTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Test-only probe: a StorefrontApiTestCase whose createKernel() returns a supplied
 * fake kernel, letting boot-lifecycle logic be exercised without a full Symfony boot.
 *
 * The file name intentionally omits the "Test" suffix so Codeception does not collect
 * it as a test class; it is instantiated directly by AbstractApiTestCaseBootTest.
 */
class FakeKernelProbe extends StorefrontApiTestCase
{
    /**
     * The supplied kernels are empty stubs with no event dispatcher, and the probe dispatches no
     * request, so it is the one case allowed to boot without operation recording.
     */
    protected const bool ALLOWS_MISSING_OPERATION_RECORDING = true;

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
}
