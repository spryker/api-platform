<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use SplFileInfo;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers processor, provider, and resolver services discovered in API resource schema files.
 *
 * Scans all resource schema files (*.resource.yml/yaml) across configured API types
 * and registers any referenced classes as public autowired services.
 */
class SchemaServiceRegistrationPass extends AbstractApiServiceRegistrationPass
{
    public function __construct(
        protected SchemaFileDiscovery $schemaFileDiscovery = new SchemaFileDiscovery(),
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $parameters = $this->resolveParameters($container);

        if ($parameters === null) {
            return;
        }

        foreach ($parameters['apiTypes'] as $apiType) {
            $schemaFiles = $this->schemaFileDiscovery->findSchemaFiles($parameters['sourceDirectories'], $apiType);

            foreach ($schemaFiles as $schemaFile) {
                $services = $this->extractServicesFromSchema($schemaFile);

                foreach ($services as $serviceClass) {
                    if ($this->shouldRegisterService($container, $serviceClass)) {
                        $this->registerService($container, $serviceClass);
                    }
                }
            }
        }
    }

    /**
     * @return array<string>
     */
    protected function extractServicesFromSchema(SplFileInfo $schemaFile): array
    {
        $schema = $this->schemaFileDiscovery->parseSchemaFile($schemaFile);

        if ($schema === null) {
            return [];
        }

        $services = [];
        $resourceDefinitions = $this->schemaFileDiscovery->extractResourceDefinitions($schema);

        foreach ($resourceDefinitions as $resource) {
            $this->collectServicesFromResource($resource, $services);
        }

        return $services;
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string> $services
     *
     * @return void
     */
    protected function collectServicesFromResource(array $resource, array &$services): void
    {
        if (isset($resource['provider']) && is_string($resource['provider'])) {
            $services[] = $resource['provider'];
        }

        if (isset($resource['processor']) && is_string($resource['processor'])) {
            $services[] = $resource['processor'];
        }

        $this->collectResolverClassesFromResource($resource, $services);

        if (!isset($resource['operations']) || !is_array($resource['operations'])) {
            return;
        }

        foreach ($resource['operations'] as $operation) {
            if (!is_array($operation)) {
                continue;
            }

            if (isset($operation['provider']) && is_string($operation['provider'])) {
                $services[] = $operation['provider'];
            }

            if (isset($operation['processor']) && is_string($operation['processor'])) {
                $services[] = $operation['processor'];
            }
        }
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string> $services
     *
     * @return void
     */
    protected function collectResolverClassesFromResource(array $resource, array &$services): void
    {
        if (!isset($resource['includes']) || !is_array($resource['includes'])) {
            return;
        }

        foreach ($resource['includes'] as $entry) {
            if (is_array($entry) && isset($entry['resolverClass']) && is_string($entry['resolverClass'])) {
                $services[] = $entry['resolverClass'];
            }
        }
    }
}
