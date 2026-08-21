<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use InvalidArgumentException;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Validates that SecurityBundle is registered when resource schemas use security expressions.
 *
 * This compiler pass runs at compile time and throws a clear error if any resource
 * YAML files contain `security:` expressions but the SecurityBundle is not registered.
 * This prevents cryptic runtime failures.
 */
class SecurityServiceRegistrationPass implements CompilerPassInterface
{
    protected const string SECURITY_BUNDLE_NAME = 'SecurityBundle';

    public function __construct(
        protected SchemaFileDiscovery $schemaFileDiscovery = new SchemaFileDiscovery(),
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function process(ContainerBuilder $container): void
    {
        if ($this->isSecurityBundleRegistered($container)) {
            return;
        }

        $resourcesWithSecurity = $this->findResourcesWithSecurityExpressions($container);

        if ($resourcesWithSecurity === []) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The following API resource schemas use security expressions but SecurityBundle is not registered: %s. '
            . 'Register SecurityBundle in your bundles.php to enable security expression evaluation.',
            implode(', ', $resourcesWithSecurity),
        ));
    }

    protected function isSecurityBundleRegistered(ContainerBuilder $container): bool
    {
        $bundles = $container->getParameter('kernel.bundles');

        return is_array($bundles) && isset($bundles[static::SECURITY_BUNDLE_NAME]);
    }

    /**
     * @return array<string>
     */
    protected function findResourcesWithSecurityExpressions(ContainerBuilder $container): array
    {
        if (!$container->hasParameter('spryker_api_platform.api_types') || !$container->hasParameter('spryker_api_platform.source_directories')) {
            return [];
        }

        $apiTypes = $container->getParameter('spryker_api_platform.api_types');
        $sourceDirectories = $container->getParameter('spryker_api_platform.source_directories');

        if (!is_array($apiTypes) || !is_array($sourceDirectories)) {
            return [];
        }

        $resourcesWithSecurity = [];

        foreach ($apiTypes as $apiType) {
            $schemaFiles = $this->schemaFileDiscovery->findSchemaFiles($sourceDirectories, (string)$apiType);

            foreach ($schemaFiles as $schemaFile) {
                if ($this->schemaHasSecurityExpressions($schemaFile)) {
                    $resourcesWithSecurity[] = $schemaFile->getFilename();
                }
            }
        }

        return $resourcesWithSecurity;
    }

    protected function schemaHasSecurityExpressions(SplFileInfo $schemaFile): bool
    {
        $schema = $this->schemaFileDiscovery->parseSchemaFile($schemaFile);

        if ($schema === null) {
            return false;
        }

        $resourceDefinitions = $this->schemaFileDiscovery->extractResourceDefinitions($schema);

        foreach ($resourceDefinitions as $resource) {
            if ($this->resourceHasSecurityExpressions($resource)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $resource
     */
    protected function resourceHasSecurityExpressions(array $resource): bool
    {
        if (isset($resource['security']) || isset($resource['securityPostDenormalize']) || isset($resource['securityPostValidation'])) {
            return true;
        }

        if (!isset($resource['operations'])) {
            return false;
        }

        foreach ($resource['operations'] as $operation) {
            if (!is_array($operation)) {
                continue;
            }

            if (isset($operation['security']) || isset($operation['securityPostDenormalize']) || isset($operation['securityPostValidation'])) {
                return true;
            }
        }

        return false;
    }
}
