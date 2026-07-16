<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Object;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Schema\Object\CanonicalObjectDefinitionResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Object
 * @group CanonicalObjectDefinitionResolverTest
 * Add your own group annotations below this line
 */
class CanonicalObjectDefinitionResolverTest extends Unit
{
    protected ApiUnitTester $tester;

    /**
     * Project layer overrides core per-key and adds new fields.
     *
     * @return void
     */
    public function testGivenSameObjectAcrossLayersWhenResolvingThenProjectLayerWins(): void
    {
        $resolver = $this->createResolver();
        $resolved = $resolver->resolve([
            ['name' => 'Address', 'layer' => 'core', 'properties' => ['zipCode' => ['type' => 'string', 'description' => 'core']]],
            ['name' => 'Address', 'layer' => 'project', 'properties' => ['zipCode' => ['type' => 'string', 'description' => 'project'], 'state' => ['type' => 'string']]],
        ]);

        $this->assertSame('project', $resolved['Address']['zipCode']['description']);
        $this->assertArrayHasKey('state', $resolved['Address']);
    }

    /**
     * extends inherits base fields, omit removes named keys, own properties add on top.
     *
     * @return void
     */
    public function testGivenExtendsAndOmitWhenResolvingThenAppliesExtendsThenOmitThenOwnProperties(): void
    {
        $resolver = $this->createResolver();
        $resolved = $resolver->resolve([
            ['name' => 'Address', 'layer' => 'core', 'properties' => ['id' => ['type' => 'integer'], 'zipCode' => ['type' => 'string']]],
            ['name' => 'AddressSnapshot', 'layer' => 'core', 'extends' => 'Address', 'omit' => ['id'], 'properties' => ['country' => ['type' => 'string']]],
        ]);

        $this->assertArrayNotHasKey('id', $resolved['AddressSnapshot']);
        $this->assertArrayHasKey('zipCode', $resolved['AddressSnapshot']); // inherited
        $this->assertArrayHasKey('country', $resolved['AddressSnapshot']); // own
    }

    /**
     * An extends cycle (A→B→A) throws ApiSchemaGenerationException.
     *
     * @return void
     */
    public function testGivenExtendsCycleWhenResolvingThenThrows(): void
    {
        $this->expectException(ApiSchemaGenerationException::class);
        $this->createResolver()->resolve([
            ['name' => 'A', 'layer' => 'core', 'extends' => 'B', 'properties' => []],
            ['name' => 'B', 'layer' => 'core', 'extends' => 'A', 'properties' => []],
        ]);
    }

    /**
     * Feature layer sits between core and project in precedence.
     *
     * @return void
     */
    public function testGivenAllThreeLayersWhenResolvingThenProjectWinsOverFeatureOverCore(): void
    {
        $resolver = $this->createResolver();
        $resolved = $resolver->resolve([
            ['name' => 'Item', 'layer' => 'core', 'properties' => ['price' => ['type' => 'integer', 'description' => 'core']]],
            ['name' => 'Item', 'layer' => 'feature', 'properties' => ['price' => ['type' => 'integer', 'description' => 'feature']]],
            ['name' => 'Item', 'layer' => 'project', 'properties' => ['price' => ['type' => 'integer', 'description' => 'project']]],
        ]);

        $this->assertSame('project', $resolved['Item']['price']['description']);
    }

    /**
     * Feature layer overrides core but is itself overridden by project.
     *
     * @return void
     */
    public function testGivenFeatureAndCoreOnlyWhenResolvingThenFeatureOverridesCore(): void
    {
        $resolver = $this->createResolver();
        $resolved = $resolver->resolve([
            ['name' => 'Item', 'layer' => 'core', 'properties' => ['price' => ['type' => 'integer', 'description' => 'core']]],
            ['name' => 'Item', 'layer' => 'feature', 'properties' => ['price' => ['type' => 'integer', 'description' => 'feature']]],
        ]);

        $this->assertSame('feature', $resolved['Item']['price']['description']);
    }

    /**
     * A self-referencing extends (A→A) throws ApiSchemaGenerationException.
     *
     * @return void
     */
    public function testGivenSelfExtendsWhenResolvingThenThrows(): void
    {
        $this->expectException(ApiSchemaGenerationException::class);
        $this->createResolver()->resolve([
            ['name' => 'A', 'layer' => 'core', 'extends' => 'A', 'properties' => []],
        ]);
    }

    /**
     * An extends pointing to a non-existent object throws ApiSchemaGenerationException.
     *
     * @return void
     */
    public function testGivenUnknownExtendsTargetWhenResolvingThenThrows(): void
    {
        $this->expectException(ApiSchemaGenerationException::class);
        $this->createResolver()->resolve([
            ['name' => 'AddressSnapshot', 'layer' => 'core', 'extends' => 'Adrress', 'properties' => ['zipCode' => ['type' => 'string']]],
        ]);
    }

    /**
     * The same objectName defined twice in the SAME layer (two source files) must throw,
     * and the message must name both source files.
     *
     * @return void
     */
    public function testGivenSameObjectNameInSameLayerFromTwoFilesWhenResolvingThenThrowsNamingBothFiles(): void
    {
        $resolver = $this->createResolver();

        try {
            $resolver->resolve([
                ['name' => 'Address', 'layer' => 'project', 'sourceFile' => '/src/Pyz/SomeModule/resources/api/storefront/objects/address.object.yml', 'properties' => ['zipCode' => ['type' => 'string']]],
                ['name' => 'Address', 'layer' => 'project', 'sourceFile' => '/config/api/objects/storefront/address.object.yml', 'properties' => ['city' => ['type' => 'string']]],
            ]);
            $this->fail('Expected ApiSchemaGenerationException was not thrown.');
        } catch (ApiSchemaGenerationException $exception) {
            $this->assertStringContainsString('/src/Pyz/SomeModule/resources/api/storefront/objects/address.object.yml', $exception->getMessage());
            $this->assertStringContainsString('/config/api/objects/storefront/address.object.yml', $exception->getMessage());
        }
    }

    /**
     * The same objectName across DIFFERENT layers still merges (existing override behavior), no throw.
     *
     * @return void
     */
    public function testGivenSameObjectNameAcrossDifferentLayersFromTwoFilesWhenResolvingThenMergesWithoutThrow(): void
    {
        $resolver = $this->createResolver();

        $resolved = $resolver->resolve([
            ['name' => 'Address', 'layer' => 'core', 'sourceFile' => '/src/Spryker/SomeModule/resources/api/storefront/objects/address.object.yml', 'properties' => ['zipCode' => ['type' => 'string']]],
            ['name' => 'Address', 'layer' => 'project', 'sourceFile' => '/config/api/objects/storefront/address.object.yml', 'properties' => ['city' => ['type' => 'string']]],
        ]);

        $this->assertArrayHasKey('zipCode', $resolved['Address']);
        $this->assertArrayHasKey('city', $resolved['Address']);
    }

    protected function createResolver(): CanonicalObjectDefinitionResolver
    {
        return new CanonicalObjectDefinitionResolver();
    }
}
