<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ReflectionClass;
use Spryker\ApiPlatform\Exception\AmbiguousNotFoundErrorDeclarationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the resource-specific "not found" error behind an operation, following the two ways the modules declare it.
 * `null` means the resource declares no pair; a malformed declaration (an attribute that cannot be instantiated, a
 * constant that cannot be read) is a programmer error and surfaces on both the runtime and the documentation path,
 * because the same lookup serves {@see \Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber} and the
 * OpenAPI 404 example, and a generic body would hide it from both. A provider declares at most one pair: it returns
 * `null` for a missing item without saying which entity is missing, so a second pair cannot be told apart from the
 * first and is refused rather than picked by declaration order.
 *
 * A provider may carry the pair itself through `*NOT_FOUND*MESSAGE` / `*NOT_FOUND*CODE` constants (for example
 * `ERROR_MESSAGE_CUSTOMER_NOT_FOUND` and `ERROR_CODE_CUSTOMER_NOT_FOUND`): the runtime renders that error for a
 * missing item and the OpenAPI document uses the same pair for its 404 example, so the two cannot drift apart.
 *
 * Or an operation declares the error its module raises itself, as the `notFoundCode` and `notFoundMessage` extra
 * properties of the resource YAML. The declaration is per operation because the collection of a sub-resource misses
 * its parent while the item misses itself, and it wins over the provider constants. It is documentation: the message
 * is shown as written, so an author names the URI variables in it — `Customer with reference "{customerReference}"
 * was not found.` — where the runtime prints the values.
 */
class ProviderNotFoundErrorResolver
{
    public const string EXTRA_PROPERTY_NOT_FOUND_CODE = 'notFoundCode';

    public const string EXTRA_PROPERTY_NOT_FOUND_MESSAGE = 'notFoundMessage';

    protected const string CONSTANT_FRAGMENT_NOT_FOUND = 'NOT_FOUND';

    protected const string CONSTANT_FRAGMENT_MESSAGE = 'MESSAGE';

    protected const string CONSTANT_FRAGMENT_CODE = 'CODE';

    protected const string MESSAGE_AMBIGUOUS_DECLARATION = 'Provider "%s" declares more than one not-found pair (%s); a provider returning null for a missing item cannot tell which entity is missing. Keep a single pair, or raise the module\'s own not-found exception and declare `notFoundCode` / `notFoundMessage` per operation in the resource YAML.';

    /**
     * @return array{status: int, detail: string, message: string, code: string}|null
     */
    public function resolveByResourceClass(string $resourceClass): ?array
    {
        if ($resourceClass === '' || !class_exists($resourceClass)) {
            return null;
        }

        $attributes = (new ReflectionClass($resourceClass))->getAttributes(ApiResource::class);

        if ($attributes === []) {
            return null;
        }

        $providerClass = $attributes[0]->newInstance()->getProvider();

        if (!is_string($providerClass)) {
            return null;
        }

        return $this->resolveByProviderClass($providerClass);
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\AmbiguousNotFoundErrorDeclarationException
     *
     * @return array{status: int, detail: string, message: string, code: string}|null
     */
    public function resolveByProviderClass(string $providerClass): ?array
    {
        if ($providerClass === '' || !class_exists($providerClass)) {
            return null;
        }

        $pairs = $this->collectNotFoundPairs(new ReflectionClass($providerClass));

        if (count($pairs) > 1) {
            throw new AmbiguousNotFoundErrorDeclarationException(sprintf(
                static::MESSAGE_AMBIGUOUS_DECLARATION,
                $providerClass,
                implode(', ', array_keys($pairs)),
            ));
        }

        $pair = reset($pairs);

        return $pair === false ? null : $this->createError($pair[0], $pair[1]);
    }

    /**
     * @param \ReflectionClass<object> $providerReflection
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function collectNotFoundPairs(ReflectionClass $providerReflection): array
    {
        $pairs = [];

        foreach ($providerReflection->getReflectionConstants() as $constant) {
            $constantName = $constant->getName();

            if (!str_contains($constantName, static::CONSTANT_FRAGMENT_NOT_FOUND) || !str_contains($constantName, static::CONSTANT_FRAGMENT_MESSAGE)) {
                continue;
            }

            $codeConstantName = str_replace(static::CONSTANT_FRAGMENT_MESSAGE, static::CONSTANT_FRAGMENT_CODE, $constantName);

            if (!$providerReflection->hasConstant($codeConstantName)) {
                continue;
            }

            $pairs[$constantName] = [(string)$constant->getValue(), (string)$providerReflection->getConstant($codeConstantName)];
        }

        return $pairs;
    }

    /**
     * @return array{status: int, detail: string, message: string, code: string}|null
     */
    public function resolveForOperation(HttpOperation $httpOperation): ?array
    {
        $declaredError = $this->resolveByExtraProperties($httpOperation->getExtraProperties() ?? []);

        if ($declaredError !== null) {
            return $declaredError;
        }

        $providerClass = $httpOperation->getProvider();

        if (!is_string($providerClass)) {
            return null;
        }

        return $this->resolveByProviderClass($providerClass);
    }

    /**
     * @param array<string, mixed> $extraProperties
     *
     * @return array{status: int, detail: string, message: string, code: string}|null
     */
    protected function resolveByExtraProperties(array $extraProperties): ?array
    {
        $code = $extraProperties[static::EXTRA_PROPERTY_NOT_FOUND_CODE] ?? null;
        $message = $extraProperties[static::EXTRA_PROPERTY_NOT_FOUND_MESSAGE] ?? null;

        if ((!is_string($code) && !is_int($code)) || (string)$code === '' || !is_string($message) || $message === '') {
            return null;
        }

        return $this->createError($message, (string)$code);
    }

    /**
     * @return array{status: int, detail: string, message: string, code: string}
     */
    protected function createError(string $message, string $code): array
    {
        return [
            'status' => Response::HTTP_NOT_FOUND,
            'detail' => $message,
            'message' => $message,
            'code' => $code,
        ];
    }
}
