<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection;

use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;
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
                /** @phpstan-ignore method.nonObject */
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
                ->arrayNode('excluded_path_fragments')
                    ->info('Path fragments matched against real schema file paths; any match excludes the file from schema discovery')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('contract_coverage_excluded_resources')
                    ->info('Resource short names the contract-coverage gate does not enforce; every generated resource is enforced when empty. The value is a project decision and belongs in the project\'s spryker_api_platform package configuration.')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('contract_coverage_enforced_dimensions')
                    ->info('Contract-coverage dimensions beyond operations, validation rules and response attributes that fail the gate and the test runtime; every other dimension is reported only. "all" enforces every dimension. An application lists a dimension once its tests cover it.')
                    ->defaultValue([])
                    ->enumPrototype()->values(ContractCoverageEnforcement::acceptedValues())->end()
                ->end()
                ->variableNode('contract_coverage_baseline')
                    ->info('Coverage items a known product bug keeps uncovered, keyed by dimension, then by the item key the report prints, each with the bug in plain language. A baselined item does not fail its dimension; an entry whose item is covered fails until it is removed.')
                    ->defaultValue([])
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !is_array($value))
                        ->thenInvalid('contract_coverage_baseline maps each dimension to its item keys and reasons, got %s.')
                    ->end()
                ->end()
                ->arrayNode('contract_coverage_ownership_security_attributes')
                    ->info('Security voter attributes whose grant depends on who owns the addressed resource; an operation guarded by one owes a test in which an authenticated caller is denied someone else\'s resource.')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('canonical_object_search_directories')
                    ->info('Additional directories (keyed by API type) scanned for canonical *.object.yml / *.object.validation.yml files, on top of in-module locations. Relative paths are resolved against the project root.')
                    ->defaultValue([])
                    ->useAttributeAsKey('api_type')
                    ->arrayPrototype()
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
                ->booleanNode('is_method_not_allowed_status_enabled')
                    ->info('Answer a request whose path exists but declares no operation for its HTTP method with 405 and an Allow header, per RFC 9110. Disable it to answer 404 instead, as the old Glue REST API did.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('debug')
                    ->info('Enable debug mode (disables caching, enables verbose output)')
                    ->defaultValue('%kernel.debug%')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
