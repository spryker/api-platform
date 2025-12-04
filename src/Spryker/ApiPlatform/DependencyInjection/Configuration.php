<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration for API Resource Generator Bundle
 *
 * Defines the configuration tree for:
 * - Source directories to search for schema files
 * - Cache directory for generated resources
 * - Default ApiTypes to generate
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('spryker_api_platform');

        /** @phpstan-var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('source_directories')
                    ->info('Directories to search for API schema files')
                    ->defaultValue([
                        'vendor/spryker',
                        'src/Pyz',
                    ])
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('cache_dir')
                    ->info('Cache directory for generated resources')
                    ->defaultValue('%kernel.cache_dir%/api-generator')
                ->end()
                ->scalarNode('generated_dir')
                    ->info('Directory where generated resources are written')
                    ->defaultValue('%kernel.project_dir%/src/Generated/Api')
                ->end()
                ->arrayNode('api_types')
                    ->info('Default ApiTypes to generate (empty = all found)')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('debug')
                    ->info('Enable debug mode (disables caching, enables verbose output)')
                    ->defaultValue('%kernel.debug%')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
