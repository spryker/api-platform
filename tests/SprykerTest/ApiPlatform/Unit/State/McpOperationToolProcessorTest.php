<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Exception\AccessDeniedException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\State\ProviderInterface;
use Codeception\Test\Unit;
use LogicException;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\ApiPlatform\State\McpOperationToolProcessor;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group McpOperationToolProcessorTest
 * Add your own group annotations below this line
 */
class McpOperationToolProcessorTest extends Unit
{
    protected const string RESOURCE_CLASS = 'Generated\Api\Storefront\ThingsStorefrontResource';

    protected const string URI_TEMPLATE_COLLECTION = '/things';

    protected const string URI_TEMPLATE_ITEM = '/things/{sku}';

    protected Request $providedRequest;

    protected ?Operation $providedOperation = null;

    /**
     * @var array<string, mixed>
     */
    protected array $providedUriVariables = [];

    public function testGivenCollectionToolWhenProcessingThenTheSourceOperationRunsWithTheArgumentsAsQuery(): void
    {
        // Arrange
        $mcpRequest = $this->createMcpRequest();
        $requestStack = $this->createRequestStack($mcpRequest);
        $processor = $this->createProcessor($requestStack, fn (Request $request): array => [['name' => 'first']]);

        // Act
        $result = $processor->process(
            $this->createInput(['filter' => 'new', 'limit' => 5, 'isActive' => false, 'unused' => null]),
            $this->createMcpTool('GetCollection', static::URI_TEMPLATE_COLLECTION),
            [],
            ['request' => $mcpRequest],
        );

        // Assert
        $this->assertFalse($result->isError);
        $this->assertSame(['items' => [['name' => 'first']]], $result->structuredContent);
        $this->assertInstanceOf(GetCollection::class, $this->providedOperation);
        $this->assertSame(['filter' => 'new', 'limit' => '5', 'isActive' => 'false'], $this->providedRequest->query->all());
        $this->assertSame('filter=new&limit=5&isActive=false', $this->providedRequest->server->get('QUERY_STRING'));
        $this->assertSame('GET', $this->providedRequest->getMethod());
        $this->assertSame($this->providedOperation, $this->providedRequest->attributes->get(RequestAttribute::API_OPERATION));
        $this->assertSame('customer', $this->providedRequest->attributes->get('customer'));
        $this->assertFalse($this->providedRequest->attributes->has('_api_query_parameters'));
        $this->assertSame($mcpRequest, $requestStack->getCurrentRequest());
    }

    public function testGivenRenamedArgumentsWhenProcessingThenTheSourceOperationReadsThemUnderTheirQueryParameterNames(): void
    {
        // Arrange
        $mcpRequest = $this->createMcpRequest();
        $processor = $this->createProcessor($this->createRequestStack($mcpRequest), fn (Request $request): array => []);
        $mcpTool = $this->createMcpTool('GetCollection', static::URI_TEMPLATE_COLLECTION, [
            'pageLimit' => 'page[limit]',
            'filterCompanyRoles' => 'filter[company-roles][]',
            'filterStatus' => 'filter[status][]',
        ]);

        // Act
        $processor->process(
            $this->createInput(['pageLimit' => 5, 'filterCompanyRoles' => ['buyer', 'approver'], 'filterStatus' => 'active', 'locale' => 'en_US']),
            $mcpTool,
            [],
            ['request' => $mcpRequest],
        );

        // Assert
        $this->assertSame([
            'page' => ['limit' => '5'],
            'filter' => ['company-roles' => ['buyer', 'approver'], 'status' => ['active']],
            'locale' => 'en_US',
        ], $this->providedRequest->query->all());
        $this->assertSame(['limit' => '5'], $this->providedRequest->query->all('page'));
    }

    public function testGivenSourceOperationAnswersAnErrorStatusCodeWhenProcessingThenTheResultIsAnErrorAndTheMcpRequestKeepsItsStatus(): void
    {
        // Arrange
        $mcpRequest = $this->createMcpRequest();
        $processor = $this->createProcessor($this->createRequestStack($mcpRequest), function (Request $request): array {
            $request->attributes->set(RequestAttribute::RESPONSE_STATUS_CODE, 400);

            return [['statusCode' => 400]];
        });

        // Act
        $result = $processor->process($this->createInput([]), $this->createMcpTool('GetCollection', static::URI_TEMPLATE_COLLECTION), [], ['request' => $mcpRequest]);

        // Assert
        $this->assertTrue($result->isError);
        $this->assertSame(['items' => [['statusCode' => 400]]], $result->structuredContent);
        $this->assertFalse($mcpRequest->attributes->has(RequestAttribute::RESPONSE_STATUS_CODE));
    }

    public function testGivenItemToolWhenProcessingThenTheUriVariableArgumentsAreNotSentAsQuery(): void
    {
        // Arrange
        $mcpRequest = $this->createMcpRequest();
        $processor = $this->createProcessor($this->createRequestStack($mcpRequest), fn (Request $request): array => ['sku' => '001']);

        // Act
        $result = $processor->process($this->createInput(['sku' => '001', 'locale' => 'de_DE']), $this->createMcpTool('Get', static::URI_TEMPLATE_ITEM), [], ['request' => $mcpRequest]);

        // Assert
        $this->assertFalse($result->isError);
        $this->assertSame(['sku' => '001'], $result->structuredContent);
        $this->assertSame(['sku' => '001'], $this->providedUriVariables);
        $this->assertSame(['locale' => 'de_DE'], $this->providedRequest->query->all());
        $this->assertSame('001', $this->providedRequest->attributes->get('sku'));
    }

