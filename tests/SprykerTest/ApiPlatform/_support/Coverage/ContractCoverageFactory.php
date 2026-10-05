<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Coverage;

use Spryker\ApiPlatform\Contract\Coverage\AnnotationCollector;
use Spryker\ApiPlatform\Contract\Coverage\ConstraintRuleMapper;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageBaseline;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageRunner;
use Spryker\ApiPlatform\Contract\Coverage\CoverageCalculator;
use Spryker\ApiPlatform\Contract\Coverage\DeclaredClassNameResolver;
use Spryker\ApiPlatform\Contract\Coverage\ErrorMappingDiscovery;
use Spryker\ApiPlatform\Contract\Coverage\ErrorMappingResolver;
use Spryker\ApiPlatform\Contract\Coverage\IncludeEvidenceVerifier;
use Spryker\ApiPlatform\Contract\Coverage\OperationCoverageRecorder;
use Spryker\ApiPlatform\Contract\Coverage\OperationVerifier;
use Spryker\ApiPlatform\Contract\Coverage\RecordedExchangeFactory;
use Spryker\ApiPlatform\Contract\Coverage\RequestAttributePathExtractor;
use Spryker\ApiPlatform\Contract\Coverage\RequestAttributeTruthCollector;
use Spryker\ApiPlatform\Contract\Coverage\RequestAttributeVerifier;
use Spryker\ApiPlatform\Contract\Coverage\ResourceNameMatcher;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeTruthCollector;
use Spryker\ApiPlatform\Contract\Coverage\SchemaSourceResolver;
use Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader;
use Spryker\ApiPlatform\Contract\Coverage\ScopeResolver;
use Spryker\ApiPlatform\Contract\Coverage\ValidationEvidenceVerifier;
use Spryker\ApiPlatform\Contract\Envelope\JsonApiEnvelopeRecorder;
use Spryker\ApiPlatform\Contract\Envelope\JsonApiEnvelopeVerifier;
use Spryker\ApiPlatform\Contract\Replay\OpenApiExampleRequestBuilder;

/**
 * Assembles the contract-coverage object graph for the test runtime, which reflects the generated
 * resources boot-free from a Codeception lane that never builds the Glue container. The production
 * caller wires the same graph in `config/GlueStorefront/packages/spryker_api_platform.php`; the
 * runtime reads the enforced dimensions and the ownership attributes from the booted container
 * instead, so this graph enforces nothing and derives no ownership scenario.
 */
class ContractCoverageFactory
{
    /**
     * The suite's project namespace, which the production wiring reads from
     * `KernelConstants::PROJECT_NAMESPACES`.
     *
     * @var array<string>
     */
    protected const array PROJECT_NAMESPACES = ['Pyz'];

    /**
     * @param array<string> $excludedResources
     */
    public static function createContractCoverageRunner(
        string $apiType,
        array $excludedResources = []
    ): ContractCoverageRunner {
        return new ContractCoverageRunner(
            $apiType,
            $excludedResources,
            ...static::createContractCoverageRunnerCollaborators(),
        );
    }

    /**
     * The runner's collaborators in constructor order, so a subclass that only overrides a
     * discovery seam can hand them straight on rather than restating the graph.
     *
     * @return array{\Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader, \Spryker\ApiPlatform\Contract\Coverage\AnnotationCollector, \Spryker\ApiPlatform\Contract\Coverage\ScopeResolver, \Spryker\ApiPlatform\Contract\Coverage\CoverageCalculator, \Spryker\ApiPlatform\Contract\Coverage\ResourceNameMatcher, \Spryker\ApiPlatform\Contract\Coverage\SchemaSourceResolver, \Spryker\ApiPlatform\Contract\Coverage\DeclaredClassNameResolver, \Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement, \Spryker\ApiPlatform\Contract\Coverage\ErrorMappingResolver, \Spryker\ApiPlatform\Contract\Coverage\ErrorMappingDiscovery, \Spryker\ApiPlatform\Contract\Coverage\ContractCoverageBaseline}
     */
    public static function createContractCoverageRunnerCollaborators(): array
    {
        return [
            static::createSchemaTruthLoader(),
            static::createAnnotationCollector(),
            static::createScopeResolver(),
            static::createCoverageCalculator(),
            static::createResourceNameMatcher(),
            static::createSchemaSourceResolver(),
            static::createDeclaredClassNameResolver(),
            ContractCoverageEnforcement::none(),
            static::createErrorMappingResolver(),
            static::createErrorMappingDiscovery(),
            ContractCoverageBaseline::none(),
        ];
    }

