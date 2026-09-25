<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use Spryker\ApiPlatform\Contract\Coverage\Exception\ResourcesNotGeneratedException;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;

/**
 * Runs the API contract-coverage check for one API type: reflects the generated `#[ApiResource]`
 * classes — the source of truth for operations, validation constraints and required response
 * attributes — against the `#[CoversApiOperation]` / `#[CoversApiValidation]` /
 * `#[CoversApiRequiredResponseAttributes]` annotations on that API type's suites.
 *
 * Boot-free: no kernel, no database, no containers. Both resources and tests are discovered
 * automatically, so adding a test never needs an edit here.
 *
 * Drive it through `vendor/bin/glue api:contract:coverage`.
 */
class ContractCoverageRunner
{
    protected const string RESOURCE_NAMESPACE_TEMPLATE = 'Generated\\Api\\%s\\';

    protected const string GENERATED_RESOURCE_PATH_TEMPLATE = '/src/Generated/Api/%s';

    protected const string TEST_PATH_MARKER_TEMPLATE = '/%sApi/';

    protected const string GLUE_APPLICATION_TEMPLATE = 'GLUE_%s';

    protected const string TESTS_PATH = '/tests';

    protected const string TEST_FILE_SUFFIX = 'Test.php';

    /**
     * @param string $apiType The API type this run measures, in any casing — `Storefront`,
     *   `Backend`. It selects the generated resource namespace and directory and the test path
     *   marker, so one runner never sees another API type's resources or tests.
     * @param array<string> $excludedResources Resource short names the gate does not enforce; empty
     *   enforces every generated resource of this API type. Owned by the application's
     *   `spryker_api_platform` package configuration, which is per application and therefore
     *   already per API type.
     */
    public function __construct(
        protected readonly string $apiType,
        protected readonly array $excludedResources,
        protected readonly SchemaTruthLoader $truthLoader,
        protected readonly AnnotationCollector $annotationCollector,
        protected readonly ScopeResolver $scopeResolver,
        protected readonly CoverageCalculator $coverageCalculator,
        protected readonly ResourceNameMatcher $resourceNameMatcher,
        protected readonly SchemaSourceResolver $schemaSourceResolver,
        protected readonly DeclaredClassNameResolver $declaredClassNameResolver,
    ) {
    }

    /**
     * @param array<string> $moduleFilters Module or resource names; empty runs over every in-scope resource.
     */
    public function run(string $applicationRoot, array $moduleFilters = []): ContractCoverageResult
    {
        $this->assertResourcesGenerated($applicationRoot);

        $classesByShortName = $this->groupResourceClassesByShortName($this->generatedResourcePath($applicationRoot));
        $truthByResource = $this->loadTruthFromClasses($classesByShortName);

        $enforcedResources = $this->filterExcluded(array_keys($truthByResource));
        $selectedResources = $this->resourceNameMatcher->match($enforcedResources, $moduleFilters);
        $unmatchedFilters = $this->resourceNameMatcher->unmatchedFilters($enforcedResources, $moduleFilters);

        $annotations = $this->annotationCollector->collect(
            $this->discoverTestClasses($applicationRoot . static::TESTS_PATH),
        );

        $scope = $this->scopeResolver->resolve($truthByResource, $selectedResources);
        $report = $this->coverageCalculator->calculate($scope->enforcedTruth, $scope->existenceTruth, $annotations);

        return new ContractCoverageResult(
            $report,
            $scope,
            $selectedResources,
            $unmatchedFilters,
            count($truthByResource),
            $this->collectSchemaDefects($truthByResource, $classesByShortName, $selectedResources, $applicationRoot),
            $annotations->operationDeclarers,
            $this->collectUnknownExclusions(array_keys($classesByShortName)),
        );
    }

    /**
     * Exclusions are matched against the generated short names rather than the enforced selection,
     * so narrowing a run with `--module` never turns the rest of the list into false unknowns.
     *
     * @param array<string> $generatedResources
     *
     * @return array<string>
     */
    protected function collectUnknownExclusions(array $generatedResources): array
    {
        return array_values(array_diff($this->excludedResources, $generatedResources));
    }

