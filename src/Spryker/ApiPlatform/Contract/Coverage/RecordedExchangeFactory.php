<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use ApiPlatform\Validator\Exception\ConstraintViolationListAwareExceptionInterface;
use ReflectionClass;
use Spryker\ApiPlatform\Exception\LossyIntegerConversionException;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\ApiPlatform\Validation\SynthesizedViolation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException as PropertyAccessInvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Throwable;

/**
 * Turns one request, its response and the exception the kernel saw on the way into a
 * {@see RecordedExchange}. Pure: it reads only what it is handed, so every runtime check can be
 * tested against fabricated requests.
 *
 * The exception is the original one, caught before the exception subscriber turns it into the
 * JSON:API error envelope, because the envelope carries neither the violated rule nor whether an
 * access decision denied the request.
 *
 * Every violation is structured, with one rule and the full attribute path; the error detail text is
 * never read for it. A 422 has three sources: the validator's violation list, a denormalization type
 * error in the exception chain, and the errors the subscriber's augmenters synthesize, which they
 * leave on the request ({@see \Spryker\ApiPlatform\Validation\SynthesizedViolation}). What none of
 * them names proves nothing.
 */
class RecordedExchangeFactory
{
    protected const string JSON_API_KEY_DATA = 'data';

    protected const string JSON_API_KEY_ATTRIBUTES = 'attributes';

    protected const string JSON_API_KEY_ERRORS = 'errors';

    protected const string JSON_API_KEY_CODE = 'code';

    protected const string JSON_API_KEY_DETAIL = 'detail';

    protected const string QUERY_INCLUDE = 'include';

    /**
     * @var non-empty-string
     */
    protected const string INCLUDE_SEPARATOR = ',';

    /**
     * @var non-empty-string
     */
    protected const string INCLUDE_PATH_SEPARATOR = '.';

    protected const string HEADER_AUTHORIZATION = 'Authorization';

    protected const string JSON_CONTENT_TYPE_FRAGMENT = 'json';

    protected const string RULE_TYPE = 'Type';

    /**
     * @var non-empty-string
     */
    protected const string PATH_SEPARATOR = '.';

    /**
     * PropertyAccessor's type-mismatch message: the expected type and the property path, relative to
     * the object being denormalized.
     */
    protected const string PATTERN_PROPERTY_ACCESS_TYPE_ERROR = '/Expected argument of type "([^"]+)", "[^"]+" given at property path "([^"]+)"/';

    protected const string CLASS_NAME_SEPARATOR = '\\';

    public function __construct(protected ConstraintRuleMapper $ruleMapper)
    {
    }

