<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use ReflectionClass;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeTruthCollector;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ResponseAttributesDefaultPredicateFixtureCollector;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ResponseAttributesFixturePaginationObject;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ResponseAttributesFixtureResource;

/**
 * Pins the derivation rules against fixture resources carrying one property per rule, and the
 * default nested-object predicate separately against generated class names.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ResponseAttributeTruthCollectorTest
 * Add your own group annotations below this line
 */
class ResponseAttributeTruthCollectorTest extends Unit
{
    protected const string GENERATED_NESTED_OBJECT_CLASS = 'Generated\\Api\\Storefront\\ResponseAttributesFixture\\ResponseAttributesGeneratedNestedObject';

    protected const string GENERATED_SIBLING_RESOURCE_CLASS = 'Generated\\Api\\Storefront\\ResponseAttributesFixture\\ResponseAttributesGeneratedSiblingResource';

    public function testGivenAResourceWhenCollectingThenOnlyReadableRequiredPathsAreReturnedPerOperation(): void
    {
        // Arrange
        $collector = new ResponseAttributeTruthCollector(
            static fn (string $class): bool => str_ends_with($class, 'Object'),
        );
        $operations = [
            new ApiOperation('GET', '/response-attributes-fixture'),
            new ApiOperation('POST', '/response-attributes-fixture'),
        ];

        // Act
        $responseAttributes = $collector->collect(new ReflectionClass(ResponseAttributesFixtureResource::class), $operations);

        // Assert — an array declaring no item fields demands only its presence, and derivation
        // stops one nested level down.
        $this->assertSame([
            'GET /response-attributes-fixture  name',
            'GET /response-attributes-fixture  lines[].sku',
            'GET /response-attributes-fixture  lines[].quantity',
            'GET /response-attributes-fixture  tags',
            'GET /response-attributes-fixture  pagination.numFound',
            'GET /response-attributes-fixture  pagination.cursor',
            'POST /response-attributes-fixture  name',
            'POST /response-attributes-fixture  lines[].sku',
            'POST /response-attributes-fixture  lines[].quantity',
            'POST /response-attributes-fixture  tags',
            'POST /response-attributes-fixture  pagination.numFound',
            'POST /response-attributes-fixture  pagination.cursor',
        ], $this->responseAttributeKeys($responseAttributes));
    }

    public function testGivenAGeneratedNestedShapeWhenTheDefaultPredicateDecidesThenItDescends(): void
    {
        // Arrange — aliased onto a real fixture class, so the name the predicate sees is a
        // generated one while the class behind it exists without the generated tree.
        $generatedNestedObjectClass = $this->aliasFixturesIntoTheGeneratedNamespace(static::GENERATED_NESTED_OBJECT_CLASS);
        $collector = new ResponseAttributesDefaultPredicateFixtureCollector();

        // Act
        $isNestedObjectClass = $collector->isGeneratedNestedObjectClass($generatedNestedObjectClass);

        // Assert — a leading backslash on the namespace prefix would never match what
        // `ReflectionNamedType::getName()` returns, and every nested shape would go undemanded.
        $this->assertTrue($isNestedObjectClass);
    }

    public function testGivenAGeneratedSiblingResourceWhenTheDefaultPredicateDecidesThenItDoesNotDescend(): void
    {
        // Arrange — a sibling resource sits under the same namespace as a nested shape and is told
        // apart only by carrying `#[ApiResource]`.
        $generatedSiblingResourceClass = $this->aliasFixturesIntoTheGeneratedNamespace(static::GENERATED_SIBLING_RESOURCE_CLASS);
        $collector = new ResponseAttributesDefaultPredicateFixtureCollector();

        // Act
        $isNestedObjectClass = $collector->isGeneratedNestedObjectClass($generatedSiblingResourceClass);

        // Assert
        $this->assertFalse($isNestedObjectClass);
    }

    public function testGivenAClassOutsideTheGeneratedNamespaceWhenTheDefaultPredicateDecidesThenItDoesNotDescend(): void
    {
        // Arrange
        $collector = new ResponseAttributesDefaultPredicateFixtureCollector();

        // Act
        $isNestedObjectClass = $collector->isGeneratedNestedObjectClass(ResponseAttributesFixturePaginationObject::class);

        // Assert — carrying no `#[ApiResource]` is not enough; the class has to be generated.
        $this->assertFalse($isNestedObjectClass);
    }

    /**
     * Aliases both generated names onto real fixture classes and hands the requested one back as a
     * class name: the predicate reflects what it is given, so the name only reaches it once the
     * alias is in place, which the guard is here to prove rather than assume.
     *
     * @return class-string
     */
    protected function aliasFixturesIntoTheGeneratedNamespace(string $class): string
    {
        if (!class_exists(static::GENERATED_NESTED_OBJECT_CLASS, false)) {
            class_alias(ResponseAttributesFixturePaginationObject::class, static::GENERATED_NESTED_OBJECT_CLASS);
        }
        if (!class_exists(static::GENERATED_SIBLING_RESOURCE_CLASS, false)) {
            class_alias(ResponseAttributesFixtureResource::class, static::GENERATED_SIBLING_RESOURCE_CLASS);
        }
        if (!class_exists($class)) {
            $this->fail(sprintf('Aliasing "%s" into the generated namespace did not take effect.', $class));
        }

        return $class;
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $responseAttributes
     *
     * @return array<string>
     */
    protected function responseAttributeKeys(array $responseAttributes): array
    {
        return array_map(static fn (ResponseAttribute $responseAttribute): string => $responseAttribute->key(), $responseAttributes);
    }
}
