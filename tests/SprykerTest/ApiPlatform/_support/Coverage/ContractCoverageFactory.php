<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Coverage;

use Spryker\ApiPlatform\Contract\Coverage\AnnotationCollector;
use Spryker\ApiPlatform\Contract\Coverage\ConstraintRuleMapper;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageRunner;
use Spryker\ApiPlatform\Contract\Coverage\CoverageCalculator;
use Spryker\ApiPlatform\Contract\Coverage\DeclaredClassNameResolver;
use Spryker\ApiPlatform\Contract\Coverage\OperationCoverageRecorder;
use Spryker\ApiPlatform\Contract\Coverage\OperationVerifier;
use Spryker\ApiPlatform\Contract\Coverage\ResourceNameMatcher;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeTruthCollector;
use Spryker\ApiPlatform\Contract\Coverage\SchemaSourceResolver;
use Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader;
use Spryker\ApiPlatform\Contract\Coverage\ScopeResolver;
use Spryker\ApiPlatform\Contract\Envelope\JsonApiEnvelopeRecorder;
use Spryker\ApiPlatform\Contract\Envelope\JsonApiEnvelopeVerifier;

/**
 * Assembles the contract-coverage object graph for the test runtime, which reflects the generated
 * resources boot-free from a Codeception lane that never builds the Glue container. The production
 * caller wires the same graph in `config/GlueStorefront/packages/spryker_api_platform.php`.
 */
class ContractCoverageFactory
{
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
     * @return array{\Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader, \Spryker\ApiPlatform\Contract\Coverage\AnnotationCollector, \Spryker\ApiPlatform\Contract\Coverage\ScopeResolver, \Spryker\ApiPlatform\Contract\Coverage\CoverageCalculator, \Spryker\ApiPlatform\Contract\Coverage\ResourceNameMatcher, \Spryker\ApiPlatform\Contract\Coverage\SchemaSourceResolver, \Spryker\ApiPlatform\Contract\Coverage\DeclaredClassNameResolver}
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
        ];
    }

    public static function createSchemaTruthLoader(): SchemaTruthLoader
    {
        return new SchemaTruthLoader(
            static::createConstraintRuleMapper(),
            static::createResponseAttributeTruthCollector(),
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

    public static function createJsonApiEnvelopeVerifier(): JsonApiEnvelopeVerifier
    {
        return new JsonApiEnvelopeVerifier();
    }
}
