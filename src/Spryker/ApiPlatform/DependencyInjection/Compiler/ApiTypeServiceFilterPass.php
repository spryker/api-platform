<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes services tagged with `spryker_api_platform.api_type` whose declared types
 * do not intersect with the application's configured `spryker_api_platform.api_types`.
 *
 * Services are tagged automatically via the `#[ApiType]` attribute and
 * `registerAttributeForAutoconfiguration()` in the extension.
 */
class ApiTypeServiceFilterPass implements CompilerPassInterface
{
    protected const string TAG_API_TYPE = 'spryker_api_platform.api_type';

    protected const string TAG_ATTRIBUTE_TYPE = 'type';

    protected const string PARAMETER_API_TYPES = 'spryker_api_platform.api_types';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(static::PARAMETER_API_TYPES)) {
            return;
        }

        $configuredApiTypes = $container->getParameter(static::PARAMETER_API_TYPES);

        if (!is_array($configuredApiTypes)) {
            return;
        }

        $configuredApiTypesLower = array_map('strtolower', $configuredApiTypes);
        $taggedServiceIds = $container->findTaggedServiceIds(static::TAG_API_TYPE);

        foreach ($taggedServiceIds as $serviceId => $tags) {
            $serviceTypes = $this->extractServiceTypes($tags);

            if (array_intersect($serviceTypes, $configuredApiTypesLower) !== []) {
                continue;
            }

            $container->removeDefinition($serviceId);
        }
    }

    /**
     * @param array<array<string, string>> $tags
     *
     * @return array<string>
     */
    protected function extractServiceTypes(array $tags): array
    {
        $types = [];

        foreach ($tags as $attributes) {
            if (!isset($attributes[static::TAG_ATTRIBUTE_TYPE])) {
                continue;
            }

            $types[] = strtolower($attributes[static::TAG_ATTRIBUTE_TYPE]);
        }

        return $types;
    }
}
