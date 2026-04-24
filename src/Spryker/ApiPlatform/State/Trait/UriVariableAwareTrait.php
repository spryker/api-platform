<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Trait;

use Spryker\ApiPlatform\Exception\ApiPlatformContextException;

/**
 * Grants access to the URI variables extracted by API Platform from the route template.
 *
 * URI variables are passed to the host class's entry method (`provide()` for providers,
 * `process()` for processors) by API Platform, which parses them from the matched
 * Symfony route — e.g. the route template `/carts/{cartId}` matched against a request
 * to `GET /carts/42` yields `['cartId' => '42']`.
 *
 * The host class is expected to assign `$this->uriVariables = $uriVariables;` at the
 * start of its entry method so that these accessors return current values.
 */
trait UriVariableAwareTrait
{
    /**
     * @var array<string, mixed>
     */
    protected array $uriVariables = [];

    /**
     * @return array<string, mixed>
     */
    protected function getUriVariables(): array
    {
        return $this->uriVariables;
    }

    protected function hasUriVariable(string $name): bool
    {
        return array_key_exists($name, $this->uriVariables);
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\ApiPlatformContextException When the URI
     *     variable is not present — use {@see self::findUriVariable()} for nullable access.
     */
    protected function getUriVariable(string $name): mixed
    {
        if (!$this->hasUriVariable($name)) {
            throw new ApiPlatformContextException(sprintf(
                'The uri variable "%s" is missing. Either you have to make sure you call `%s::hasUriVariable()` before or there is a major issue in your setup.',
                $name,
                static::class,
            ));
        }

        return $this->uriVariables[$name];
    }

    /**
     * Nullable access to a URI variable. Returns null when the variable was not part of
     * the route template or was not forwarded by API Platform for the current operation.
     */
    protected function findUriVariable(string $name): mixed
    {
        return $this->hasUriVariable($name) ? $this->getUriVariable($name) : null;
    }
}
