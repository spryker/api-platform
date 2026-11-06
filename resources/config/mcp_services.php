<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ApiPlatform\State\CallableProvider;
use Spryker\ApiPlatform\EventSubscriber\McpAccessDeniedSubscriber;
use Spryker\ApiPlatform\State\McpAccessDeniedToHttpProcessor;
use Spryker\ApiPlatform\State\McpAccessDeniedToHttpProvider;
use Spryker\ApiPlatform\State\McpOperationToolProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(McpAccessDeniedToHttpProvider::class)
        ->autoconfigure(false)
        ->decorate('api_platform.mcp.state_provider', null, -1000, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->arg('$decorated', service('.inner'))
        ->arg('$requestStack', service('request_stack'));

    $services->set(McpAccessDeniedToHttpProcessor::class)
        ->autoconfigure(false)
        ->decorate('api_platform.mcp.state_processor', null, -1000, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->arg('$decorated', service('.inner'))
        ->arg('$requestStack', service('request_stack'));

    $services->set(McpAccessDeniedSubscriber::class);

    // A plain provider locator, without the read, security and MCP decorators of `api_platform.state_provider.locator`:
    // the MCP tool call already passed them, and the source operation must only run its own provider.
    $services->set('spryker_api_platform.mcp.source_operation_provider', CallableProvider::class)
        ->autoconfigure(false)
        ->args([tagged_locator('api_platform.state_provider', 'key')]);

    $services->set(McpOperationToolProcessor::class)
        ->arg('$resourceMetadataCollectionFactory', service('api_platform.metadata.resource.metadata_collection_factory'))
        ->arg('$sourceOperationProvider', service('spryker_api_platform.mcp.source_operation_provider'))
        ->arg('$normalizer', service('serializer'))
        ->arg('$requestStack', service('request_stack'));
};
