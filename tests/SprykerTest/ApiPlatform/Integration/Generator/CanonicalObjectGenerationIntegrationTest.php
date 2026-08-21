<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Generator;

use Codeception\Test\Unit;
use SplFileInfo;
use Spryker\ApiPlatform\Generator\CanonicalObjectRegistry;
use Spryker\ApiPlatform\Generator\ClassGenerator;
use Spryker\ApiPlatform\Schema\Object\CanonicalObjectDefinitionResolver;
use Spryker\ApiPlatform\Schema\Object\Loader\ObjectSchemaLoaderInterface;
use SprykerTest\ApiPlatform\ApiIntegrationTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Integration
 * @group Generator
 * @group CanonicalObjectGenerationIntegrationTest
 * Add your own group annotations below this line
 */
class CanonicalObjectGenerationIntegrationTest extends Unit
{
    protected const API_TYPE = 'Storefront';

    protected const CANONICAL_NAMESPACE = 'Generated\Api\Storefront';

    /**
     * @var array<string, string> Fixture file name → YAML body. Written to a temp dir per test and
     *     loaded through the real ObjectSchemaLoader. Kept inline (not under the gitignored `_data/`)
     *     so the fixtures are committed with the test and run in CI.
     */
    protected const OBJECT_FIXTURES = [
        'address.object.yml' => "object:\n"
            . "  name: Address\n"
            . "  properties:\n"
            . "    id:\n"
            . "      type: string\n"
            . "    street:\n"
            . "      type: string\n"
            . "    zipCode:\n"
            . "      type: string\n"
            . "    city:\n"
            . "      type: string\n",
        'address-snapshot.object.yml' => "object:\n"
            . "  name: AddressSnapshot\n"
            . "  extends: Address\n"
            . "  omit:\n"
            . "    - id\n"
            . "  properties:\n"
            . "    country:\n"
            . "      type: string\n",
    ];

    protected ApiIntegrationTester $tester;

    protected ?string $fixtureDir = null;

    protected function _before(): void
    {
        $this->fixtureDir = sprintf('%s/cc39249-canonical-%s', sys_get_temp_dir(), uniqid());

        $filesystem = new Filesystem();
        $filesystem->mkdir($this->fixtureDir);

        foreach (static::OBJECT_FIXTURES as $fileName => $body) {
            $filesystem->dumpFile(sprintf('%s/%s', $this->fixtureDir, $fileName), $body);
        }
    }

    protected function _after(): void
    {
        if ($this->fixtureDir === null) {
            return;
        }

        (new Filesystem())->remove($this->fixtureDir);
        $this->fixtureDir = null;
    }

    /**
     * Assertion 1 (collapse): two resources each tagging `objectName: Address` plus a resolved
     * `Address` canonical definition collapse to exactly ONE canonical value-object class in the
     * shared `Generated\Api\Storefront` namespace, and `Address` is reported as a known canonical
     * object name.
     */
    public function testGivenTwoResourcesTaggingSameObjectNameWhenGeneratingThenCollapsesOntoOneCanonicalClass(): void
    {
        // Arrange — load the real `address.object.yml` fixture through the object-schema loader,
        // resolve it, and feed the resolved definitions into the registry alongside two resource
        // schemas that both reference `objectName: Address`.
        $definitions = [$this->loadObjectFixture('address.object.yml')];
        $resolvedDefinitions = $this->createResolver()->resolve($definitions);

        $validatedSchemas = [
            'storefront_customers_addresses' => $this->createAddressReferencingResource('CustomersAddresses', 'customers-addresses'),
            'storefront_orders_shipping' => $this->createAddressReferencingResource('OrdersShipping', 'orders-shipping'),
        ];

        // Act
        $result = $this->createRegistry()->build($resolvedDefinitions, [], $validatedSchemas, static::API_TYPE);

        // Assert — Address is reported as a known canonical object name.
        $this->assertArrayHasKey('Address', $result->getKnownCanonicalObjectNames());

        // Assert — exactly one canonical class is emitted, in the shared canonical namespace, and it
        // carries the fixture's fields. Both referencing resources share this single class.
        $classes = $result->getCanonicalObjectClasses();
        $this->assertCount(1, $classes, 'Two objectName: Address references must collapse to one canonical class.');

        $this->assertArrayHasKey('Address', $classes, 'The canonical class must be keyed by the bare objectName.');

        $canonicalClassCode = $classes['Address'];
        $this->assertStringContainsString('namespace ' . static::CANONICAL_NAMESPACE . ';', $canonicalClassCode);
        $this->assertStringContainsString('final class Address', $canonicalClassCode);
        $this->assertStringContainsString('public ?string $street = null;', $canonicalClassCode);
        $this->assertStringContainsString('public ?string $zipCode = null;', $canonicalClassCode);
        $this->assertStringContainsString('public ?string $city = null;', $canonicalClassCode);
    }