    public function testGivenItemToolWithoutResultWhenProcessingThenTheResultIsANotFoundError(): void
    {
        // Arrange
        $mcpRequest = $this->createMcpRequest();
        $processor = $this->createProcessor($this->createRequestStack($mcpRequest), fn (Request $request): ?array => null);

        // Act
        $result = $processor->process($this->createInput(['sku' => '404']), $this->createMcpTool('Get', static::URI_TEMPLATE_ITEM), [], ['request' => $mcpRequest]);

        // Assert
        $this->assertTrue($result->isError);
        $this->assertSame(['statusCode' => 404, 'message' => 'Not Found'], $result->structuredContent);
    }

    public function testGivenSourceOperationThrowsAnHttpExceptionWhenProcessingThenTheResultIsAnErrorWithItsStatusCode(): void
    {
        // Arrange
        $mcpRequest = $this->createMcpRequest();
        $requestStack = $this->createRequestStack($mcpRequest);
        $processor = $this->createProcessor($requestStack, fn (Request $request): array => throw new NotFoundHttpException('Thing not found.'));

        // Act
        $result = $processor->process($this->createInput(['sku' => '404']), $this->createMcpTool('Get', static::URI_TEMPLATE_ITEM), [], ['request' => $mcpRequest]);

        // Assert
        $this->assertTrue($result->isError);
        $this->assertSame(['statusCode' => 404, 'message' => 'Thing not found.'], $result->structuredContent);
        $this->assertSame($mcpRequest, $requestStack->getCurrentRequest());
    }

    public function testGivenSourceOperationDeniesAccessWhenProcessingThenTheDenialIsRethrown(): void
    {
        // Arrange
        $mcpRequest = $this->createMcpRequest();
        $exception = new AccessDeniedException('Customer only.');
        $processor = $this->createProcessor($this->createRequestStack($mcpRequest), fn (Request $request): array => throw $exception);

        // Act
        $thrown = $this->catchThrowable(fn () => $processor->process($this->createInput([]), $this->createMcpTool('GetCollection', static::URI_TEMPLATE_COLLECTION), [], ['request' => $mcpRequest]));

        // Assert
        $this->assertSame($exception, $thrown);
    }

    public function testGivenToolWithUnknownSourceOperationWhenProcessingThenALogicExceptionAsksForTheGeneration(): void
    {
        // Arrange
        $mcpRequest = $this->createMcpRequest();
        $processor = $this->createProcessor($this->createRequestStack($mcpRequest), fn (Request $request): array => []);

        // Act
        $thrown = $this->catchThrowable(fn () => $processor->process($this->createInput([]), $this->createMcpTool('GetCollection', '/unknown'), [], ['request' => $mcpRequest]));

        // Assert
        $this->assertInstanceOf(LogicException::class, $thrown);
        $this->assertStringContainsString('Run the resource generation again', $thrown->getMessage());
    }

    protected function createProcessor(RequestStack $requestStack, callable $provide): McpOperationToolProcessor
    {
        $resourceMetadataCollectionFactory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataCollectionFactory->method('create')->willReturn(new ResourceMetadataCollection(static::RESOURCE_CLASS, [
            new ApiResource(operations: [
                'things_get_collection' => new GetCollection(uriTemplate: static::URI_TEMPLATE_COLLECTION . '{._format}', class: static::RESOURCE_CLASS, name: 'things_get_collection'),
                'things_get' => new Get(uriTemplate: static::URI_TEMPLATE_ITEM, class: static::RESOURCE_CLASS, uriVariables: ['sku' => new Link(fromClass: static::RESOURCE_CLASS)], name: 'things_get'),
            ]),
        ]));

        $sourceOperationProvider = $this->createMock(ProviderInterface::class);
        $sourceOperationProvider->method('provide')->willReturnCallback(
            function (HttpOperation $operation, array $uriVariables, array $context) use ($provide): mixed {
                $this->providedOperation = $operation;
                $this->providedUriVariables = $uriVariables;
                $this->providedRequest = $context['request'];

                return $provide($context['request']);
            },
        );

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('normalize')->willReturnArgument(0);

        return new McpOperationToolProcessor($resourceMetadataCollectionFactory, $sourceOperationProvider, $normalizer, $requestStack);
    }

    /**
     * @param array<string, string> $queryParameterNames
     */
    protected function createMcpTool(string $operationType, string $uriTemplate, array $queryParameterNames = []): McpTool
    {
        return new McpTool(
            name: 'things',
            class: static::RESOURCE_CLASS,
            extraProperties: [
                McpOperationToolProcessor::EXTRA_PROPERTY_SOURCE_OPERATION => [
                    McpOperationToolProcessor::SOURCE_OPERATION_TYPE => $operationType,
                    McpOperationToolProcessor::SOURCE_OPERATION_URI_TEMPLATE => $uriTemplate,
                    McpOperationToolProcessor::SOURCE_OPERATION_QUERY_PARAMETER_NAMES => $queryParameterNames,
                ],
            ],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function createInput(array $arguments): stdClass
    {
        return (object)$arguments;
    }

    protected function createMcpRequest(): Request
    {
        $request = Request::create('/mcp', Request::METHOD_POST);
        $request->attributes->set('customer', 'customer');
        $request->attributes->set('_api_query_parameters', []);

        return $request;
    }

    protected function createRequestStack(Request $mcpRequest): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push($mcpRequest);

        return $requestStack;
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
