<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform;

use Codeception\Test\Unit;
use Exception;

/**
 * Base test case for API Platform unit tests.
 *
 * Use this class for tests that:
 * - Do not require Symfony kernel
 * - Do not need database access
 * - Test isolated business logic
 * - Use mocks or stubs for dependencies
 *
 * Example:
 * ```php
 * class ApiTypeNormalizerTest extends ApiUnitTestCase
 * {
 *     public function testGivenLowercaseWhenNormalizingThenReturnsUcfirst(): void
 *     {
 *         // Arrange
 *         $input = 'backend';
 *
 *         // Act
 *         $result = ApiTypeNormalizer::normalizeForGeneration($input);
 *
 *         // Assert
 *         $this->assertEquals('backend', $result);
 *     }
 * }
 * ```
 */
abstract class ApiUnitTestCase extends Unit
{
    /**
     * Create mock with type hint support.
     *
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createMockWithType(string $className): object
    {
        return $this->createMock($className);
    }

    /**
     * @param string $exceptionClass The exception class name
     * @param string $messagePattern Regex pattern for exception message
     * @param callable $callback The code that should throw
     *
     * @return void
     */
    protected function assertExceptionWithMessage(
        string $exceptionClass,
        string $messagePattern,
        callable $callback,
    ): void {
        try {
            $callback();

            $this->fail(sprintf('Expected exception %s was not thrown', $exceptionClass));
        } catch (Exception $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            $this->assertMatchesRegularExpression($messagePattern, $e->getMessage());
        }
    }
}
