<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\ApiPlatform\Test;

use Codeception\Attribute\DataProvider;
use LogicException;
use ReflectionClass;
use Spryker\ApiPlatform\Contract\Coverage\AnnotationCollector;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Replay\OpenApiExampleReplayContext;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replays the generated OpenAPI example of every servable operation of the resources the class names
 * in {@see \Spryker\ApiPlatform\Contract\Attribute\ReplaysOpenApiExamples}, and fails on a server
 * error. A 4xx is a correct answer to a placeholder value; a 5xx is a bug the example exposed.
 *
 * Each operation is its own data-provider case, so it runs in its own transaction. A suite fills the
 * path variables and fixture-bound values in {@see AbstractOpenApiExampleReplayTestCase::createReplayContext()}.
 */
abstract class AbstractOpenApiExampleReplayTestCase extends StorefrontApiTestCase
{
    protected const string GENERATED_RESOURCE_PATH_TEMPLATE = '%s/src/Generated/Api/%s';

    protected const string GENERATED_RESOURCE_NAMESPACE_TEMPLATE = 'Generated\\Api\\%s';

    protected const string RESOURCE_CLASS_TEMPLATE = '%s\\%s';

    protected const int STATUS_SERVER_ERROR_MIN = 500;

    /**
     * @var array<string, array<string, \Spryker\ApiPlatform\Contract\Replay\ReplayableRequest>> Per test class.
     */
    protected static array $replayableRequests = [];

    abstract protected function createReplayContext(ApiOperation $operation): OpenApiExampleReplayContext;

    /**
     * @return iterable<string, array{string}>
     */
    public static function replayableOperations(): iterable
    {
        foreach (static::buildReplayableRequests() as $dispatchKey => $replayableRequest) {
            yield $dispatchKey => [$dispatchKey];
        }
    }

    #[DataProvider('replayableOperations')]
    public function testGivenTheGeneratedExampleWhenTheOperationIsReplayedThenTheResponseIsNotAServerError(string $dispatchKey): void
    {
        // Arrange
        $replayableRequest = static::buildReplayableRequests()[$dispatchKey];
        $context = $this->createReplayContext($replayableRequest->operation);
        if ($context->skipReason !== null) {
            $this->markTestSkipped($context->skipReason);
        }
        $request = $replayableRequest->resolve($context);
        $this->operationRecorder = ContractCoverageFactory::createOperationCoverageRecorder();
        static::$activeOperationRecorder = $this->operationRecorder;

        // Act
        $response = $this->handleApiRequest($request['method'], $request['uri'], $request['content'], $context->headers);

        // Assert
        $this->assertLessThan(static::STATUS_SERVER_ERROR_MIN, $response->getStatusCode(), $this->describeServerError($request['method'], $request['uri'], $request['content'], $response));
    }

    protected function describeServerError(string $method, string $uri, ?string $content, Response $response): string
    {
        $exceptionClasses = array_filter(array_map(
            static fn ($exchange): ?string => $exchange->exceptionClass,
            $this->operationRecorder?->exchanges() ?? [],
        ));

        return sprintf(
            "The generated example of %s %s answered %d (%s).\nRequest body: %s\nResponse: %s",
            $method,
            $uri,
            $response->getStatusCode(),
            $exceptionClasses === [] ? 'no exception recorded' : implode(', ', $exceptionClasses),
            $content ?? '(none)',
            (string)$response->getContent(),
        );
    }

    /**
     * Built once per class: data providers run before any instance exists.
     *
     * @throws \LogicException
     *
     * @return array<string, \Spryker\ApiPlatform\Contract\Replay\ReplayableRequest>
     */
    protected static function buildReplayableRequests(): array
    {
        if (isset(static::$replayableRequests[static::class])) {
            return static::$replayableRequests[static::class];
        }

        $resourceShortNames = AnnotationCollector::readReplayedResources(new ReflectionClass(static::class));
        if ($resourceShortNames === []) {
            throw new LogicException(sprintf('%s names no resource in #[ReplaysOpenApiExamples].', static::class));
        }

        $schemaTruthLoader = ContractCoverageFactory::createSchemaTruthLoader();
        $requestBuilder = ContractCoverageFactory::createOpenApiExampleRequestBuilder();

        $replayableRequests = [];
        foreach (glob(static::generatedResourceDirectory() . '/*.php') ?: [] as $file) {
            /** @var class-string $resourceClass */
            $resourceClass = sprintf(static::RESOURCE_CLASS_TEMPLATE, static::generatedResourceNamespace(), basename($file, '.php'));
            if (!class_exists($resourceClass) || !in_array($schemaTruthLoader->shortName($resourceClass), $resourceShortNames, true)) {
                continue;
            }

            foreach ($requestBuilder->build($resourceClass) as $replayableRequest) {
                $replayableRequests[$replayableRequest->operation->dispatchKey()] = $replayableRequest;
            }
        }

        ksort($replayableRequests);

        return static::$replayableRequests[static::class] = $replayableRequests;
    }

    /**
     * Read at data-provider time, before any kernel boots, so it falls back to the working directory.
     */
    protected static function generatedResourceDirectory(): string
    {
        $applicationRoot = defined('APPLICATION_ROOT_DIR') ? APPLICATION_ROOT_DIR : (string)getcwd();

        return sprintf(static::GENERATED_RESOURCE_PATH_TEMPLATE, $applicationRoot, static::API_TYPE);
    }

    protected static function generatedResourceNamespace(): string
    {
        return sprintf(static::GENERATED_RESOURCE_NAMESPACE_TEMPLATE, static::API_TYPE);
    }
}
