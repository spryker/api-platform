<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ApiPlatform\JsonApi\State\JsonApiProvider;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use ApiPlatform\State\ErrorProvider;
use Spryker\ApiPlatform\Cache\ApiResourceCacheWarmer;
use Spryker\ApiPlatform\Command\ApiDebugCommand;
use Spryker\ApiPlatform\Command\ApiGenerateCommand;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\EventSubscriber\AcceptLanguageLocaleSubscriber;
use Spryker\ApiPlatform\EventSubscriber\OAuthExceptionSubscriber;
use Spryker\ApiPlatform\Generator\ClassGenerator;
use Spryker\ApiPlatform\Generator\ConstraintFormatter;
use Spryker\ApiPlatform\Generator\FqcnConstraintResolver;
use Spryker\ApiPlatform\Generator\MediaType\MediaTypeFormatterRegistry;
use Spryker\ApiPlatform\Generator\OpenApiOperationBuilder;
use Spryker\ApiPlatform\Generator\PropertyAttributeGenerator;
use Spryker\ApiPlatform\Generator\RelationshipPhpDocGenerator;
use Spryker\ApiPlatform\Generator\ResourceAttributeGenerator;
use Spryker\ApiPlatform\Generator\ResourceGenerator;
use Spryker\ApiPlatform\Generator\ResourceGeneratorInterface;
use Spryker\ApiPlatform\Generator\ResourceNameTagGenerator;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use Spryker\ApiPlatform\Generator\UseStatementCollector;
use Spryker\ApiPlatform\Generator\ValidationAttributeGenerator;
use Spryker\ApiPlatform\OpenApi\FormatTransformer\JsonApiFormatTransformer;
use Spryker\ApiPlatform\OpenApi\Normalizer\EmptyRelationshipNormalizer;
use Spryker\ApiPlatform\OpenApi\Normalizer\IdNormalizer;
use Spryker\ApiPlatform\OpenApi\Normalizer\LinksPositionNormalizer;
use Spryker\ApiPlatform\OpenApi\Normalizer\SelfLinkNormalizer;
use Spryker\ApiPlatform\Provider\RelationshipProvider;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolver;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolverInterface;
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
use Spryker\ApiPlatform\Schema\Validator\PreMergeValidator;
use Spryker\ApiPlatform\Schema\Validator\PreMergeValidatorInterface;
use Spryker\ApiPlatform\Schema\Validator\Rules\MergeValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\OperationValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\PaginationValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\ProcessorValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\PropertyValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\ProviderValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\RelationshipValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\ResourceNameValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\ResourceNamingValidationRule;
use Spryker\ApiPlatform\Schema\Validator\Rules\SecurityExpressionValidationRule;
use Spryker\ApiPlatform\Schema\Validator\SchemaValidator;
use Spryker\ApiPlatform\Schema\Validator\SchemaValidatorInterface;
use Spryker\ApiPlatform\Serializer\Encoder\CXmlEncoder;
use Spryker\ApiPlatform\Serializer\Normalizer\CXmlNormalizer;
use Spryker\ApiPlatform\Serializer\TranslatingConstraintViolationListNormalizer;
use Spryker\ApiPlatform\State\TranslatingErrorProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage as SecurityExpressionLanguage;

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

    // Schema Parser PreMergeValidator
    $services->set(PreMergeValidatorInterface::class, PreMergeValidator::class);

    // Generator: Tag Generator
    $services->set(ResourceNameTagGenerator::class);

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

    $services->set(RelationshipValidationRule::class)
        ->tag('spryker_api_platform.validation_rule.post_merge');

    $services->set(MergeValidationRule::class);

    // Dependencies: Security-aware ExpressionLanguage auto-registers is_granted() and other security functions
    $services->set(ExpressionLanguage::class, SecurityExpressionLanguage::class);

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
    $services->set(PhpTemplateRenderer::class);

    // Generator: Property Attribute Generator
    $services->set(PropertyAttributeGenerator::class);

    // Generator: Constraint Formatter
    $services->set(ConstraintFormatter::class);

    // Generator: Fully qualified class name Constraint Resolver
    $services->set(FqcnConstraintResolver::class);

    // Generator: Validation Attribute Generator
    $services->set(ValidationAttributeGenerator::class);

    // Generator: Media Type Formatter Registry
    // Those can be used to manipulate a specific media type output e.g. change the formatting of `application/vnd.ai+json`
    $services->set(MediaTypeFormatterRegistry::class)
        ->arg('$formatters', tagged_iterator('spryker_api_platform.media_type_formatter'));

    // Generator: OpenApi Operation Builder
    $services->set(OpenApiOperationBuilder::class)
        ->arg('$formatterRegistry', service(MediaTypeFormatterRegistry::class))
        ->arg('$apiPlatformFormats', param('api_platform.formats'));

    // Generator: Resource Attribute Generator
    $services->set(ResourceAttributeGenerator::class);

    // Generator: Use Statement Collector
    $services->set(UseStatementCollector::class);

    // Generator: Relationship PHPDoc Generator
    $services->set(RelationshipPhpDocGenerator::class);

    // Generator: Class Generator
    $services->set(ClassGenerator::class);

    // Generator: Resource Generator
    $services->set(ResourceGenerator::class)
        ->arg('$loaders', tagged_iterator('spryker_api_platform.schema_loader'));

    $services->alias(ResourceGeneratorInterface::class, ResourceGenerator::class);

    // Cache Warmer
    $services->set(ApiResourceCacheWarmer::class)
        ->tag('kernel.cache_warmer');

    // OpenAPI Format Transformers
    $services->set(JsonApiFormatTransformer::class)
        ->tag('spryker_api_platform.format_transformer');

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

    // Override error provider to enable debug mode in development environment
    // This shows full exception messages instead of "Internal Server Error"
    if (APPLICATION_ENV === 'docker.dev') {
        $services->set('api_platform.state.error_provider', ErrorProvider::class)
            ->arg('$debug', true)
            ->arg('$resourceClassResolver', service('api_platform.resource_class_resolver'))
            ->arg('$resourceMetadataCollectionFactory', service('api_platform.metadata.resource.metadata_collection_factory'))
            ->tag('api_platform.state_provider', ['key' => 'api_platform.state.error_provider']);
    }

    // Convert OAuthServerException to HttpException with correct status code
    $services->set(OAuthExceptionSubscriber::class);

    // Locale resolution from Accept-Language header
    $services->set(AcceptLanguageLocaleSubscriber::class);

    // Translate error messages using Spryker glossary
    $services->set(TranslatingErrorProvider::class)
        ->autoconfigure(false)
        ->decorate('api_platform.state.error_provider')
        ->arg('$decorated', service('.inner'));

    // Translate validation constraint violation messages using Spryker glossary
    $services->set(TranslatingConstraintViolationListNormalizer::class)
        ->autoconfigure(false)
        ->decorate('api_platform.jsonapi.normalizer.constraint_violation_list', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

    // Relationship system (?include)
    // Note: api_platform.relationships parameter is populated by RelationshipConfigurationPass compiler pass
    $services->set(ApiPlatformRelationshipResolver::class)
        ->arg('$relationships', param('api_platform.relationships'))
        ->arg('$providerLocator', service('service_container'));

    $services->alias(ApiPlatformRelationshipResolverInterface::class, ApiPlatformRelationshipResolver::class);

    // Sparse fieldsets: enables ?fields[resourceType]=attr1,attr2 for JSON:API
    $services->set('spryker.api_platform.filter.property', PropertyFilter::class)
        ->arg('$parameterName', 'fields')
        ->arg('$overrideDefaultProperties', true)
        ->arg('$whitelist', null)
        ->arg('$nameConverter', service('api_platform.name_converter')->nullOnInvalid())
        ->tag('api_platform.filter');

    // Enable API Platform's JsonApiProvider to parse ?include= parameter
    $services->set(JsonApiProvider::class)
        ->decorate('api_platform.state_provider.locator')
        ->arg('$decorated', service('.inner'))
        ->arg('$orderParameterName', 'order');

    $services->set(RelationshipProvider::class)
        ->autoconfigure(false)
        ->decorate(JsonApiProvider::class)
        ->arg('$decorated', service('.inner'))
        ->arg('$relationshipResolver', service(ApiPlatformRelationshipResolverInterface::class))
        ->arg('$requestStack', service('request_stack'));

    /**
     * We want a specific format for the JSON:API response.
     *
     * All normalizer will be executed and each one ensures it can only be executed once.
     */

    /**
     * This normalizer ensures that we have the identifier in the id field and not the IRI for backwards compatibility reasons.
     * The order (priority) is important!
     */
    $services->set(IdNormalizer::class)
        ->arg('$identifiersExtractor', service('api_platform.api.identifiers_extractor'))
        ->tag('serializer.normalizer', ['priority' => 64]);

    /**
     * This normalizer ensures that we always have a link to the resource as self link available.
     * The order (priority) is important!
     */
    $services->set(SelfLinkNormalizer::class)
        ->tag('serializer.normalizer', ['priority' => 63]);

    /**
     * This normalizer ensures that we do not have empty relations kept.
     */
    $services->set(EmptyRelationshipNormalizer::class)
        ->tag('serializer.normalizer');

    /**
     * This normalizer ensures that links of other related resources are at the end.
     */
    $services->set(LinksPositionNormalizer::class)
        ->tag('serializer.normalizer');

    $services->set(CXmlNormalizer::class)
        ->autoconfigure(false)
        ->decorate('api_platform.serializer.normalizer.item');

    $services->set(CXmlEncoder::class)
        ->autoconfigure(false)
        ->decorate('serializer.encoder.xml');
};
