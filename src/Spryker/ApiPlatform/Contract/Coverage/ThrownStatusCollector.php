<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ReflectionMethod;
use Spryker\ApiPlatform\State\Processor\AbstractProcessor;
use Spryker\ApiPlatform\State\Provider\AbstractProvider;

/**
 * Derives the error statuses each operation's processor and provider can throw, which the
 * operation's schema has to declare.
 *
 * A processor or provider built on {@see AbstractProcessor} / {@see AbstractProvider} answers each
 * operation type from its own method - `processPost()`, `provideCollection()`, ... - so an operation
 * owes only what that method can throw. One that dispatches by itself is read from `process()` /
 * `provide()`, and every operation it serves owes all of it.
 *
 * An HTTP exception whose status cannot be read is collected as an {@see UnreadableThrownStatus}.
 */
class ThrownStatusCollector
{
    protected const int STATUS_CLIENT_ERROR_MIN = 400;

    protected const string METHOD_PROCESS = 'process';

    protected const string METHOD_PROVIDE = 'provide';

    protected const string METHOD_PROVIDE_ITEM = 'provideItem';

    protected const string METHOD_PROVIDE_COLLECTION = 'provideCollection';

    /**
     * @var array<class-string<\ApiPlatform\Metadata\HttpOperation>, string>
     */
    protected const array PROCESSOR_METHOD_BY_OPERATION_CLASS = [
        Post::class => 'processPost',
        Patch::class => 'processPatch',
        Delete::class => 'processDelete',
    ];

    public function __construct(protected ThrownStatusAnalyzer $thrownStatusAnalyzer)
    {
    }

    /**
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ThrownStatus|\Spryker\ApiPlatform\Contract\Coverage\UnreadableThrownStatus>
     */
    public function collect(HttpOperation $operation, ApiResource $apiResource, ApiOperation $apiOperation): array
    {
        $thrownStatuses = [];

        foreach ($this->entryMethods($operation, $apiResource) as [$className, $methodName]) {
            foreach ($this->thrownStatusAnalyzer->thrownStatuses($className, $methodName) as $status) {
                if ($status >= static::STATUS_CLIENT_ERROR_MIN) {
                    $thrownStatuses[] = new ThrownStatus($apiOperation->dispatchKey(), $status);
                }
            }

            foreach ($this->thrownStatusAnalyzer->unreadableExceptionClassNames($className, $methodName) as $exceptionClassName) {
                $thrownStatuses[] = new UnreadableThrownStatus($apiOperation->dispatchKey(), $exceptionClassName);
            }
        }

        return $thrownStatuses;
    }

    /**
     * @return array<array{class-string, string}>
     */
    protected function entryMethods(HttpOperation $operation, ApiResource $apiResource): array
    {
        $entryMethods = [];

        $processorClassName = $this->className($operation->getProcessor() ?? $apiResource->getProcessor());
        $processorMethod = $processorClassName === null ? null : $this->processorMethod($processorClassName, $operation);
        if ($processorClassName !== null && $processorMethod !== null) {
            $entryMethods[] = [$processorClassName, $processorMethod];
        }

        $providerClassName = $this->className($operation->getProvider() ?? $apiResource->getProvider());
        $providerMethod = $providerClassName === null ? null : $this->providerMethod($providerClassName, $operation);
        if ($providerClassName !== null && $providerMethod !== null) {
            $entryMethods[] = [$providerClassName, $providerMethod];
        }

        return $entryMethods;
    }

    /**
     * @param class-string $processorClassName
     */
    protected function processorMethod(string $processorClassName, HttpOperation $operation): ?string
    {
        if (!$this->dispatchesThrough($processorClassName, static::METHOD_PROCESS, AbstractProcessor::class)) {
            return static::METHOD_PROCESS;
        }

        foreach (static::PROCESSOR_METHOD_BY_OPERATION_CLASS as $operationClassName => $methodName) {
            if ($operation instanceof $operationClassName) {
                return $methodName;
            }
        }

        return null;
    }

    /**
     * A POST creates what no provider can load, and an operation declaring `read: false` skips the
     * provider altogether.
     *
     * @param class-string $providerClassName
     */
    protected function providerMethod(string $providerClassName, HttpOperation $operation): ?string
    {
        if ($operation instanceof Post || $operation->canRead() === false) {
            return null;
        }

        if (!$this->dispatchesThrough($providerClassName, static::METHOD_PROVIDE, AbstractProvider::class)) {
            return static::METHOD_PROVIDE;
        }

        return $operation instanceof CollectionOperationInterface ? static::METHOD_PROVIDE_COLLECTION : static::METHOD_PROVIDE_ITEM;
    }

    /**
     * @param class-string $className
     * @param class-string $templateClassName
     */
    protected function dispatchesThrough(string $className, string $dispatchMethodName, string $templateClassName): bool
    {
        return is_a($className, $templateClassName, true)
            && (new ReflectionMethod($className, $dispatchMethodName))->getDeclaringClass()->getName() === $templateClassName;
    }

    /**
     * @return class-string|null
     */
    protected function className(mixed $stateHandler): ?string
    {
        return is_string($stateHandler) && class_exists($stateHandler) ? $stateHandler : null;
    }
}
