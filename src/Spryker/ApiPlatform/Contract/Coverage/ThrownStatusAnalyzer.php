<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use ArgumentCountError;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Reads, without running anything, which HTTP statuses a method can throw: a `throw` of an HTTP
 * exception it constructs, or of one an exception factory returns, in the method itself or in any
 * method it calls on `$this` or on a collaborator typed as a concrete class.
 *
 * A status is followed through literals, class constants, ternaries, `match` arms and the integer
 * parameters a call site passes or leaves at their default. A status read at runtime - an error
 * mapping, a variable thrown by the caller - is not followed: the error-mappings dimension owns
 * mapped statuses.
 */
class ThrownStatusAnalyzer
{
    protected const int MAXIMUM_CALL_DEPTH = 8;

    protected const string PARAMETER_STATUS_CODE = 'statusCode';

    protected const string VARIABLE_THIS = 'this';

    protected const string CLASS_NAME_STATIC = 'static';

    protected const string CLASS_NAME_SELF = 'self';

    protected const string CLASS_NAME_PARENT = 'parent';

    /**
     * Exceptions the framework answers with a fixed status that no instance built from the defaults
     * reports: one outside the HTTP hierarchy, or one whose constructor requires an argument.
     *
     * @var array<class-string, int>
     */
    protected const array STATUS_BY_EXCEPTION_CLASS = [
        AccessDeniedException::class => Response::HTTP_FORBIDDEN,
        UnauthorizedHttpException::class => Response::HTTP_UNAUTHORIZED,
        MethodNotAllowedHttpException::class => Response::HTTP_METHOD_NOT_ALLOWED,
    ];

    protected ?Parser $parser = null;

    protected NodeFinder $nodeFinder;

    /**
     * Static because the source does not change within a process, and the test runtime builds a
     * fresh runner for each schema sweep: without it every sweep would parse the processors again.
     *
     * @var array<string, array<string, \PhpParser\Node\Stmt\ClassMethod>> File name => `<method name>:<start line>` => method body.
     */
    protected static array $classMethodsByFile = [];

    /**
     * @var array<string, array<int>>
     */
    protected static array $thrownStatusesByMethod = [];

    /**
     * @var array<string, array<class-string>>
     */
    protected static array $unreadableExceptionClassNamesByMethod = [];

    /**
     * The exceptions whose status the method being analyzed throws but that cannot be read.
     *
     * @var array<class-string, true>
     */
    protected array $unreadableExceptionClassNames = [];

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * @param class-string $className
     *
     * @return array<int> Sorted unique statuses.
     */
    public function thrownStatuses(string $className, string $methodName): array
    {
        $this->analyze($className, $methodName);

        return static::$thrownStatusesByMethod[$this->methodKey($className, $methodName)];
    }

    /**
     * The HTTP exceptions the method can throw whose fixed status cannot be read without running
     * anything, because their constructor requires an argument: each has to be listed in
     * {@see static::STATUS_BY_EXCEPTION_CLASS}.
     *
     * @param class-string $className
     *
     * @return array<class-string> Sorted unique class names.
     */
    public function unreadableExceptionClassNames(string $className, string $methodName): array
    {
        $this->analyze($className, $methodName);

        return static::$unreadableExceptionClassNamesByMethod[$this->methodKey($className, $methodName)];
    }

    protected function methodKey(string $className, string $methodName): string
    {
        return $className . '::' . $methodName;
    }

    /**
     * Fills both result caches for the method, once per process.
     *
     * @param class-string $className
     */
    protected function analyze(string $className, string $methodName): void
    {
        $methodKey = $this->methodKey($className, $methodName);
        if (isset(static::$thrownStatusesByMethod[$methodKey])) {
            return;
        }

        $this->unreadableExceptionClassNames = [];
        $method = $this->analyzedMethod($className, $methodName, $className, []);
        $statuses = $method === null ? [] : array_values(array_unique($this->statusesThrownBy($method, [])));
        sort($statuses);
        $unreadableExceptionClassNames = array_keys($this->unreadableExceptionClassNames);
        sort($unreadableExceptionClassNames);

        static::$thrownStatusesByMethod[$methodKey] = $statuses;
        static::$unreadableExceptionClassNamesByMethod[$methodKey] = $unreadableExceptionClassNames;
    }

