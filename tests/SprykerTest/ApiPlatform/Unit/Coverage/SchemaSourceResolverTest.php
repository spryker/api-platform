<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\SchemaSourceResolver;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\GeneratedHeaderFixtureResource;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group SchemaSourceResolverTest
 * Add your own group annotations below this line
 */
class SchemaSourceResolverTest extends Unit
{
    protected const string APPLICATION_ROOT = '/application/root';

    public function testGivenAGeneratedResourceHeaderWhenResolvingThenEverySourceSchemaFileIsReturned(): void
    {
        // Arrange
        $resolver = new SchemaSourceResolver();

        // Act
        $schemaFiles = $resolver->schemaFilesFor(GeneratedHeaderFixtureResource::class);

        // Assert
        $this->assertSame(
            [
                '/application/root/src/Spryker/StoresApi/resources/api/storefront/stores.resource.yml',
                '/application/root/src/Spryker/Store/resources/api/storefront/stores.resource.yml',
            ],
            $schemaFiles,
        );
    }

    public function testGivenAHeaderListingValidationSchemasWhenResolvingThenOnlyResourceSchemasAreReturned(): void
    {
        // Arrange
        $resolver = new SchemaSourceResolver();

        // Act
        $schemaFiles = $resolver->schemaFilesFor(GeneratedHeaderFixtureResource::class);

        // Assert
        $this->assertNotContains(
            '/application/root/src/Spryker/StoresApi/resources/api/storefront/stores.validation.yml',
            $schemaFiles,
        );
    }

    public function testGivenAnApplicationRootWhenResolvingThenPathsAreReportedRelativeToIt(): void
    {
        // Arrange
        $resolver = new SchemaSourceResolver();

        // Act
        $schemaFiles = $resolver->schemaFilesFor(GeneratedHeaderFixtureResource::class, static::APPLICATION_ROOT);

        // Assert
        $this->assertSame(
            [
                'src/Spryker/StoresApi/resources/api/storefront/stores.resource.yml',
                'src/Spryker/Store/resources/api/storefront/stores.resource.yml',
            ],
            $schemaFiles,
        );
    }

    public function testGivenSeveralClassesBackingOneResourceWhenResolvingAllThenSchemaFilesAreUniqueAndSorted(): void
    {
        // Arrange
        $resolver = new SchemaSourceResolver();

        // Act
        $schemaFiles = $resolver->schemaFilesForAll(
            [GeneratedHeaderFixtureResource::class, GeneratedHeaderFixtureResource::class],
            static::APPLICATION_ROOT,
        );

        // Assert
        $this->assertSame(
            [
                'src/Spryker/Store/resources/api/storefront/stores.resource.yml',
                'src/Spryker/StoresApi/resources/api/storefront/stores.resource.yml',
            ],
            $schemaFiles,
        );
    }
}
