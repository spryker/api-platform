<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Exception\ModuleException;
use Codeception\Module;
use Codeception\Test\TestCaseWrapper;
use Codeception\TestInterface;
use SprykerTest\ApiPlatform\Test\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The request seam of the API test lanes, exposed as an actor action so a test dispatches through
 * `$this->tester->handleApiRequest(...)` and never learns which base class boots the kernel.
 *
 * It is also the one place any test support code reaches the running test case: a helper that
 * builds requests for its own resource uses {@see ApiRequestHelperTrait} and keeps calling
 * `$this->handleApiRequest(...)` unchanged.
 *
 * Enable it on the suite in codeception.yml, after the suite helper that sets the environment:
 * ```yaml
 * modules:
 *     enabled:
 *         - \SprykerTest\ApiPlatform\Helper\ApiRequestHelper
 * ```
 */
class ApiRequestHelper extends Module
{
    protected ?AbstractApiTestCase $testCase = null;

    public function _before(TestInterface $test): void
    {
        $testCase = $test instanceof TestCaseWrapper ? $test->getTestCase() : $test;

        $this->testCase = $testCase instanceof AbstractApiTestCase ? $testCase : null;
    }

    public function _after(TestInterface $test): void
    {
        $this->testCase = null;
    }

    /**
     * Dispatches a request through the booted kernel and returns the raw response. A relative URI
     * is resolved against the suite's base URL.
     *
     * @param array<string, string> $headers
     */
    public function handleApiRequest(string $method, string $uri, ?string $content = null, array $headers = []): Response
    {
        return $this->getTestCase()->handleApiRequest($method, $uri, $content, $headers);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function encodeJsonApiBody(string $type, array $attributes, ?string $id = null): string
    {
        return $this->getTestCase()->encodeJsonApiBody($type, $attributes, $id);
    }

    /**
     * Registers a header applied to every following request of the test method. A per-call header
     * still wins over it.
     */
    public function addDefaultRequestHeader(string $name, string $value): void
    {
        $this->getTestCase()->addDefaultRequestHeader($name, $value);
    }

    /**
     * @throws \Codeception\Exception\ModuleException
     */
    protected function getTestCase(): AbstractApiTestCase
    {
        if ($this->testCase === null) {
            throw new ModuleException($this, sprintf(
                'The request seam dispatches through the running test case, so a test using %s has '
                . 'to extend %s.',
                static::class,
                AbstractApiTestCase::class,
            ));
        }

        return $this->testCase;
    }
}