    /**
     * Only the enforced selection is checked: an unadopted resource declaring no responses is a
     * known state of the campaign, and failing on it would block every unrelated run.
     *
     * @param array<string, \Spryker\ApiPlatform\Contract\Coverage\TruthSet> $truthByResource
     * @param array<string, array<class-string>> $classesByShortName
     * @param array<string> $selectedResources
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\SchemaDefect>
     */
    protected function collectSchemaDefects(
        array $truthByResource,
        array $classesByShortName,
        array $selectedResources,
        string $applicationRoot,
    ): array {
        $schemaDefects = [];

        foreach ($selectedResources as $resource) {
            if (!isset($truthByResource[$resource])) {
                continue;
            }

            $undeclaredOperations = $truthByResource[$resource]->undeclaredResponseOperations;
            if ($undeclaredOperations === []) {
                continue;
            }

            $schemaFiles = $this->schemaSourceResolver->schemaFilesForAll(
                $classesByShortName[$resource] ?? [],
                $applicationRoot,
            );

            foreach ($undeclaredOperations as $operation) {
                $schemaDefects[] = new SchemaDefect($operation, $resource, $schemaFiles);
            }
        }

        return $schemaDefects;
    }

    /**
     * The schema-declared statuses of every generated resource, keyed by dispatch key. Read at test
     * runtime to judge observed responses against the contract, so it spans all resources rather
     * than the enforced selection — a test may exercise an operation of either.
     *
     * @return array<string, array<int>>
     */
    public function declaredResponses(string $applicationRoot): array
    {
        $declaredResponses = [];

        $classesByShortName = $this->groupResourceClassesByShortName($this->generatedResourcePath($applicationRoot));

        foreach ($this->loadTruthFromClasses($classesByShortName) as $truthSet) {
            $declaredResponses = TruthSet::mergeDeclaredResponses($declaredResponses, $truthSet->declaredResponses);
        }

        return $declaredResponses;
    }

    /**
     * The schema-derived response attribute paths of every generated resource, unioned per dispatch
     * key. Spans all resources rather than the enforced selection, like
     * {@see ContractCoverageRunner::declaredResponses()}.
     *
     * @return array<string, array<string>>
     */
    public function responseAttributes(string $applicationRoot): array
    {
        $pathsByDispatchKey = [];

        $classesByShortName = $this->groupResourceClassesByShortName($this->generatedResourcePath($applicationRoot));

        foreach ($this->loadTruthFromClasses($classesByShortName) as $truthSet) {
            foreach ($truthSet->responseAttributes as $responseAttribute) {
                $pathsByDispatchKey[$responseAttribute->dispatchKey][] = $responseAttribute->path;
            }
        }

        return array_map(
            static fn (array $paths): array => array_values(array_unique($paths)),
            $pathsByDispatchKey,
        );
    }

    /**
     * The short name of every generated resource, whether in scope or not.
     *
     * @return array<string>
     */
    public function resourceShortNames(string $applicationRoot): array
    {
        return array_keys($this->groupResourceClassesByShortName($this->generatedResourcePath($applicationRoot)));
    }

    /**
     * The short name of every generated resource whose schema marks a property `identifier: true`,
     * which decides whether a response owes a `data.id`. A short name backed by several classes
     * counts as declaring one as soon as any of them does. Spans all resources rather than the
     * enforced selection, like {@see ContractCoverageRunner::declaredResponses()}.
     *
     * @return array<string>
     */
    public function resourceShortNamesDeclaringIdentifier(string $applicationRoot): array
    {
        $shortNames = [];

        $classesByShortName = $this->groupResourceClassesByShortName($this->generatedResourcePath($applicationRoot));

        foreach ($classesByShortName as $shortName => $resourceClasses) {
            foreach ($resourceClasses as $resourceClass) {
                if ($this->truthLoader->declaresIdentifier($resourceClass)) {
                    $shortNames[] = $shortName;

                    break;
                }
            }
        }

        return $shortNames;
    }

    /**
     * Every enforced resource that is actually generated — what `--module` can select from.
     *
     * @return array<string>
     */
    public function getSelectableResources(string $applicationRoot): array
    {
        $classesByShortName = $this->groupResourceClassesByShortName($this->generatedResourcePath($applicationRoot));

        return $this->filterExcluded(array_keys($classesByShortName));
    }

    /**
     * @param array<string> $resources
     *
     * @return array<string>
     */
    protected function filterExcluded(array $resources): array
    {
        return array_values(array_diff($resources, $this->excludedResources));
    }

    protected function resourceNamespacePrefix(): string
    {
        return sprintf(static::RESOURCE_NAMESPACE_TEMPLATE, $this->normalizedApiType());
    }