    /**
     * @param array<string, true> $callPath
     *
     * @return array<int>
     */
    protected function statusesThrownBy(AnalyzedMethod $method, array $callPath): array
    {
        if (isset($callPath[$method->key()]) || count($callPath) >= static::MAXIMUM_CALL_DEPTH) {
            return [];
        }
        $callPath[$method->key()] = true;

        $statuses = [];

        foreach ($this->nodeFinder->find($method->node->stmts ?? [], $this->isThrowOrCall(...)) as $node) {
            if ($node instanceof Throw_) {
                $statuses = [...$statuses, ...$this->statusesOfException($node->expr, $method, $callPath)];

                continue;
            }

            $callee = $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall
                ? $this->callee($node, $method)
                : null;
            if ($callee !== null) {
                $statuses = [...$statuses, ...$this->statusesThrownBy($callee, $callPath)];
            }
        }

        return $statuses;
    }

    protected function isThrowOrCall(Node $node): bool
    {
        return $node instanceof Throw_
            || $node instanceof MethodCall
            || $node instanceof NullsafeMethodCall
            || $node instanceof StaticCall;
    }

    /**
     * The statuses of the exception an expression evaluates to: one it constructs, or the ones an
     * exception factory method returns.
     *
     * @param array<string, true> $callPath
     *
     * @return array<int>
     */
    protected function statusesOfException(Expr $expression, AnalyzedMethod $method, array $callPath): array
    {
        if ($expression instanceof New_) {
            return $this->statusesOfInstantiation($expression, $method);
        }

        if ($expression instanceof Ternary) {
            return [
                ...$this->statusesOfException($expression->if ?? $expression->cond, $method, $callPath),
                ...$this->statusesOfException($expression->else, $method, $callPath),
            ];
        }

        if ($expression instanceof Match_) {
            $statuses = [];
            foreach ($expression->arms as $arm) {
                $statuses = [...$statuses, ...$this->statusesOfException($arm->body, $method, $callPath)];
            }

            return $statuses;
        }

        if (!$expression instanceof MethodCall && !$expression instanceof NullsafeMethodCall && !$expression instanceof StaticCall) {
            return [];
        }

        $factoryMethod = $this->callee($expression, $method);
        if ($factoryMethod === null) {
            return [];
        }

        return $this->statusesReturnedBy($factoryMethod, $callPath);
    }

    /**
     * @param array<string, true> $callPath
     *
     * @return array<int>
     */
    protected function statusesReturnedBy(AnalyzedMethod $factoryMethod, array $callPath): array
    {
        if (isset($callPath[$factoryMethod->key()]) || count($callPath) >= static::MAXIMUM_CALL_DEPTH) {
            return [];
        }
        $callPath[$factoryMethod->key()] = true;

        $statuses = [];

        foreach ($this->nodeFinder->findInstanceOf($factoryMethod->node->stmts ?? [], Return_::class) as $return) {
            if ($return->expr !== null) {
                $statuses = [...$statuses, ...$this->statusesOfException($return->expr, $factoryMethod, $callPath)];
            }
        }

        return $statuses;
    }

    /**
     * @return array<int>
     */
    protected function statusesOfInstantiation(New_ $instantiation, AnalyzedMethod $method): array
    {
        if (!$instantiation->class instanceof Name) {
            return [];
        }

        $exceptionClassName = $this->resolveClassName($instantiation->class, $method);
        if ($exceptionClassName === null || !class_exists($exceptionClassName)) {
            return [];
        }

        foreach (static::STATUS_BY_EXCEPTION_CLASS as $mappedClassName => $status) {
            if (is_a($exceptionClassName, $mappedClassName, true)) {
                return [$status];
            }
        }

        if (!is_subclass_of($exceptionClassName, HttpExceptionInterface::class)) {
            return [];
        }

        foreach ((new ReflectionClass($exceptionClassName))->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->getName() !== static::PARAMETER_STATUS_CODE) {
                continue;
            }

            $argument = $this->argumentFor($instantiation->args, $parameter->getPosition(), $parameter->getName());

            return $argument === null ? [] : $this->resolveStatuses($argument, $method);
        }