    /**
     * Assertion 2 (reference typing): with `Address` known, BOTH referencing resources type the
     * `address` property to the canonical class, import it from the shared canonical namespace, and
     * emit NO per-resource `*AddressStorefrontObject` companion class.
     */
    public function testGivenReferencingResourcesWhenGeneratingThenTypeCanonicalClassAndEmitNoCompanion(): void
    {
        // Arrange
        $definitions = [$this->loadObjectFixture('address.object.yml')];
        $resolvedDefinitions = $this->createResolver()->resolve($definitions);

        $resourceA = $this->createAddressReferencingResource('CustomersAddresses', 'customers-addresses');
        $resourceB = $this->createAddressReferencingResource('OrdersShipping', 'orders-shipping');

        $registryResult = $this->createRegistry()->build(
            $resolvedDefinitions,
            [],
            ['a' => $resourceA, 'b' => $resourceB],
            static::API_TYPE,
        );
        $known = $registryResult->getKnownCanonicalObjectNames();

        $canonicalShortName = array_key_first($registryResult->getCanonicalObjectClasses());

        $classGenerator = $this->createClassGenerator();

        foreach (['CustomersAddresses' => $resourceA, 'OrdersShipping' => $resourceB] as $resource) {
            // Act
            $generatedResult = $classGenerator->generateAll($resource, static::API_TYPE, $known);
            $resourceCode = $generatedResult->getMainClassCode();

            // Assert — the property is typed to the canonical class and imported from the shared
            // canonical namespace.
            $this->assertStringContainsString(
                sprintf('public ?%s $address = null;', $canonicalShortName),
                $resourceCode,
            );
            $this->assertStringContainsString(
                sprintf('use %s\\%s;', static::CANONICAL_NAMESPACE, $canonicalShortName),
                $resourceCode,
            );

            // Assert — no per-resource companion class is emitted for the canonical property.
            $this->assertSame(
                [],
                $generatedResult->getNestedObjectClasses(),
                'A canonical objectName reference must not emit a per-resource companion class.',
            );
            $this->assertStringNotContainsString('AddressStorefrontObject', $resourceCode);
        }
    }

    /**
     * Assertion 3 (extends/omit): `AddressSnapshot` extends `Address`, omits `id`, and adds
     * `country`. The generated `AddressSnapshot` canonical class carries the inherited fields plus
     * `country` and lacks `id`.
     */
    public function testGivenObjectExtendingAnotherWithOmitWhenGeneratingThenProducesInheritedFieldsMinusOmittedPlusOwn(): void
    {
        // Arrange — load both the base and the extending fixture from disk.
        $definitions = [
            $this->loadObjectFixture('address.object.yml'),
            $this->loadObjectFixture('address-snapshot.object.yml'),
        ];
        $resolvedDefinitions = $this->createResolver()->resolve($definitions);

        // Act
        $result = $this->createRegistry()->build($resolvedDefinitions, [], [], static::API_TYPE);

        // Assert — both canonical objects are known.
        $known = $result->getKnownCanonicalObjectNames();
        $this->assertArrayHasKey('Address', $known);
        $this->assertArrayHasKey('AddressSnapshot', $known);

        // Assert — the snapshot class carries inherited fields + its own `country`, and lacks `id`.
        $snapshotCode = $this->findCanonicalClassFor('AddressSnapshot', $result->getCanonicalObjectClasses());
        $this->assertStringContainsString('public ?string $street = null;', $snapshotCode, 'Inherited base field must be present.');
        $this->assertStringContainsString('public ?string $zipCode = null;', $snapshotCode, 'Inherited base field must be present.');
        $this->assertStringContainsString('public ?string $city = null;', $snapshotCode, 'Inherited base field must be present.');
        $this->assertStringContainsString('public ?string $country = null;', $snapshotCode, 'Own field must be present.');
        $this->assertStringNotContainsString('$id', $snapshotCode, 'Omitted base field must be absent.');
    }

    /**
     * Resolves the canonical class for the given object name. A canonical class is keyed and named by
     * the bare `objectName` (e.g. `Address`, `AddressSnapshot`) — the same identity every referencing
     * resource imports and types.
     *
     * @param array<string, string> $canonicalObjectClasses
     */
    protected function findCanonicalClassFor(string $objectName, array $canonicalObjectClasses): string
    {
        $this->assertArrayHasKey(
            $objectName,
            $canonicalObjectClasses,
            sprintf('No canonical class keyed by "%s".', $objectName),
        );

        $code = $canonicalObjectClasses[$objectName];
        $this->assertStringContainsString(sprintf('final class %s', $objectName), $code);
        $this->assertStringContainsString('namespace ' . static::CANONICAL_NAMESPACE . ';', $code);

        return $code;
    }

    /**
     * A minimal resource schema with a single `objectName: Address` reference property.
     *
     * @return array<string, mixed>
     */
    protected function createAddressReferencingResource(string $name, string $shortName): array
    {
        return [
            'name' => $name,
            'shortName' => $shortName,
            'operations' => [],
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'identifier' => true,
                ],
                'address' => [
                    'type' => 'object',
                    'objectName' => 'Address',
                ],
            ],
        ];
    }

    /**
     * Loads a `*.object.yml` fixture from the test data tree through the REAL object-schema loader.
     *
     * @return array<string, mixed>
     */
    protected function loadObjectFixture(string $fileName): array
    {
        $path = sprintf('%s/%s', $this->fixtureDir, $fileName);

        return $this->createObjectSchemaLoader()->load(new SplFileInfo($path));
    }

    protected function createResolver(): CanonicalObjectDefinitionResolver
    {
        return $this->tester->getContainer()->get(CanonicalObjectDefinitionResolver::class);
    }

    protected function createRegistry(): CanonicalObjectRegistry
    {
        return $this->tester->getContainer()->get(CanonicalObjectRegistry::class);
    }

    protected function createClassGenerator(): ClassGenerator
    {
        return $this->tester->getContainer()->get(ClassGenerator::class);
    }

    protected function createObjectSchemaLoader(): ObjectSchemaLoaderInterface
    {
        return $this->tester->getContainer()->get(ObjectSchemaLoaderInterface::class);
    }
}
