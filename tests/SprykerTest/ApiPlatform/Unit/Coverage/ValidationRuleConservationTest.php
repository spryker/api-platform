<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ConstraintRuleMapper;
use Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use Symfony\Component\Yaml\Yaml;

/**
 * Conserves every rule the `*.validation.yml` sources declare into {@see SchemaTruthLoader}'s truth
 * set, by parsing those sources rather than by mirroring the loader — a loader that stops
 * descending would otherwise report a self-consistent PASS over a contract it cannot see.
 *
 * Containment, not equality: which operation a rule is active on stays the loader's business.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ValidationRuleConservationTest
 * Add your own group annotations below this line
 */
class ValidationRuleConservationTest extends Unit
{
    protected const string GENERATED_RESOURCE_DIRECTORY = '/src/Generated/Api/Storefront';

    protected const string RESOURCE_NAMESPACE_PREFIX = 'Generated\\Api\\Storefront\\';

    protected const string VALIDATION_SCHEMA_GLOB = '/src/Spryker/*/resources/api/storefront/*.validation.yml';

    protected const string CONSTRAINT_LENGTH = 'Length';

    protected const string SCHEMA_ROOT_KEY = 'resource';

    /**
     * @var array<string>
     */
    protected const array OPERATION_KEYS = ['get', 'post', 'patch', 'put', 'delete'];

    /**
     * Live validation gaps, not coverage bookkeeping: the only file declaring these sits behind an
     * `excludedPathFragments` entry, and `glue api:generate` reports them on every run
     * ({@see \Spryker\ApiPlatform\Schema\Validation\Collector\ExcludedValidationRuleCollector}).
     * Listing them keeps the conservation invariant biting for everything else meanwhile.
     *
     * @var array<string>
     */
    protected const array RULES_DECLARED_ONLY_IN_EXCLUDED_SCHEMAS = [
        'customers.acceptedTerms.NotNull',
        'customers.confirmPassword.Expression',
        'customers.password.NotCompromisedPassword',
        'refresh-tokens.refreshToken.Length.min',
    ];

    public function testGivenEveryDeclaredValidationRuleWhenLoadingTheTruthThenNoneIsMissing(): void
    {
        // Arrange
        $declaredRules = $this->declaredRulesFromSchemas();
        $this->assertNotEmpty($declaredRules, 'No validation schemas were found — the glob is wrong, not the contract.');

        // Act
        $loadedRules = $this->loadedRules();

        // Assert — anything here is a rule the schemas declare and the gate cannot see.
        $missing = array_values(array_diff($declaredRules, $loadedRules, static::RULES_DECLARED_ONLY_IN_EXCLUDED_SCHEMAS));
        sort($missing);
        $this->assertSame([], $missing, sprintf(
            "%d of %d declared validation rules never reach the truth set, so the gate cannot ask for them.\n"
                . 'Descend into the constraint that holds them in %s.',
            count($missing),
            count($declaredRules),
            SchemaTruthLoader::class,
        ));
    }

    /**
     * Every `<shortName>.<dotted attribute path>.<rule>` the storefront validation schemas declare.
     *
     * @return array<string>
     */
    protected function declaredRulesFromSchemas(): array
    {
        $rules = [];
        $generatedShortNames = $this->generatedShortNames();

        foreach ($this->validationSchemaFiles() as $file) {
            $shortName = $this->resourceShortNameFor($file);

            // A schema whose resource this application never generated (its module contributes to
            // another resource, or is not installed) has no class for the loader to reflect, so it
            // cannot be part of the invariant.
            if ($shortName === null || !isset($generatedShortNames[$shortName])) {
                continue;
            }

            foreach ($this->parseOperations($file) as $fields) {
                foreach ($fields as $field => $constraints) {
                    foreach ($this->walkDeclaredRules($constraints, (string)$field) as $rule) {
                        $rules[$shortName . '.' . $rule] = true;
                    }
                }
            }
        }

        return array_keys($rules);
    }

    /**
     * The same coordinates, taken from the loader's truth set.
     *
     * @return array<string>
     */
    protected function loadedRules(): array
    {
        $loader = ContractCoverageFactory::createSchemaTruthLoader();
        $rules = [];

        foreach ($this->generatedResourceClasses() as $resourceClass) {
            $shortName = $loader->shortName($resourceClass);
            if ($shortName === null) {
                continue;
            }

            foreach ($loader->load([$resourceClass])->validationConstraints as $validationConstraint) {
                $rules[$validationConstraint->resource . '.' . $validationConstraint->attribute . '.' . $validationConstraint->rule] = true;
            }
        }

        return array_keys($rules);
    }

