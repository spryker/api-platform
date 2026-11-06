<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State;

use ApiPlatform\Metadata\Exception\AccessDeniedException;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\ApiPlatform\State\McpAccessDeniedToHttpProcessor;
use Spryker\ApiPlatform\State\McpAccessDeniedToHttpProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;
use Throwable;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group McpAccessDeniedToHttpTest
 * Add your own group annotations below this line
 */
class McpAccessDeniedToHttpTest extends Unit
{
    protected const string SECURITY_MESSAGE = 'Customer only.';

    public function testGivenToolProviderDeniesAccessWhenProvidingThenTheExceptionIsRecordedOnTheMainRequestAndRethrown(): void
    {
        // Arrange
        $requestStack = $this->createRequestStack();
        $exception = new AccessDeniedException(static::SECURITY_MESSAGE);
        $provider = new McpAccessDeniedToHttpProvider($this->createThrowingProvider($exception), $requestStack);

        // Act
        $thrown = $this->catchThrowable(fn () => $provider->provide(new McpTool()));

        // Assert
        $this->assertSame($exception, $thrown);
        $this->assertSame($exception, $requestStack->getMainRequest()?->attributes->get(RequestAttribute::MCP_ACCESS_DENIED));
    }

    public function testGivenToolProviderGrantsAccessWhenProvidingThenNothingIsRecorded(): void
    {
        // Arrange
        $requestStack = $this->createRequestStack();
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->method('provide')->willReturn(['status' => 'healthy']);

        // Act
        $result = (new McpAccessDeniedToHttpProvider($innerProvider, $requestStack))->provide(new McpTool());

        // Assert
        $this->assertSame(['status' => 'healthy'], $result);
        $this->assertFalse($requestStack->getMainRequest()?->attributes->has(RequestAttribute::MCP_ACCESS_DENIED));
    }

    public function testGivenToolProcessorThrowsSecurityAccessDeniedWhenProcessingThenTheExceptionIsRecordedOnTheMainRequestAndRethrown(): void
    {
        // Arrange
        $requestStack = $this->createRequestStack();
        $exception = new SecurityAccessDeniedException(static::SECURITY_MESSAGE);
        $innerProcessor = $this->createMock(ProcessorInterface::class);
        $innerProcessor->method('process')->willThrowException($exception);
        $processor = new McpAccessDeniedToHttpProcessor($innerProcessor, $requestStack);

        // Act
        $thrown = $this->catchThrowable(fn () => $processor->process(null, new McpTool()));

        // Assert
        $this->assertSame($exception, $thrown);
        $this->assertSame($exception, $requestStack->getMainRequest()?->attributes->get(RequestAttribute::MCP_ACCESS_DENIED));
    }

    protected function createRequestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/mcp', Request::METHOD_POST));

        return $requestStack;
    }

    /**
     * @return \ApiPlatform\State\ProviderInterface<object>
     */
    protected function createThrowingProvider(Throwable $exception): ProviderInterface
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willThrowException($exception);

        return $provider;
    }

    protected function catchThrowable(callable $callable): ?Throwable
    {
        try {
            $callable();
        } catch (Throwable $throwable) {
            return $throwable;
        }

        return null;
    }
}
