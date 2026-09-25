<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Existence;
use Symfony\Component\Validator\Constraints\Sequentially;

/**
 * Builds a {@see TruthSet} from the generated API Platform resource classes by reflection, without
 * booting a kernel. It reads the `#[ApiResource]` attribute (operations, short name, provider) and
 * the `#[ApiProperty]` / `Symfony\Component\Validator\Constraints\*` attributes on the properties.
 *
 * uriTemplate resolution mirrors API Platform's own defaults. An item `GET` with no provider is
 * classified non-servable, and error coverage is schema-declared rather than synthesised — see
 * {@see TruthSet}.
 */
class SchemaTruthLoader
{
    protected const int STATUS_CLIENT_ERROR_MIN = 400;

    protected const string CONSTRAINT_NAMESPACE_PREFIX = 'Symfony\\Component\\Validator\\Constraints\\';

    protected const string CONSTRAINT_LENGTH = 'Length';

    protected const string CONSTRAINT_VALID = 'Valid';

    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeTruthCollector::GENERATED_API_NAMESPACE_PREFIX
     */
    protected const string GENERATED_API_NAMESPACE_PREFIX = 'Generated\\Api\\';

    /**
     * @uses \Spryker\ApiPlatform\Generator\ResourceAttributeGenerator::EXTRA_PROPERTY_DECLARED_RESPONSES
     */
    protected const string EXTRA_PROPERTY_DECLARED_RESPONSES = 'declaredResponses';

    /**
     * @uses \Spryker\ApiPlatform\Generator\ResourceAttributeGenerator::EXTRA_PROPERTY_INTERNAL
     */
    protected const string EXTRA_PROPERTY_INTERNAL = 'internal';

    /**
     * @uses \Spryker\ApiPlatform\Generator\PropertyAttributeGenerator::EXTRA_PROPERTY_SYNTHETIC_IDENTIFIER
     */
    protected const string EXTRA_PROPERTY_SYNTHETIC_IDENTIFIER = 'syntheticIdentifier';

    protected const string DEFAULT_IDENTIFIER = 'id';

    protected const string VERB_GET = 'GET';

    protected const string VERB_POST = 'POST';

    protected const string VERB_PUT = 'PUT';

    protected const string VERB_PATCH = 'PATCH';

    protected const string VERB_DELETE = 'DELETE';

    protected const string VALIDATION_CONTEXT_GROUPS = 'groups';

    public function __construct(
        protected ConstraintRuleMapper $ruleMapper,
        protected ResponseAttributeTruthCollector $responseAttributeTruthCollector,
    ) {
    }

    /**
     * @param array<class-string> $resourceClasses
     */
    public function load(array $resourceClasses): TruthSet
    {
        $truthSets = [];

        foreach ($resourceClasses as $resourceClass) {
            $truthSet = $this->loadResource($resourceClass);
            if ($truthSet !== null) {
                $truthSets[] = $truthSet;
            }
        }

        return $this->mergeTruthSets($truthSets);
    }

    /**
     * The truth of one generated resource class, or null when the class carries no `#[ApiResource]`.
     *
     * @param class-string $resourceClass
     */
    protected function loadResource(string $resourceClass): ?TruthSet
    {
        $reflectionClass = new ReflectionClass($resourceClass);
        $apiResourceAttributes = $reflectionClass->getAttributes(ApiResource::class);
        if ($apiResourceAttributes === []) {
            return null;
        }

        /** @var \ApiPlatform\Metadata\ApiResource $apiResource */
        $apiResource = $apiResourceAttributes[0]->newInstance();
        $shortName = (string)$apiResource->getShortName();
        $identifier = $this->resolveIdentifier($reflectionClass);

        $operationTruth = $this->collectOperations($apiResource, $shortName, $identifier);

        return new TruthSet(
            $operationTruth['servableOperations'],
            $operationTruth['nonServableOperations'],
            $this->loadValidationConstraints($reflectionClass, $shortName, $operationTruth['inputOperations']),
            $operationTruth['undeclaredResponseOperations'],
            $operationTruth['declaredResponses'],
            $operationTruth['internalOperations'],
            $this->collectResponseAttributes(
                $reflectionClass,
                $operationTruth['servableOperations'],
                $operationTruth['declaredResponses'],
            ),
        );
    }