    /**
     * @param \Spryker\ApiPlatform\Contract\Coverage\ApiOperation $operation The matched operation, without a status.
     */
    public function create(ApiOperation $operation, Request $request, Response $response, ?Throwable $exception = null): RecordedExchange
    {
        $errors = $this->decodeErrors($response);
        $requestAttributes = $this->decodeRequestAttributes($request);

        return new RecordedExchange(
            $operation,
            $response->getStatusCode(),
            $requestAttributes,
            $this->includeRelationshipNames($request),
            array_values(array_filter(array_map(
                static fn (array $error): ?string => isset($error[static::JSON_API_KEY_CODE]) ? (string)$error[static::JSON_API_KEY_CODE] : null,
                $errors,
            ), static fn (?string $code): bool => $code !== null)),
            array_values(array_filter(array_map(
                static fn (array $error): ?string => isset($error[static::JSON_API_KEY_DETAIL]) ? (string)$error[static::JSON_API_KEY_DETAIL] : null,
                $errors,
            ), static fn (?string $detail): bool => $detail !== null)),
            [
                ...$this->constraintViolations($exception),
                ...$this->denormalizationViolations($exception, $requestAttributes),
                ...$this->synthesizedViolations($request),
            ],
            $this->isAccessDenied($exception),
            $request->headers->has(static::HEADER_AUTHORIZATION),
            $exception === null ? null : $exception::class,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeRequestAttributes(Request $request): array
    {
        if (!str_contains((string)$request->headers->get('Content-Type', ''), static::JSON_CONTENT_TYPE_FRAGMENT)) {
            return [];
        }

        $document = json_decode((string)$request->getContent(), true);
        $attributes = is_array($document) ? ($document[static::JSON_API_KEY_DATA][static::JSON_API_KEY_ATTRIBUTES] ?? null) : null;

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * A nested include (`items.concrete-products`) counts for its first relationship: that is the
     * one the requested resource declares.
     *
     * @return array<string>
     */
    protected function includeRelationshipNames(Request $request): array
    {
        $include = $request->query->all()[static::QUERY_INCLUDE] ?? null;
        if (!is_string($include) || $include === '') {
            return [];
        }

        $names = [];
        foreach (explode(static::INCLUDE_SEPARATOR, $include) as $entry) {
            $name = trim(explode(static::INCLUDE_PATH_SEPARATOR, trim($entry))[0]);
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function decodeErrors(Response $response): array
    {
        if (!str_contains((string)$response->headers->get('Content-Type', ''), static::JSON_CONTENT_TYPE_FRAGMENT)) {
            return [];
        }

        $document = json_decode((string)$response->getContent(), true);
        $errors = is_array($document) ? ($document[static::JSON_API_KEY_ERRORS] ?? null) : null;

        return is_array($errors) ? array_values(array_filter($errors, 'is_array')) : [];
    }

    /**
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\RecordedConstraintViolation>
     */
    protected function constraintViolations(?Throwable $exception): array
    {
        $violationAware = $this->findInChain(
            $exception,
            static fn (Throwable $throwable): bool => $throwable instanceof ConstraintViolationListAwareExceptionInterface,
        );
        if (!$violationAware instanceof ConstraintViolationListAwareExceptionInterface) {
            return [];
        }

        $violations = [];
        foreach ($violationAware->getConstraintViolationList() as $violation) {
            $constraint = $violation instanceof ConstraintViolation ? $violation->getConstraint() : null;

            $violations[] = new RecordedConstraintViolation(
                ValidationAttributePath::normalize($violation->getPropertyPath()),
                $constraint === null
                    ? $this->ruleForConstraintlessViolation($violation->getCode())
                    : $this->ruleMapper->ruleForViolation(
                        (new ReflectionClass($constraint))->getShortName(),
                        $violation->getCode(),
                        $violation->getParameters(),
                    ),
                $violation->getPropertyPath(),
            );
        }

        return $violations;
    }

    /**
     * API Platform turns each collected denormalization error into a violation with no constraint
     * behind it, carrying the `Type` invalid-type code.
     */
    protected function ruleForConstraintlessViolation(?string $violationCode): string
    {
        return $violationCode === Type::INVALID_TYPE_ERROR ? static::RULE_TYPE : '';
    }

    /**
     * A value the serializer could not assign to its property is a `Type` violation of that property.
     * The serializer's own exception carries the full path. A bare PropertyAccessor exception carries
     * only the path inside the object being built, so it counts only where exactly one submitted path
     * ends in it. A value bound for a nested object class says nothing about which leaf was wrong; the
     * nested-object augmenter names the leaf instead.
     *
     * @param array<string, mixed> $requestAttributes
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\RecordedConstraintViolation>
     */
    protected function denormalizationViolations(?Throwable $exception, array $requestAttributes): array
    {
        $notNormalizable = $this->findInChain(
            $exception,
            fn (Throwable $throwable): bool => $throwable instanceof NotNormalizableValueException
                && $throwable->getPath() !== null
                && !$this->expectsObject($throwable->getExpectedTypes() ?? []),
        );
        if ($notNormalizable instanceof NotNormalizableValueException) {
            return [$this->typeViolation((string)$notNormalizable->getPath())];
        }

        $relativePath = $this->propertyAccessRelativePath($exception);
        if ($relativePath === null) {
            return [];
        }

        $fullPath = $this->resolveSubmittedPath($relativePath, $requestAttributes);

        return $fullPath === null ? [] : [$this->typeViolation($fullPath)];
    }

    protected function propertyAccessRelativePath(?Throwable $exception): ?string
    {
        $lossy = $this->findInChain($exception, static fn (Throwable $throwable): bool => $throwable instanceof LossyIntegerConversionException);
        if ($lossy instanceof LossyIntegerConversionException) {
            return ValidationAttributePath::normalize($lossy->propertyPath);
        }

        $typeError = $this->findInChain(
            $exception,
            static fn (Throwable $throwable): bool => $throwable instanceof PropertyAccessInvalidArgumentException
                && preg_match(static::PATTERN_PROPERTY_ACCESS_TYPE_ERROR, $throwable->getMessage()) === 1,
        );
        if ($typeError === null) {
            return null;
        }

        preg_match(static::PATTERN_PROPERTY_ACCESS_TYPE_ERROR, $typeError->getMessage(), $matches);

        return $this->expectsObject([$matches[1]]) ? null : ValidationAttributePath::normalize($matches[2]);
    }

    /**
     * @param array<string> $expectedTypes
     */
    protected function expectsObject(array $expectedTypes): bool
    {
        foreach ($expectedTypes as $expectedType) {
            if (str_contains($expectedType, static::CLASS_NAME_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $requestAttributes
     */
    protected function resolveSubmittedPath(string $relativePath, array $requestAttributes): ?string
    {
        $candidates = array_values(array_filter(
            array_unique($this->submittedPaths($requestAttributes, '')),
            static fn (string $path): bool => $path === $relativePath || str_ends_with($path, static::PATH_SEPARATOR . $relativePath),
        ));

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * Every path the request body submits, at any depth, with list indexes dropped.
     *
     * @param array<mixed> $attributes
     *
     * @return array<string>
     */
    protected function submittedPaths(array $attributes, string $prefix): array
    {
        $paths = [];

        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $paths = [...$paths, ...(is_array($value) ? $this->submittedPaths($value, $prefix) : [])];

                continue;
            }

            $path = $prefix === '' ? $name : $prefix . static::PATH_SEPARATOR . $name;
            $paths[] = $path;

            if (is_array($value)) {
                $paths = [...$paths, ...$this->submittedPaths($value, $path)];
            }
        }

        return $paths;
    }

    protected function typeViolation(string $propertyPath): RecordedConstraintViolation
    {
        return new RecordedConstraintViolation(ValidationAttributePath::normalize($propertyPath), static::RULE_TYPE, $propertyPath);
    }

    /**
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\RecordedConstraintViolation>
     */
    protected function synthesizedViolations(Request $request): array
    {
        $violations = [];

        foreach ($request->attributes->get(RequestAttribute::SYNTHESIZED_VIOLATIONS, []) as $synthesized) {
            if (!$synthesized instanceof SynthesizedViolation) {
                continue;
            }

            $violations[] = new RecordedConstraintViolation(
                ValidationAttributePath::normalize($synthesized->propertyPath),
                $this->ruleMapper->ruleForViolation($synthesized->constraintShortName, null),
                $synthesized->propertyPath,
            );
        }

        return $violations;
    }

    protected function isAccessDenied(?Throwable $exception): bool
    {
        return $this->findInChain(
            $exception,
            static fn (Throwable $throwable): bool => $throwable instanceof AccessDeniedException || $throwable instanceof AccessDeniedHttpException,
        ) !== null;
    }

    /**
     * @param callable(\Throwable): bool $predicate
     */
    protected function findInChain(?Throwable $throwable, callable $predicate): ?Throwable
    {
        while ($throwable !== null) {
            if ($predicate($throwable)) {
                return $throwable;
            }
            $throwable = $throwable->getPrevious();
        }

        return null;
    }
}
