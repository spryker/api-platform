<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequiredResponseAttributes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation;

/**
 * Reads `#[CoversApiOperation]` / `#[CoversApiValidation]` / `#[CoversApiRequiredResponseAttributes]`
 * declarations off the public `test*` methods of the given test classes. Reflection only — no test
 * is instantiated or run, so it is cheap and boot-free.
 *
 * A validation declaration and a response-attribute marker both bind to the `#[CoversApiOperation]`
 * on the same method, so both dimensions are per operation. Either one without that declaration is
 * an authoring error and fails the collection loudly.
 */
class AnnotationCollector
{
    protected const string TEST_METHOD_PREFIX = 'test';

    /**
     * @param array<class-string> $testClasses
     */
    public function collect(array $testClasses): CollectedAnnotations
    {
        $operations = [];
        $validations = [];
        $responseAttributeCoveredOperations = [];
        $operationDeclarers = [];

        foreach ($testClasses as $testClass) {
            $collected = $this->collectFromClass($testClass);

            $operations = array_merge($operations, $collected->declaredOperations);
            $validations = array_merge($validations, $collected->declaredValidations);
            $responseAttributeCoveredOperations = array_merge(
                $responseAttributeCoveredOperations,
                $collected->responseAttributeCoveredOperations,
            );

            foreach ($collected->operationDeclarers as $dispatchKey => $declarers) {
                $operationDeclarers[$dispatchKey] = array_merge($operationDeclarers[$dispatchKey] ?? [], $declarers);
            }
        }

        return new CollectedAnnotations(
            $operations,
            $validations,
            array_values(array_unique($responseAttributeCoveredOperations)),
            $operationDeclarers,
        );
    }

    /**
     * @param class-string $testClass
     *
     * @throws \LogicException
     */
    protected function collectFromClass(string $testClass): CollectedAnnotations
    {
        $operations = [];
        $validations = [];
        $responseAttributeCoveredOperations = [];
        $operationDeclarers = [];

        foreach ($this->testMethods($testClass) as $method) {
            $methodOperations = static::readOperations($method);

            $operations = array_merge($operations, $methodOperations);
            $validations = array_merge($validations, static::readValidations($method, $methodOperations));

            $successOperations = static::uniqueByDispatchKey(array_values(array_filter(
                $methodOperations,
                static fn (ApiOperation $operation): bool => $operation->status === null,
            )));
            $isMarked = static::hasResponseAttributeMarker($method);
            if ($isMarked && $successOperations === []) {
                throw new LogicException(sprintf(
                    '#[CoversApiRequiredResponseAttributes] on %s::%s() needs a success #[CoversApiOperation] on the same'
                    . ' method — response attribute coverage is per operation, so the marked test must name the operation'
                    . ' whose response it round-trips.',
                    $testClass,
                    $method->getName(),
                ));
            }

            foreach ($successOperations as $operation) {
                $operationDeclarers[$operation->dispatchKey()][] = $testClass . '::' . $method->getName();
                if ($isMarked) {
                    $responseAttributeCoveredOperations[] = $operation->dispatchKey();
                }
            }
        }

        return new CollectedAnnotations($operations, $validations, $responseAttributeCoveredOperations, $operationDeclarers);
    }

    /**
     * @param class-string $testClass
     *
     * @return array<\ReflectionMethod>
     */
    protected function testMethods(string $testClass): array
    {
        return array_values(array_filter(
            (new ReflectionClass($testClass))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), static::TEST_METHOD_PREFIX),
        ));
    }

    /**
     * Collects the operation declarations of a single method, used by the runtime verifier to learn
     * what the currently running test claims to cover.
     *
     * @param class-string $testClass
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    public static function operationsForMethod(string $testClass, string $method): array
    {
        $reflectionClass = new ReflectionClass($testClass);
        if (!$reflectionClass->hasMethod($method)) {
            return [];
        }

        return static::readOperations($reflectionClass->getMethod($method));
    }

    /**
     * Whether the currently running test method claims the response attribute coverage of its
     * operation. Read by {@see \SprykerTest\ApiPlatform\Test\AbstractApiTestCase} in its
     * post-conditions, which enforces the claim against what the method actually asserted.
     *
     * @param class-string $testClass
     */
    public static function coversRequiredResponseAttributes(string $testClass, string $method): bool
    {
        $reflectionClass = new ReflectionClass($testClass);
        if (!$reflectionClass->hasMethod($method)) {
            return false;
        }

        return static::hasResponseAttributeMarker($reflectionClass->getMethod($method));
    }

    protected static function hasResponseAttributeMarker(ReflectionMethod $method): bool
    {
        return $method->getAttributes(CoversApiRequiredResponseAttributes::class) !== [];
    }

    /**
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected static function readOperations(ReflectionMethod $method): array
    {
        $operations = [];
        foreach ($method->getAttributes(CoversApiOperation::class) as $attribute) {
            /** @var \Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation $instance */
            $instance = $attribute->newInstance();
            $operations[] = new ApiOperation($instance->verb, UriTemplateNormalizer::normalize($instance->uriTemplate), $instance->status);
        }

        return $operations;
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $methodOperations
     *
     * @throws \LogicException
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    protected static function readValidations(ReflectionMethod $method, array $methodOperations): array
    {
        $validationAttributes = $method->getAttributes(CoversApiValidation::class);
        if ($validationAttributes === []) {
            return [];
        }

        $boundOperations = static::uniqueByDispatchKey($methodOperations);
        if ($boundOperations === []) {
            throw new LogicException(sprintf(
                '#[CoversApiValidation] on %s::%s() needs a #[CoversApiOperation] on the same method'
                . ' — validation coverage is per operation, so the rule must name the operation it is exercised against.',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            ));
        }

        $validations = [];
        foreach ($validationAttributes as $attribute) {
            /** @var \Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation $instance */
            $instance = $attribute->newInstance();
            foreach ($boundOperations as $operation) {
                $validations[] = new ValidationConstraint(
                    $instance->resource,
                    $instance->attribute,
                    $instance->ruleIdentifier(),
                    $operation->verb,
                    $operation->uriTemplate,
                );
            }
        }

        return $validations;
    }

    /**
     * A method may declare the same operation twice — once for the success response and once with a
     * `status` for a declared error response. The validation binds to the operation's dispatch
     * identity, so such duplicates collapse to one.
     *
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $operations
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected static function uniqueByDispatchKey(array $operations): array
    {
        $unique = [];
        foreach ($operations as $operation) {
            $unique[$operation->dispatchKey()] ??= $operation;
        }

        return array_values($unique);
    }
}
