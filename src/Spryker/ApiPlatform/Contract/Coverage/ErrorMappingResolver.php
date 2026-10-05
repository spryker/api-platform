<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use LogicException;
use ReflectionMethod;
use Throwable;

/**
 * Calls a registered error mapping - `Class::method` - and returns its code/status entries. The
 * class resolves to the project's override first, because a project can add entries, and the API
 * answers what the override maps.
 */
class ErrorMappingResolver
{
    /**
     * @var non-empty-string
     */
    protected const string SOURCE_SEPARATOR = '::';

    /**
     * @var non-empty-string
     */
    protected const string NAMESPACE_SEPARATOR = '\\';

    protected const string ENTRY_KEY_CODE = 'code';

    protected const string ENTRY_KEY_STATUS = 'status';

    /**
     * @param array<string> $projectNamespaces
     */
    public function __construct(protected array $projectNamespaces)
    {
    }

    /**
     * @throws \LogicException
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ErrorMappingEntry>
     */
    public function resolve(string $source): array
    {
        [$declaredClass, $method] = $this->split($source);
        $class = $this->resolveProjectOverride($declaredClass);

        try {
            $reflectionMethod = new ReflectionMethod($class, $method);
            $mapping = $reflectionMethod->isStatic() ? $reflectionMethod->invoke(null) : $reflectionMethod->invoke(new $class());
        } catch (Throwable $throwable) {
            throw new LogicException(sprintf('The error mapping %s could not be read from %s: %s', $source, $class, $throwable->getMessage()), 0, $throwable);
        }

        if (!is_array($mapping)) {
            throw new LogicException(sprintf('The error mapping %s read from %s returns %s instead of an array.', $source, $class, get_debug_type($mapping)));
        }

        $entries = [];
        foreach ($mapping as $errorIdentifier => $entry) {
            if (!is_array($entry) || !isset($entry[static::ENTRY_KEY_CODE], $entry[static::ENTRY_KEY_STATUS])) {
                throw new LogicException(sprintf(
                    'The error mapping %s maps "%s" to no code/status entry, so that code could never be held against the declared codes.',
                    $source,
                    (string)$errorIdentifier,
                ));
            }

            $entries[] = new ErrorMappingEntry($source, (string)$errorIdentifier, (string)$entry[static::ENTRY_KEY_CODE], (int)$entry[static::ENTRY_KEY_STATUS]);
        }

        if ($entries === []) {
            throw new LogicException(sprintf('The error mapping %s returns no code/status entries, so it cannot be held against the declared codes.', $source));
        }

        return $entries;
    }

    /**
     * The project override of a class - the same class name under a project namespace instead of
     * its organization - or the class itself.
     */
    public function resolveProjectOverride(string $class): string
    {
        $segments = explode(static::NAMESPACE_SEPARATOR, ltrim($class, static::NAMESPACE_SEPARATOR));
        if (in_array($segments[0], $this->projectNamespaces, true)) {
            return $class;
        }

        foreach ($this->projectNamespaces as $projectNamespace) {
            $segments[0] = $projectNamespace;
            $overrideClass = implode(static::NAMESPACE_SEPARATOR, $segments);
            if (class_exists($overrideClass)) {
                return $overrideClass;
            }
        }

        return $class;
    }

    /**
     * @throws \LogicException
     *
     * @return array{0: class-string, 1: string}
     */
    protected function split(string $source): array
    {
        $parts = explode(static::SOURCE_SEPARATOR, $source);
        if (count($parts) !== 2 || !class_exists($parts[0]) || !method_exists($parts[0], $parts[1])) {
            throw new LogicException(sprintf('The error mapping source "%s" is not an existing Class::method.', $source));
        }

        return [$parts[0], $parts[1]];
    }
}
