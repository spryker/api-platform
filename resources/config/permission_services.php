<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Spryker\ApiPlatform\Command\PermissionRegistryDumpCommand;
use Spryker\ApiPlatform\Metadata\PermissionCheckResourceMetadataCollectionFactory;
use Spryker\ApiPlatform\Security\Permission\OperationPermissionIdentifierResolver;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(OperationPermissionIdentifierResolver::class);

    // Priority 0 runs inside API Platform's cached decorator (-10), so the result is cached with the metadata.
    $services->set(PermissionCheckResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory', null, 0)
        ->args([
            service('.inner'),
            service('api_platform.metadata.resource.name_collection_factory'),
            service(OperationPermissionIdentifierResolver::class),
            param('spryker_api_platform.api_types'),
        ]);

    $services->set(PermissionRegistryDumpCommand::class)
        ->public()
        ->args([
            service('api_platform.metadata.resource.name_collection_factory'),
            service('api_platform.metadata.resource.metadata_collection_factory'),
            param('spryker_api_platform.api_types'),
            param('spryker_api_platform.generated_dir'),
        ])
        ->tag('console.command');
};