    public static function createSchemaTruthLoader(): SchemaTruthLoader
    {
        return new SchemaTruthLoader(
            static::createConstraintRuleMapper(),
            static::createResponseAttributeTruthCollector(),
            [],
            static::createRequestAttributeTruthCollector(),
        );
    }

    public static function createOperationCoverageRecorder(): OperationCoverageRecorder
    {
        return new OperationCoverageRecorder(static::createOperationVerifier());
    }

    /**
     * @param array<string> $schemaResourceShortNames
     * @param array<string> $identifierDeclaringResourceShortNames
     */
    public static function createJsonApiEnvelopeRecorder(
        array $schemaResourceShortNames,
        array $identifierDeclaringResourceShortNames
    ): JsonApiEnvelopeRecorder {
        return new JsonApiEnvelopeRecorder(
            $schemaResourceShortNames,
            $identifierDeclaringResourceShortNames,
            static::createJsonApiEnvelopeVerifier(),
        );
    }

    public static function createConstraintRuleMapper(): ConstraintRuleMapper
    {
        return new ConstraintRuleMapper();
    }

    public static function createResponseAttributeTruthCollector(): ResponseAttributeTruthCollector
    {
        return new ResponseAttributeTruthCollector();
    }

    public static function createAnnotationCollector(): AnnotationCollector
    {
        return new AnnotationCollector();
    }

    public static function createScopeResolver(): ScopeResolver
    {
        return new ScopeResolver();
    }

    public static function createCoverageCalculator(): CoverageCalculator
    {
        return new CoverageCalculator();
    }

    public static function createResourceNameMatcher(): ResourceNameMatcher
    {
        return new ResourceNameMatcher();
    }

    public static function createSchemaSourceResolver(): SchemaSourceResolver
    {
        return new SchemaSourceResolver();
    }

    public static function createDeclaredClassNameResolver(): DeclaredClassNameResolver
    {
        return new DeclaredClassNameResolver();
    }

    public static function createOperationVerifier(): OperationVerifier
    {
        return new OperationVerifier();
    }

    public static function createErrorMappingResolver(): ErrorMappingResolver
    {
        return new ErrorMappingResolver(static::PROJECT_NAMESPACES);
    }

    public static function createErrorMappingDiscovery(): ErrorMappingDiscovery
    {
        return new ErrorMappingDiscovery(static::createErrorMappingResolver());
    }

    public static function createOpenApiExampleRequestBuilder(): OpenApiExampleRequestBuilder
    {
        return new OpenApiExampleRequestBuilder(static::createSchemaTruthLoader());
    }

    public static function createIncludeEvidenceVerifier(): IncludeEvidenceVerifier
    {
        return new IncludeEvidenceVerifier();
    }

    public static function createRequestAttributeVerifier(): RequestAttributeVerifier
    {
        return new RequestAttributeVerifier(static::createRequestAttributePathExtractor());
    }

    public static function createRequestAttributePathExtractor(): RequestAttributePathExtractor
    {
        return new RequestAttributePathExtractor();
    }

    public static function createRequestAttributeTruthCollector(): RequestAttributeTruthCollector
    {
        return new RequestAttributeTruthCollector();
    }

    public static function createValidationEvidenceVerifier(): ValidationEvidenceVerifier
    {
        return new ValidationEvidenceVerifier();
    }

    public static function createRecordedExchangeFactory(): RecordedExchangeFactory
    {
        return new RecordedExchangeFactory(static::createConstraintRuleMapper());
    }

    public static function createJsonApiEnvelopeVerifier(): JsonApiEnvelopeVerifier
    {
        return new JsonApiEnvelopeVerifier();
    }
}