        return $this->fixedStatusOf($exceptionClassName);
    }

    /**
     * An HTTP exception whose constructor takes no status answers the one its class fixes, which an
     * instance built from the defaults reports. One whose constructor requires an argument is
     * recorded as unreadable instead.
     *
     * @param class-string $exceptionClassName
     *
     * @return array<int>
     */
    protected function fixedStatusOf(string $exceptionClassName): array
    {
        try {
            $exception = (new ReflectionClass($exceptionClassName))->newInstance();
        } catch (ArgumentCountError) {
            $this->unreadableExceptionClassNames[$exceptionClassName] = true;

            return [];
        }

        return $exception instanceof HttpExceptionInterface ? [$exception->getStatusCode()] : [];
    }

    /**
     * @return array<int>
     */
    protected function resolveStatuses(Expr $expression, AnalyzedMethod $method): array
    {
        if ($expression instanceof Int_) {
            return [$expression->value];
        }

        if ($expression instanceof Variable && is_string($expression->name)) {
            return $method->statusesByParameter[$expression->name] ?? [];
        }

        if ($expression instanceof Ternary) {
            return [
                ...$this->resolveStatuses($expression->if ?? $expression->cond, $method),
                ...$this->resolveStatuses($expression->else, $method),
            ];
        }

        if ($expression instanceof Match_) {
            $statuses = [];
            foreach ($expression->arms as $arm) {
                $statuses = [...$statuses, ...$this->resolveStatuses($arm->body, $method)];
            }

            return $statuses;
        }

        if (!$expression instanceof ClassConstFetch || !$expression->class instanceof Name || !$expression->name instanceof Identifier) {
            return [];
        }

        $className = $this->resolveClassName($expression->class, $method);
        $constantName = $className . '::' . $expression->name->toString();
        if ($className === null || !defined($constantName)) {
            return [];
        }

        $value = constant($constantName);

        return is_int($value) ? [$value] : [];
    }

    protected function callee(MethodCall|NullsafeMethodCall|StaticCall $call, AnalyzedMethod $caller): ?AnalyzedMethod
    {
        if (!$call->name instanceof Identifier) {
            return null;
        }

        $target = $this->callTarget($call, $caller);
        if ($target === null) {
            return null;
        }

        [$lookupClassName, $boundClassName] = $target;

        return $this->analyzedMethod(
            $lookupClassName,
            $call->name->toString(),
            $boundClassName,
            $this->bindArguments($call->args, $lookupClassName, $call->name->toString(), $caller),
        );
    }

    /**
     * The class the called method is looked up on, and the class `$this` and `static` mean inside it.
     *
     * @return array{class-string, class-string}|null
     */
    protected function callTarget(MethodCall|NullsafeMethodCall|StaticCall $call, AnalyzedMethod $caller): ?array
    {
        if ($call instanceof StaticCall) {
            if (!$call->class instanceof Name) {
                return null;
            }

            $className = $this->resolveClassName($call->class, $caller);
            if ($className === null) {
                return null;
            }

            return $this->isLateBound($call->class) ? [$className, $caller->boundClassName] : [$className, $className];
        }

        if ($this->isThis($call->var)) {
            return [$caller->boundClassName, $caller->boundClassName];
        }

        if (!$call->var instanceof PropertyFetch || !$this->isThis($call->var->var) || !$call->var->name instanceof Identifier) {
            return null;
        }

        $collaboratorClassName = $this->propertyClassName($caller->boundClassName, $call->var->name->toString());

        return $collaboratorClassName === null ? null : [$collaboratorClassName, $collaboratorClassName];
    }

    /**
     * A `self::`, `static::` or `parent::` call keeps `$this`; a call on another class name does not.
     */
    protected function isLateBound(Name $name): bool
    {
        return in_array($name->toLowerString(), [static::CLASS_NAME_STATIC, static::CLASS_NAME_SELF, static::CLASS_NAME_PARENT], true);
    }

    protected function isThis(Expr $expression): bool
    {
        return $expression instanceof Variable && $expression->name === static::VARIABLE_THIS;
    }

    /**
     * Only a collaborator typed as a concrete class can be followed; an interface names no body.
     *
     * @param class-string $className
     *
     * @return class-string|null
     */
    protected function propertyClassName(string $className, string $propertyName): ?string
    {
        $reflectionClass = new ReflectionClass($className);
        if (!$reflectionClass->hasProperty($propertyName)) {
            return null;
        }

        $type = $reflectionClass->getProperty($propertyName)->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName())) {
            return null;
        }

        return $type->getName();
    }

    /**
     * @return class-string|null
     */
    protected function resolveClassName(Name $name, AnalyzedMethod $method): ?string
    {
        $className = match ($name->toLowerString()) {
            static::CLASS_NAME_STATIC => $method->boundClassName,
            static::CLASS_NAME_SELF => $method->declaringClassName,
            static::CLASS_NAME_PARENT => get_parent_class($method->declaringClassName) ?: null,
            default => $name->toString(),
        };

        /** @var class-string|null $className */
        return $className !== null && (class_exists($className) || interface_exists($className)) ? $className : null;
    }

    /**
     * The statuses each integer parameter of the callee carries: what the call site passes, else the
     * parameter's default.
     *
     * @param array<\PhpParser\Node> $arguments
     * @param class-string $className
     *
     * @return array<string, array<int>>
     */
    protected function bindArguments(array $arguments, string $className, string $methodName, AnalyzedMethod $caller): array
    {
        if (!method_exists($className, $methodName)) {
            return [];
        }

        $statusesByParameter = [];

        foreach ((new ReflectionMethod($className, $methodName))->getParameters() as $parameter) {
            $argument = $this->argumentFor($arguments, $parameter->getPosition(), $parameter->getName());

            if ($argument !== null) {
                $statusesByParameter[$parameter->getName()] = $this->resolveStatuses($argument, $caller);

                continue;
            }

            if ($parameter->isDefaultValueAvailable() && is_int($parameter->getDefaultValue())) {
                $statusesByParameter[$parameter->getName()] = [$parameter->getDefaultValue()];
            }
        }

        return $statusesByParameter;
    }

    /**
     * @param array<\PhpParser\Node> $arguments
     */
    protected function argumentFor(array $arguments, int $position, string $parameterName): ?Expr
    {
        foreach ($arguments as $argument) {
            if ($argument instanceof Arg && $argument->name?->toString() === $parameterName) {
                return $argument->value;
            }
        }

        $positional = $arguments[$position] ?? null;

        return $positional instanceof Arg && $positional->name === null && !$positional->unpack ? $positional->value : null;
    }

    /**
     * @param class-string $lookupClassName
     * @param class-string $boundClassName
     * @param array<string, array<int>> $statusesByParameter
     */
    protected function analyzedMethod(string $lookupClassName, string $methodName, string $boundClassName, array $statusesByParameter): ?AnalyzedMethod
    {
        if (!method_exists($lookupClassName, $methodName)) {
            return null;
        }

        $reflectionMethod = new ReflectionMethod($lookupClassName, $methodName);
        $fileName = $reflectionMethod->getFileName();
        if ($fileName === false || $reflectionMethod->isAbstract()) {
            return null;
        }

        // A trait method reports the trait's file and lines, so matching on the start line finds the
        // body wherever it is declared.
        $classMethod = $this->classMethods($fileName)[$this->classMethodKey($reflectionMethod->getName(), (int)$reflectionMethod->getStartLine())] ?? null;
        if ($classMethod === null) {
            return null;
        }

        return new AnalyzedMethod($classMethod, $boundClassName, $reflectionMethod->getDeclaringClass()->getName(), $statusesByParameter);
    }

    protected function classMethodKey(string $methodName, int $startLine): string
    {
        return $methodName . ':' . $startLine;
    }

    /**
     * @return array<string, \PhpParser\Node\Stmt\ClassMethod>
     */
    protected function classMethods(string $fileName): array
    {
        if (isset(static::$classMethodsByFile[$fileName])) {
            return static::$classMethodsByFile[$fileName];
        }

        $statements = $this->getParser()->parse((string)file_get_contents($fileName)) ?? [];

        $classMethods = [];
        foreach ($this->nodeFinder->findInstanceOf((new NodeTraverser(new NameResolver()))->traverse($statements), ClassMethod::class) as $classMethod) {
            $classMethods[$this->classMethodKey($classMethod->name->toString(), $classMethod->getStartLine())] = $classMethod;
        }

        static::$classMethodsByFile[$fileName] = $classMethods;

        return $classMethods;
    }

    protected function getParser(): Parser
    {
        if ($this->parser === null) {
            $this->parser = (new ParserFactory())->createForHostVersion();
        }

        return $this->parser;
    }
}
