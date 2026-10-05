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
use Spryker\ApiPlatform\Contract\Attribute\CoversApiIncludes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequestAttributes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequiredResponseAttributes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation;
use Spryker\ApiPlatform\Contract\Attribute\ReplaysOpenApiExamples;

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

    protected const int STATUS_CLIENT_ERROR_MIN = 400;

    /**
     * @var array<string>
     */
    protected const array INPUT_VERBS = ['POST', 'PUT', 'PATCH'];

    /**
     * @param array<class-string> $testClasses
     */
    public function collect(array $testClasses): CollectedAnnotations
    {
        $operations = [];
        $validations = [];
        $responseAttributeCoveredOperations = [];
        $operationDeclarers = [];
        $errorResponseDeclarers = [];
        $requestAttributeClaims = [];
        $includeClaims = [];
        $replayedResources = [];

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
            foreach ($collected->errorResponseDeclarers as $errorResponseKey => $declarers) {
                $errorResponseDeclarers[$errorResponseKey] = array_merge($errorResponseDeclarers[$errorResponseKey] ?? [], $declarers);
            }
            $requestAttributeClaims = static::mergeRequestAttributeClaims($requestAttributeClaims, $collected->requestAttributeClaims);
            $includeClaims = static::mergeIncludeClaims($includeClaims, $collected->includeClaims);
            $replayedResources = [...$replayedResources, ...$collected->replayedResources];
        }

        return new CollectedAnnotations(
            $operations,
            $validations,
            array_values(array_unique($responseAttributeCoveredOperations)),
            $operationDeclarers,
            $errorResponseDeclarers,
            $requestAttributeClaims,
            $includeClaims,
            array_values(array_unique($replayedResources)),
        );
    }

    /**
     * @param array<string, array<string>> $claims
     * @param array<string, array<string>> $additionalClaims
     *
     * @return array<string, array<string>>
     */
    protected static function mergeIncludeClaims(array $claims, array $additionalClaims): array
    {
        foreach ($additionalClaims as $dispatchKey => $relationshipNames) {
            $claims[$dispatchKey] = array_values(array_unique([...$claims[$dispatchKey] ?? [], ...$relationshipNames]));
        }

        return $claims;
    }

    /**
     * @param array<string, array<string>|true> $claims
     * @param array<string, array<string>|true> $additionalClaims
     *
     * @return array<string, array<string>|true>
     */
    protected static function mergeRequestAttributeClaims(array $claims, array $additionalClaims): array
    {
        foreach ($additionalClaims as $dispatchKey => $paths) {
            $existing = $claims[$dispatchKey] ?? [];
            $claims[$dispatchKey] = $existing === true || $paths === true
                ? true
                : array_values(array_unique([...$existing, ...$paths]));
        }

        return $claims;
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
        $errorResponseDeclarers = [];
        $requestAttributeClaims = [];
        $includeClaims = [];

        foreach ($this->testMethods($testClass) as $method) {
            $methodOperations = static::readOperations($method);

            foreach ($methodOperations as $operation) {
                if ($operation->status !== null && $operation->code === null) {
                    $errorResponseDeclarers[$operation->key()][] = $testClass . '::' . $method->getName();
                }
            }

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

            $requestAttributeClaims = static::mergeRequestAttributeClaims($requestAttributeClaims, static::readRequestAttributeClaims($method, $methodOperations));
            $includeClaims = static::mergeIncludeClaims($includeClaims, static::readIncludeClaims($method, $methodOperations));
        }

        return new CollectedAnnotations(
            $operations,
            $validations,
            $responseAttributeCoveredOperations,
            $operationDeclarers,
            $errorResponseDeclarers,
            $requestAttributeClaims,
            $includeClaims,
            static::readReplayedResources(new ReflectionClass($testClass)),
        );
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
     * The validation declarations of a single method, bound to its operations, which the runtime
     * holds to the violations the method's requests actually raised.
     *
     * @param class-string $testClass
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    public static function validationsForMethod(string $testClass, string $method): array
    {
        $reflectionClass = new ReflectionClass($testClass);
        if (!$reflectionClass->hasMethod($method)) {
            return [];
        }

        $reflectionMethod = $reflectionClass->getMethod($method);

        return static::readValidations($reflectionMethod, static::readOperations($reflectionMethod));
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

    /**
     * The request attribute paths the method claims per input operation it declares, `true` where
     * a marker without paths claims them all. Read by the runtime to know what the method's
     * successful requests have to send.
     *
     * @param class-string $testClass
     *
     * @return array<string, array<string>|true>
     */
    public static function requestAttributeClaimsForMethod(string $testClass, string $method): array
    {
        $reflectionClass = new ReflectionClass($testClass);
        if (!$reflectionClass->hasMethod($method)) {
            return [];
        }

        $reflectionMethod = $reflectionClass->getMethod($method);

        return static::readRequestAttributeClaims($reflectionMethod, static::readOperations($reflectionMethod));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $methodOperations
     *
     * @throws \LogicException
     *
     * @return array<string, array<string>|true>
     */
    protected static function readRequestAttributeClaims(ReflectionMethod $method, array $methodOperations): array
    {
        $markers = $method->getAttributes(CoversApiRequestAttributes::class);
        if ($markers === []) {
            return [];
        }

        $inputOperations = static::uniqueByDispatchKey(array_values(array_filter(
            $methodOperations,
            static fn (ApiOperation $operation): bool => $operation->status === null && in_array(strtoupper($operation->verb), static::INPUT_VERBS, true),
        )));
        if ($inputOperations === []) {
            throw new LogicException(sprintf(
                '#[CoversApiRequestAttributes] on %s::%s() needs a success #[CoversApiOperation] of a POST, PUT or PATCH on the'
                . ' same method - request attribute coverage is per input operation, so the marked test must name the operation'
                . ' its requests write to.',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            ));
        }

        $paths = [];
        foreach ($markers as $marker) {
            /** @var \Spryker\ApiPlatform\Contract\Attribute\CoversApiRequestAttributes $instance */
            $instance = $marker->newInstance();
            if ($instance->paths === []) {
                $paths = true;

                break;
            }
            $paths = [...$paths, ...$instance->paths];
        }

        $claims = [];
        foreach ($inputOperations as $operation) {
            $claims[$operation->dispatchKey()] = $paths === true ? true : array_values(array_unique($paths));
        }

        return $claims;
    }

    /**
     * The relationship names the method claims per success operation it declares, which its
     * requests have to include and its assertions have to prove.
     *
     * @param class-string $testClass
     *
     * @return array<string, array<string>>
     */
    public static function includeClaimsForMethod(string $testClass, string $method): array
    {
        $reflectionClass = new ReflectionClass($testClass);
        if (!$reflectionClass->hasMethod($method)) {
            return [];
        }

        $reflectionMethod = $reflectionClass->getMethod($method);

        return static::readIncludeClaims($reflectionMethod, static::readOperations($reflectionMethod));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $methodOperations
     *
     * @throws \LogicException
     *
     * @return array<string, array<string>>
     */
    protected static function readIncludeClaims(ReflectionMethod $method, array $methodOperations): array
    {
        $markers = $method->getAttributes(CoversApiIncludes::class);
        if ($markers === []) {
            return [];
        }

        $relationshipNames = [];
        foreach ($markers as $marker) {
            /** @var \Spryker\ApiPlatform\Contract\Attribute\CoversApiIncludes $instance */
            $instance = $marker->newInstance();
            $relationshipNames = [...$relationshipNames, ...$instance->relationshipNames];
        }

        $successOperations = static::uniqueByDispatchKey(array_values(array_filter(
            $methodOperations,
            static fn (ApiOperation $operation): bool => $operation->status === null,
        )));
        if ($successOperations === [] || $relationshipNames === []) {
            throw new LogicException(sprintf(
                '#[CoversApiIncludes] on %s::%s() needs a success #[CoversApiOperation] on the same method and at least one'
                . ' relationship name - include coverage is per operation and per relationship, and each relationship needs'
                . ' a fixture of its own.',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            ));
        }

        $claims = [];
        foreach ($successOperations as $operation) {
            $claims[$operation->dispatchKey()] = array_values(array_unique($relationshipNames));
        }

        return $claims;
    }

    /**
     * @param \ReflectionClass<object> $testClass
     *
     * @return array<string>
     */
    public static function readReplayedResources(ReflectionClass $testClass): array
    {
        $resourceShortNames = [];

        foreach ($testClass->getAttributes(ReplaysOpenApiExamples::class) as $attribute) {
            /** @var \Spryker\ApiPlatform\Contract\Attribute\ReplaysOpenApiExamples $instance */
            $instance = $attribute->newInstance();
            $resourceShortNames = [...$resourceShortNames, ...$instance->resourceShortNames];
        }

        return array_values(array_unique($resourceShortNames));
    }

    protected static function hasResponseAttributeMarker(ReflectionMethod $method): bool
    {
        return $method->getAttributes(CoversApiRequiredResponseAttributes::class) !== [];
    }

    /**
     * @throws \LogicException
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected static function readOperations(ReflectionMethod $method): array
    {
        $operations = [];
        foreach ($method->getAttributes(CoversApiOperation::class) as $attribute) {
            /** @var \Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation $instance */
            $instance = $attribute->newInstance();

            if ($instance->code !== null && ($instance->status === null || $instance->status < static::STATUS_CLIENT_ERROR_MIN)) {
                throw new LogicException(sprintf(
                    '#[CoversApiOperation] on %s::%s() names code %s without an error status. An error code narrows one'
                    . ' declared error response, so the declaration needs the 4xx/5xx status the code answers with.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $instance->code,
                ));
            }

            if ($instance->scenario !== null && ($instance->status === null || $instance->status < static::STATUS_CLIENT_ERROR_MIN)) {
                throw new LogicException(sprintf(
                    '#[CoversApiOperation] on %s::%s() names scenario %s without an error status. A scenario is the'
                    . ' situation behind an error response, so the declaration needs the 4xx status that answers it.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $instance->scenario->value,
                ));
            }

            $operations[] = new ApiOperation(
                $instance->verb,
                UriTemplateNormalizer::normalize($instance->uriTemplate),
                $instance->status,
                code: $instance->code,
                scenario: $instance->scenario,
            );
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