    protected function generatedResourcePath(string $applicationRoot): string
    {
        return $applicationRoot . sprintf(static::GENERATED_RESOURCE_PATH_TEMPLATE, $this->normalizedApiType());
    }

    /**
     * Suites of one API type live under a `<ApiType>Api` path segment — `StorefrontApi`,
     * `BackendApi` — which is what keeps a Backend run from collecting Storefront annotations.
     */
    protected function testPathMarker(): string
    {
        return sprintf(static::TEST_PATH_MARKER_TEMPLATE, $this->normalizedApiType());
    }

    protected function glueApplication(): string
    {
        return sprintf(static::GLUE_APPLICATION_TEMPLATE, strtoupper($this->apiType));
    }

    protected function normalizedApiType(): string
    {
        return ApiTypeNormalizer::normalizeForGeneration($this->apiType);
    }

    /**
     * Several generated classes may share one resource short name (e.g. a second schema file adds
     * operations under another uriTemplate), so classes are grouped before loading — keying the
     * map per class would keep only the last one and silently shrink the truth. The grouping is
     * kept alongside the truth so a schema defect can name every file backing the resource.
     *
     * @return array<string, array<class-string>>
     */
    protected function groupResourceClassesByShortName(string $generatedResourceDirectory): array
    {
        $classesByShortName = [];

        foreach ($this->discoverResourceClasses($generatedResourceDirectory) as $resourceClass) {
            $shortName = $this->truthLoader->shortName($resourceClass);
            if ($shortName === null) {
                continue;
            }

            $classesByShortName[$shortName][] = $resourceClass;
        }

        return $classesByShortName;
    }

    /**
     * @param array<string, array<class-string>> $classesByShortName
     *
     * @return array<string, \Spryker\ApiPlatform\Contract\Coverage\TruthSet>
     */
    protected function loadTruthFromClasses(array $classesByShortName): array
    {
        $truthByResource = [];

        foreach ($classesByShortName as $shortName => $resourceClasses) {
            $truthByResource[$shortName] = $this->truthLoader->load($resourceClasses);
        }

        return $truthByResource;
    }

    /**
     * Without the generated resources there is no truth to measure against, and every counter would
     * read zero — a pass that means "nothing was checked". The gate has to say so instead: this is
     * the difference between a green run and a green tick.
     *
     * @throws \Spryker\ApiPlatform\Contract\Coverage\Exception\ResourcesNotGeneratedException
     */
    protected function assertResourcesGenerated(string $applicationRoot): void
    {
        $directory = $this->generatedResourcePath($applicationRoot);

        if ($this->discoverResourceClasses($directory) !== []) {
            return;
        }

        throw new ResourcesNotGeneratedException(sprintf(
            'No generated API resource found under "%s", so contract coverage cannot be measured. '
                . 'Generate them first: GLUE_APPLICATION=%s vendor/bin/glue api:generate',
            $directory,
            $this->glueApplication(),
        ));
    }

    /**
     * @return array<class-string>
     */
    protected function discoverResourceClasses(string $directory): array
    {
        $classes = [];

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            /** @var class-string $className */
            $className = $this->resourceNamespacePrefix() . basename($file, '.php');
            if (class_exists($className)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * @return array<class-string>
     */
    protected function discoverTestClasses(string $testsRoot): array
    {
        $classes = [];

        foreach ($this->findTestFiles($testsRoot) as $file) {
            $className = $this->classNameFromFile($file);
            if ($className !== null) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * @return array<string>
     */
    protected function findTestFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $found = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $found = array_merge($found, $this->findTestFiles($path));

                continue;
            }

            if (str_contains($path, $this->testPathMarker()) && str_ends_with($path, static::TEST_FILE_SUFFIX)) {
                $found[] = $path;
            }
        }

        return $found;
    }

    /**
     * @return class-string|null
     */
    protected function classNameFromFile(string $file): ?string
    {
        $source = (string)file_get_contents($file);

        if (!preg_match('/namespace\s+([^;]+);/', $source, $namespaceMatch)) {
            return null;
        }

        $shortName = $this->declaredClassNameResolver->resolve($source);
        if ($shortName === null) {
            return null;
        }

        // Test classes are not in composer's autoload map, so reflection only sees them once the
        // file has been loaded by hand.
        require_once $file;

        /** @var class-string $className */
        $className = trim($namespaceMatch[1]) . '\\' . $shortName;

        return $className;
    }
}