    /**
     * Walks a declared constraint list, adding a path segment for each `Collection` field and
     * unwrapping the constraints that only group others. Written against the YAML shape on purpose:
     * it must not share code with the loader, or both would go blind together.
     *
     * @return array<string>
     */
    protected function walkDeclaredRules(mixed $constraints, string $path): array
    {
        $rules = [];

        foreach ($this->toList($constraints) as $constraint) {
            [$name, $options] = $this->splitConstraint($constraint);
            if ($name === null) {
                continue;
            }

            if (in_array($name, ConstraintRuleMapper::CASCADING, true)) {
                foreach ($this->cascadeTargets($options, $path) as [$nestedConstraints, $nestedPath]) {
                    foreach ($this->walkDeclaredRules($nestedConstraints, $nestedPath) as $rule) {
                        $rules[] = $rule;
                    }
                }

                continue;
            }

            if (in_array($name, ConstraintRuleMapper::INERT, true)) {
                continue;
            }

            foreach ($this->ruleNamesFor($name, $options) as $ruleName) {
                $rules[] = $path . '.' . $ruleName;
            }
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<array{0: mixed, 1: string}>
     */
    protected function cascadeTargets(array $options, string $path): array
    {
        $targets = [];

        if (isset($options['constraints'])) {
            $targets[] = [$options['constraints'], $path];
        }

        if (isset($options['fields']) && is_array($options['fields'])) {
            foreach ($options['fields'] as $field => $fieldConstraints) {
                $targets[] = [$fieldConstraints, $path . '.' . $field];
            }
        }

        return $targets;
    }

    /**
     * `Length` is the one constraint whose declared bounds decide which rules it stands for.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string>
     */
    protected function ruleNamesFor(string $constraintName, array $options): array
    {
        if ($constraintName !== static::CONSTRAINT_LENGTH) {
            return [$constraintName];
        }

        $ruleNames = [];
        if (isset($options['min'])) {
            $ruleNames[] = 'Length.min';
        }
        if (isset($options['max'])) {
            $ruleNames[] = 'Length.max';
        }

        return $ruleNames;
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>}
     */
    protected function splitConstraint(mixed $constraint): array
    {
        if (is_string($constraint)) {
            return [$constraint, []];
        }

        if (is_array($constraint) && count($constraint) === 1) {
            $name = (string)array_key_first($constraint);
            $options = $constraint[$name];

            return [$name, is_array($options) ? $options : []];
        }

        return [null, []];
    }

    /**
     * @return array<mixed>
     */
    protected function toList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * The operation-keyed field maps of one validation schema.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function parseOperations(string $file): array
    {
        $document = Yaml::parseFile($file) ?: [];

        // Most schemas are operation-keyed at the root, but some wrap everything in the resource
        // name first. Reading only the first shape yields an empty field map and a silent pass —
        // the same failure mode this whole test exists to prevent.
        if (!$this->isOperationKeyed($document)) {
            $unwrapped = [];
            foreach ($document as $operations) {
                if (!is_array($operations)) {
                    continue;
                }

                foreach ($operations as $operation => $fields) {
                    $unwrapped[$operation] = array_merge($unwrapped[$operation] ?? [], is_array($fields) ? $fields : []);
                }
            }

            $document = $unwrapped;
        }

        return array_filter($document, static fn (mixed $fields): bool => is_array($fields));
    }

    /**
     * @param array<mixed> $document
     */
    protected function isOperationKeyed(array $document): bool
    {
        foreach (array_keys($document) as $key) {
            if (in_array(strtolower((string)$key), static::OPERATION_KEYS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    protected function generatedShortNames(): array
    {
        $loader = ContractCoverageFactory::createSchemaTruthLoader();
        $shortNames = [];

        foreach ($this->generatedResourceClasses() as $resourceClass) {
            $shortName = $loader->shortName($resourceClass);
            if ($shortName !== null) {
                $shortNames[$shortName] = true;
            }
        }

        return $shortNames;
    }

    /**
     * The `shortName` of the resource a validation schema belongs to, which is not always its file
     * name — `cart-items.validation.yml` validates the `items` resource. Null when the sibling
     * resource schema does not name one, or when nothing generated that resource here.
     */
    protected function resourceShortNameFor(string $validationFile): ?string
    {
        $resourceFile = preg_replace('/\.validation\.yml$/', '.resource.yml', $validationFile);
        if ($resourceFile === null || !is_file($resourceFile)) {
            return null;
        }

        $document = Yaml::parseFile($resourceFile) ?: [];
        $shortName = $document[static::SCHEMA_ROOT_KEY]['shortName'] ?? null;

        return is_string($shortName) ? $shortName : null;
    }

    /**
     * @return array<string>
     */
    protected function validationSchemaFiles(): array
    {
        return glob($this->projectRoot() . static::VALIDATION_SCHEMA_GLOB) ?: [];
    }

    /**
     * @return array<class-string>
     */
    protected function generatedResourceClasses(): array
    {
        $classes = [];

        foreach (glob($this->projectRoot() . static::GENERATED_RESOURCE_DIRECTORY . '/*.php') ?: [] as $file) {
            /** @var class-string $className */
            $className = static::RESOURCE_NAMESPACE_PREFIX . basename($file, '.php');
            if (class_exists($className)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    protected function projectRoot(): string
    {
        return defined('APPLICATION_ROOT_DIR') ? APPLICATION_ROOT_DIR : dirname(codecept_data_dir(), 3);
    }
}