    /**
     * The response attributes of a resource are demanded of its success operations only: an error
     * response carries no resource body, and a `DELETE` answers no content at all. The declared
     * error responses already in the servable set are filtered out with them.
     *
     * An operation whose schema declares no 2xx at all is filtered out too. The bare collection URL
     * of a resource addressed only by id - `GET /category-nodes`, which exists to answer 400 rather
     * than a route-not-found - never returns a resource body, so there is nothing for a test to
     * assert and nothing the gate can demand.
     *
     * @param \ReflectionClass<object> $reflectionClass
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $servableOperations
     * @param array<string, array<int>> $declaredResponses
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute>
     */
    protected function collectResponseAttributes(
        ReflectionClass $reflectionClass,
        array $servableOperations,
        array $declaredResponses,
    ): array {
        $successOperations = array_values(array_filter(
            $servableOperations,
            fn (ApiOperation $operation): bool => $operation->status === null
                && strtoupper($operation->verb) !== static::VERB_DELETE
                && $this->declaresSuccessResponse($declaredResponses[$operation->dispatchKey()] ?? []),
        ));

        return $this->responseAttributeTruthCollector->collect($reflectionClass, $successOperations);
    }

    /**
     * An operation that declares nothing is a schema defect reported in its own right
     * ({@see TruthSet::$undeclaredResponseOperations}); it keeps demanding its response attributes,
     * because the missing declaration is the thing to fix rather than a licence to skip coverage.
     * Only an operation that declares statuses and no 2xx among them is exempt.
     *
     * @param array<int> $declaredStatuses
     */
    protected function declaresSuccessResponse(array $declaredStatuses): bool
    {
        if ($declaredStatuses === []) {
            return true;
        }

        foreach ($declaredStatuses as $status) {
            if ($status < static::STATUS_CLIENT_ERROR_MIN) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{servableOperations: array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>, nonServableOperations: array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>, internalOperations: array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>, undeclaredResponseOperations: array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>, declaredResponses: array<string, array<int>>, inputOperations: array<array{operation: \Spryker\ApiPlatform\Contract\Coverage\ApiOperation, groups: array<string>}>}
     */
    protected function collectOperations(ApiResource $apiResource, string $shortName, string $identifier): array
    {
        $resourceProvider = $apiResource->getProvider();

        $servableOperations = [];
        $nonServableOperations = [];
        $internalOperations = [];
        $undeclaredResponseOperations = [];
        $declaredResponses = [];
        $inputOperations = [];

        // `Operations` is keyed on the base `Operation`, so a resource may carry one that is not
        // HTTP and cannot answer `getMethod()`.
        /** @var iterable<\ApiPlatform\Metadata\Operation> $operations */
        $operations = $apiResource->getOperations() ?? [];

        foreach ($operations as $operation) {
            if (!$operation instanceof HttpOperation) {
                continue;
            }

            $apiOperation = new ApiOperation(
                $operation->getMethod(),
                $this->resolveUriTemplate($operation, $shortName, $identifier),
                addressesCollection: $operation instanceof CollectionOperationInterface,
            );

            if ($this->isNonServable($operation, $resourceProvider)) {
                if ($this->isInternal($operation)) {
                    $internalOperations[] = $apiOperation;

                    continue;
                }

                $nonServableOperations[] = $apiOperation;

                continue;
            }

            $declaredStatuses = $this->declaredResponseStatuses($operation);

            if ($declaredStatuses === []) {
                $undeclaredResponseOperations[] = $apiOperation;
            }

            if ($declaredStatuses !== []) {
                $declaredResponses = TruthSet::mergeDeclaredResponses(
                    $declaredResponses,
                    [$apiOperation->dispatchKey() => $declaredStatuses],
                );
            }

            $servableOperations = array_merge(
                $servableOperations,
                [$apiOperation],
                $this->deriveDeclaredErrorResponses($declaredStatuses, $apiOperation),
            );

            if ($this->isInputOperation($operation)) {
                $inputOperations[] = [
                    'operation' => $apiOperation,
                    'groups' => $this->resolveOperationValidationGroups($operation),
                ];
            }
        }

        return [
            'servableOperations' => $servableOperations,
            'nonServableOperations' => $nonServableOperations,
            'internalOperations' => $internalOperations,
            'undeclaredResponseOperations' => $undeclaredResponseOperations,
            'declaredResponses' => $declaredResponses,
            'inputOperations' => $inputOperations,
        ];
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\TruthSet> $truthSets
     */
    protected function mergeTruthSets(array $truthSets): TruthSet
    {
        $servableOperations = [];
        $nonServableOperations = [];
        $internalOperations = [];
        $validationConstraints = [];
        $undeclaredResponseOperations = [];
        $declaredResponses = [];
        $responseAttributes = [];

        foreach ($truthSets as $truthSet) {
            $servableOperations = array_merge($servableOperations, $truthSet->servableOperations);
            $nonServableOperations = array_merge($nonServableOperations, $truthSet->nonServableOperations);
            $internalOperations = array_merge($internalOperations, $truthSet->internalOperations);
            $validationConstraints = array_merge($validationConstraints, $truthSet->validationConstraints);
            $undeclaredResponseOperations = array_merge($undeclaredResponseOperations, $truthSet->undeclaredResponseOperations);
            $responseAttributes = array_merge($responseAttributes, $truthSet->responseAttributes);
            $declaredResponses = TruthSet::mergeDeclaredResponses($declaredResponses, $truthSet->declaredResponses);
        }

        return new TruthSet(
            $servableOperations,
            $nonServableOperations,
            $validationConstraints,
            $undeclaredResponseOperations,
            $declaredResponses,
            $internalOperations,
            $responseAttributes,
        );
    }

    /**
     * @param class-string $resourceClass
     */
    public function shortName(string $resourceClass): ?string
    {
        $apiResourceAttributes = (new ReflectionClass($resourceClass))->getAttributes(ApiResource::class);
        if ($apiResourceAttributes === []) {
            return null;
        }

        /** @var \ApiPlatform\Metadata\ApiResource $apiResource */
        $apiResource = $apiResourceAttributes[0]->newInstance();
        $shortName = $apiResource->getShortName();

        return $shortName !== null ? (string)$shortName : null;
    }

    /**
     * Whether the resource marks any property `identifier: true`. Distinct from
     * {@see SchemaTruthLoader::resolveIdentifier()}, which falls back to `id` and so cannot tell a
     * declared identifier from an assumed one — the difference decides whether a response owes a
     * `data.id` at all.
     *
     * @param class-string $resourceClass
     */
    public function declaresIdentifier(string $resourceClass): bool
    {
        foreach ((new ReflectionClass($resourceClass))->getProperties() as $property) {
            foreach ($property->getAttributes(ApiProperty::class) as $attribute) {
                /** @var \ApiPlatform\Metadata\ApiProperty $apiProperty */
                $apiProperty = $attribute->newInstance();

                if ($apiProperty->isIdentifier() !== true) {
                    continue;
                }

                // A singleton resource declares an identifier only so API Platform can mint an IRI
                // for it, and its value is the resource type itself. The wire carries `data.id:
                // null`, as the legacy Glue REST API did, so demanding one would be demanding a
                // change of contract rather than a fix.
                if (($apiProperty->getExtraProperties()[static::EXTRA_PROPERTY_SYNTHETIC_IDENTIFIER] ?? null) === true) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    protected function resolveUriTemplate(HttpOperation $operation, string $shortName, string $identifier): string
    {
        $uriTemplate = $operation->getUriTemplate();
        if ($uriTemplate !== null) {
            return UriTemplateNormalizer::normalize($uriTemplate);
        }

        // Collection GETs and creates (POST) address the collection itself, so carry no identifier;
        // item operations (GET item, PATCH, PUT, DELETE) append the resource identifier — mirroring
        // API Platform's own default uriTemplate generation.
        if ($operation instanceof CollectionOperationInterface || $operation->getMethod() === static::VERB_POST) {
            return '/' . $shortName;
        }

        return '/' . $shortName . '/{' . $identifier . '}';
    }

    /**
     * The statuses the resource schema declares for an operation, read back from the generated
     * `openapi: new Operation(responses: [...])`. Empty means the schema declares nothing, which is
     * a defect rather than a licence to synthesise.
     *
     * An operation hidden with `openapi: false` carries `false` there instead of an Operation, so
     * its declarations arrive through `extraProperties` - see
     * {@see \Spryker\ApiPlatform\Generator\ResourceAttributeGenerator::addDeclaredResponsesExtraProperty()}.
     * A hidden operation is still contract: the bare legacy route answering 400-with-code-1202
     * rather than a route-not-found is what its tests pin.
     *
     * @return array<int>
     */
    protected function declaredResponseStatuses(HttpOperation $operation): array
    {
        $openapi = $operation->getOpenapi();

        if (!$openapi instanceof OpenApiOperation) {
            return $this->hiddenOperationResponseStatuses($operation);
        }

        $statuses = array_map(
            static fn (int|string $status): int => (int)$status,
            array_keys($openapi->getResponses() ?? []),
        );

        return $this->normaliseStatuses($statuses);
    }

    /**
     * @return array<int>
     */
    protected function hiddenOperationResponseStatuses(HttpOperation $operation): array
    {
        $declaredResponses = $operation->getExtraProperties()[static::EXTRA_PROPERTY_DECLARED_RESPONSES] ?? null;

        if (!is_array($declaredResponses)) {
            return [];
        }

        $statuses = array_map(
            static fn (int|string $status): int => (int)$status,
            array_values($declaredResponses),
        );

        return $this->normaliseStatuses($statuses);
    }

    /**
     * @param array<int> $statuses
     *
     * @return array<int>
     */
    protected function normaliseStatuses(array $statuses): array
    {
        sort($statuses);

        return array_values(array_unique($statuses));
    }

    /**
     * One coverage item per declared error status. Nothing is synthesised: if the schema says an
     * operation answers 403 and never 404, the gate demands a 403 test and no 404 test.
     *
     * @param array<int> $declaredStatuses
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected function deriveDeclaredErrorResponses(array $declaredStatuses, ApiOperation $apiOperation): array
    {
        $errorResponses = [];

        foreach ($declaredStatuses as $status) {
            if ($status >= static::STATUS_CLIENT_ERROR_MIN) {
                $errorResponses[] = new ApiOperation($apiOperation->verb, $apiOperation->uriTemplate, $status);
            }
        }

        return $errorResponses;
    }

    protected function isNonServable(HttpOperation $operation, callable|string|null $resourceProvider): bool
    {
        return $operation->getMethod() === static::VERB_GET
            && !$operation instanceof CollectionOperationInterface
            && $operation->getProvider() === null
            && $resourceProvider === null;
    }

    /**
     * Whether the schema says the operation is unreachable on purpose. It keys on the `internal`
     * flag the schema author declares, not on the `NotFoundAction` controller that implements it:
     * classification follows stated intent, so an operation that merely happens to be routed to a
     * framework class is still reported as the accident it is.
     */
    protected function isInternal(HttpOperation $operation): bool
    {
        return ($operation->getExtraProperties()[static::EXTRA_PROPERTY_INTERNAL] ?? null) === true;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    protected function resolveIdentifier(ReflectionClass $reflectionClass): string
    {
        foreach ($reflectionClass->getProperties() as $property) {
            foreach ($property->getAttributes(ApiProperty::class) as $attribute) {
                /** @var \ApiPlatform\Metadata\ApiProperty $apiProperty */
                $apiProperty = $attribute->newInstance();
                if ($apiProperty->isIdentifier() === true) {
                    return $property->getName();
                }
            }
        }

        return static::DEFAULT_IDENTIFIER;
    }

    protected function isInputOperation(HttpOperation $operation): bool
    {
        return in_array($operation->getMethod(), [static::VERB_POST, static::VERB_PUT, static::VERB_PATCH], true);
    }

    /**
     * The groups an operation validates with, mirroring Symfony's semantics: an operation without a
     * `validationContext` validates the `Default` group.
     *
     * @return array<string>
     */
    protected function resolveOperationValidationGroups(HttpOperation $operation): array
    {
        $groups = $operation->getValidationContext()[static::VALIDATION_CONTEXT_GROUPS] ?? null;
        if ($groups === null) {
            return [Constraint::DEFAULT_GROUP];
        }

        return array_values(array_map(static fn (mixed $group): string => (string)$group, (array)$groups));
    }

    /**
     * Emits one entry per rule per input operation the rule is ACTIVE on — active meaning the
     * constraint's groups intersect the operation's validation groups (both defaulting to
     * `Default`, mirroring Symfony). A `NotBlank` scoped to the create group therefore requires a
     * test on POST but none on PATCH; one carried by both groups requires one test per operation.
     *
     * @param \ReflectionClass<object> $reflectionClass
     * @param array<array{operation: \Spryker\ApiPlatform\Contract\Coverage\ApiOperation, groups: array<string>}> $inputOperations
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    protected function loadValidationConstraints(ReflectionClass $reflectionClass, string $shortName, array $inputOperations): array
    {
        return $this->collectClassConstraints($reflectionClass, $shortName, $inputOperations, '', []);
    }

    /**
     * Walks every property of a resource or value-object class, descending through cascading
     * constraints so a nested rule is keyed by its dotted path (`productConfigurationInstance.
     * isComplete`) exactly as a {@see \Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation}
     * annotation names it.
     *
     * @param \ReflectionClass<object> $reflectionClass
     * @param array<array{operation: \Spryker\ApiPlatform\Contract\Coverage\ApiOperation, groups: array<string>}> $inputOperations
     * @param array<string, true> $visitedClasses Classes already cascaded into on this path.
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    protected function collectClassConstraints(
        ReflectionClass $reflectionClass,
        string $shortName,
        array $inputOperations,
        string $pathPrefix,
        array $visitedClasses
    ): array {
        $validationConstraints = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $path = $pathPrefix === '' ? $property->getName() : $pathPrefix . '.' . $property->getName();

            foreach ($property->getAttributes() as $attribute) {
                if (!str_starts_with($attribute->getName(), static::CONSTRAINT_NAMESPACE_PREFIX)) {
                    continue;
                }

                $constraint = $attribute->newInstance();
                if (!$constraint instanceof Constraint) {
                    continue;
                }

                $nested = $this->collectConstraint($constraint, $property, $shortName, $inputOperations, $path, $visitedClasses);
                foreach ($nested as $validationConstraint) {
                    $validationConstraints[] = $validationConstraint;
                }
            }
        }

        return $validationConstraints;
    }

    /**
     * Resolves one constraint into coverage items. `Collection` adds a path segment per field;
     * `All`, `Optional`, `Required` and `Sequentially` only unwrap; `Valid` cascades onto the
     * property's generated value-object class.
     *
     * @param \ReflectionProperty|null $property Absent below a `Collection`, where fields are array keys rather than typed properties.
     * @param array<array{operation: \Spryker\ApiPlatform\Contract\Coverage\ApiOperation, groups: array<string>}> $inputOperations
     * @param array<string, true> $visitedClasses
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    protected function collectConstraint(
        Constraint $constraint,
        ?ReflectionProperty $property,
        string $shortName,
        array $inputOperations,
        string $path,
        array $visitedClasses
    ): array {
        $constraintShortName = (new ReflectionClass($constraint))->getShortName();

        if ($this->ruleMapper->isCascading($constraintShortName)) {
            return $this->cascade($constraint, $constraintShortName, $property, $shortName, $inputOperations, $path, $visitedClasses);
        }

        [$lengthMin, $lengthMax] = $this->resolveLengthBounds($constraintShortName, $constraint);

        $rules = $this->ruleMapper->rulesFor($constraintShortName, $lengthMin, $lengthMax);
        if ($rules === []) {
            return [];
        }

        $constraintGroups = (array)$constraint->groups;
        $validationConstraints = [];

        foreach ($inputOperations as $inputOperation) {
            if (array_intersect($constraintGroups, $inputOperation['groups']) === []) {
                continue;
            }

            foreach ($rules as $rule) {
                $validationConstraints[] = new ValidationConstraint(
                    $shortName,
                    $path,
                    $rule,
                    $inputOperation['operation']->verb,
                    $inputOperation['operation']->uriTemplate,
                );
            }
        }

        return $validationConstraints;
    }

    /**
     * @param array<array{operation: \Spryker\ApiPlatform\Contract\Coverage\ApiOperation, groups: array<string>}> $inputOperations
     * @param array<string, true> $visitedClasses
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint>
     */
    protected function cascade(
        Constraint $constraint,
        string $constraintShortName,
        ?ReflectionProperty $property,
        string $shortName,
        array $inputOperations,
        string $path,
        array $visitedClasses
    ): array {
        if ($constraintShortName === static::CONSTRAINT_VALID) {
            $valueObjectClass = $this->resolveValueObjectClass($property);
            if ($valueObjectClass === null || isset($visitedClasses[$valueObjectClass])) {
                return [];
            }

            return $this->collectClassConstraints(
                new ReflectionClass($valueObjectClass),
                $shortName,
                $inputOperations,
                $path,
                $visitedClasses + [$valueObjectClass => true],
            );
        }

        // `Collection` keys its children by field name and each one earns a path segment;
        // `All`, `Optional`, `Required` and `Sequentially` only wrap, so their children keep the
        // current path — a list index is data, not contract.
        $nestedByPath = [];
        if ($constraint instanceof Collection) {
            foreach ($constraint->fields as $field => $fieldConstraints) {
                $nestedByPath[] = [$path . '.' . $field, $fieldConstraints];
            }
        } elseif ($constraint instanceof All || $constraint instanceof Existence || $constraint instanceof Sequentially) {
            $nestedByPath[] = [$path, $constraint->constraints];
        }

        $validationConstraints = [];

        foreach ($nestedByPath as [$nestedPath, $nestedConstraints]) {
            foreach ($this->toConstraintList($nestedConstraints) as $nestedConstraint) {
                $nested = $this->collectConstraint($nestedConstraint, null, $shortName, $inputOperations, $nestedPath, $visitedClasses);
                foreach ($nested as $validationConstraint) {
                    $validationConstraints[] = $validationConstraint;
                }
            }
        }

        return $validationConstraints;
    }

    /**
     * Symfony normalises a `Collection` field to a single `Required`/`Optional` instance but leaves
     * `All`/`Optional`/`Required` children as a list, so both shapes reach this walk.
     *
     * @return array<\Symfony\Component\Validator\Constraint>
     */
    protected function toConstraintList(mixed $constraints): array
    {
        if ($constraints instanceof Constraint) {
            return [$constraints];
        }

        if (!is_array($constraints)) {
            return [];
        }

        return array_values(array_filter(
            $constraints,
            static fn (mixed $constraint): bool => $constraint instanceof Constraint,
        ));
    }

    /**
     * The generated value object a `Valid` cascade lands on, or null when the property is untyped
     * or points outside the generated API namespace.
     *
     * @return class-string|null
     */
    protected function resolveValueObjectClass(?ReflectionProperty $property): ?string
    {
        $type = $property?->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $className = $type->getName();

        return str_starts_with($className, static::GENERATED_API_NAMESPACE_PREFIX) && class_exists($className)
            ? $className
            : null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function resolveLengthBounds(string $constraintShortName, Constraint $constraint): array
    {
        if ($constraintShortName !== static::CONSTRAINT_LENGTH) {
            return [null, null];
        }

        /** @var \Symfony\Component\Validator\Constraints\Length $length */
        $length = $constraint;

        return [is_int($length->min) ? $length->min : null, is_int($length->max) ? $length->max : null];
    }
}
