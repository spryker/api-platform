<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Spryker\ApiPlatform\Cache\ApiResourceCacheClearer;
use Spryker\ApiPlatform\Cache\ApiResourceCacheWarmer;
use Spryker\ApiPlatform\Command\ApiDebugCommand;
use Spryker\ApiPlatform\Command\ApiGenerateCommand;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Generator\ClassGenerator;
use Spryker\ApiPlatform\Generator\ClassGeneratorInterface;
use Spryker\ApiPlatform\Generator\ResourceGenerator;
use Spryker\ApiPlatform\Generator\ResourceGeneratorInterface;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use Spryker\ApiPlatform\Generator\Template\TemplateRendererInterface;
use Spryker\ApiPlatform\Schema\Finder\SchemaFinder;
use Spryker\ApiPlatform\Schema\Finder\SchemaFinderInterface;
use Spryker\ApiPlatform\Schema\Loader\YamlSchemaLoader;
use Spryker\ApiPlatform\Schema\Merger\SchemaMerger;
use Spryker\ApiPlatform\Schema\Merger\SchemaMergerInterface;
use Spryker\ApiPlatform\Schema\Parser\SchemaParser;
use Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface;
use Spryker\ApiPlatform\Schema\Validation\Finder\ValidationSchemaFinder;
use Spryker\ApiPlatform\Schema\Validation\Finder\ValidationSchemaFinderInterface;
use Spryker\ApiPlatform\Schema\Validation\Loader\ValidationSchemaLoader;
use Spryker\ApiPlatform\Schema\Validation\Loader\ValidationSchemaLoaderInterface;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapper;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;
use Spryker\ApiPlatform\Schema\Validation\Merger\ValidationSchemaMerger;
use Spryker\ApiPlatform\Schema\Validation\Merger\ValidationSchemaMergerInterface;
use Spryker\ApiPlatform\Schema\Validator\Rules\MergeValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\OperationValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\PaginationValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\ProcessorValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\PropertyValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\ProviderValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\ResourceNameValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\ResourceNamingValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\SecurityExpressionValidationRule;
use Spryker\ApiPlatform\Schema\Validator\SchemaValidator;
use Spryker\ApiPlatform\Schema\Validator\SchemaValidatorInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Configuration
    $services->set(ApiPlatformConfig::class)
        ->arg('$sourceDirectories', param('spryker_api_platform.source_directories'))
        ->arg('$cacheDir', param('spryker_api_platform.cache_dir'))
        ->arg('$generatedDir', param('spryker_api_platform.generated_dir'))
        ->arg('$apiTypes', param('spryker_api_platform.api_types'))
        ->arg('$debug', param('spryker_api_platform.debug'));

    // Schema Loaders
    $services->set(YamlSchemaLoader::class)
        ->tag('spryker_api_platform.schema_loader');

    // Schema Finder
    $services->set(SchemaFinderInterface::class, SchemaFinder::class);

    // Validation Schema Infrastructure
    $services->set(ValidationSchemaFinderInterface::class, ValidationSchemaFinder::class);

    $services->set(ValidationSchemaLoaderInterface::class, ValidationSchemaLoader::class);

    $services->set(ValidationSchemaMergerInterface::class, ValidationSchemaMerger::class);

    $services->set(ValidationGroupMapperInterface::class, ValidationGroupMapper::class);

    // Schema Parser
    $services->set(SchemaParserInterface::class, SchemaParser::class);

    // Validation Rules
    $services->set(ResourceNameValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(ResourceNamingValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(ProviderValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(ProcessorValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(PaginationValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(PropertyValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(OperationValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(SecurityExpressionValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(MergeValidationRule::class);

    // Dependencies
    $services->set(ExpressionLanguage::class);

    $services->set(PropertyAccessorInterface::class)
        ->factory([PropertyAccess::class, 'createPropertyAccessor']);

    $services->set(Filesystem::class);

    // Schema Validator
    $services->set(SchemaValidatorInterface::class, SchemaValidator::class)
        ->arg('$postMergeRules', tagged_iterator('spryker_api_platform.validation_rule.post_merge'))
        ->arg('$mergeValidationRule', service(MergeValidationRule::class));

    // Schema Merger
    $services->set(SchemaMergerInterface::class, SchemaMerger::class);

    // Generator: Template Renderer
    $services->set(TemplateRendererInterface::class, PhpTemplateRenderer::class);

    // Generator: Class Generator
    $services->set(ClassGeneratorInterface::class, ClassGenerator::class);

    // Generator: Resource Generator
    $services->set(ResourceGeneratorInterface::class, ResourceGenerator::class)
        ->arg('$loaders', tagged_iterator('spryker_api_platform.schema_loader'));

    // Cache Clearer
    $services->set(ApiResourceCacheClearer::class)
        ->tag('kernel.cache_clearer');

    // Cache Warmer
    $services->set(ApiResourceCacheWarmer::class)
        ->tag('kernel.cache_warmer');

    // Console Commands
    $services->set(ApiGenerateCommand::class)
        ->public()
        ->tag('console.command');

    $services->alias('console.command.api_generate', ApiGenerateCommand::class)
        ->public();

    $services->set(ApiDebugCommand::class)
        ->public()
        ->arg('$loaders', tagged_iterator('spryker_api_platform.schema_loader'))
        ->tag('console.command');

    $services->alias('console.command.api_debug', ApiDebugCommand::class)
        ->public();
};
