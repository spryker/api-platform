<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Generator\ClassGenerator;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group ClassGeneratorTest
 * Add your own group annotations below this line
 */
class ClassGeneratorTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenSchemaWhenGeneratingThenReturnsPhpClass(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class CustomerStorefrontResource', $result);
    }

    public function testGivenApiTypeWhenGeneratingThenIncludesApiTypeInNamespace(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('namespace Generated\Api\Storefront;', $result);
    }

    public function testGivenPropertiesWhenGeneratingThenTransformsTypes(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'properties' => ['id' => ['type' => 'integer']]];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('public ?int $id = null;', $result);
    }

    public function testGivenBackendApiTypeWhenGeneratingThenGeneratesCorrectClassName(): void
    {
        // Arrange
        $schema = ['name' => 'Order', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Backend');

        // Assert
        $this->assertStringContainsString('class OrderBackendResource', $result);
    }

    public function testGivenResourceNameWithSpacesWhenGeneratingThenRemovesWhitespaceFromClassName(): void
    {
        // Arrange
        $schema = ['name' => 'Access Tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensStorefrontResource', $result);
        $this->assertStringNotContainsString('class Access TokensStorefrontResource', $result);
    }

    // Resource Name Normalization Integration Tests

    public function testGivenKebabCaseNameWhenGeneratingThenConvertsToPascalCase(): void
    {
        // Arrange
        $schema = ['name' => 'access-tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensStorefrontResource', $result);
    }

    public function testGivenSnakeCaseNameWhenGeneratingThenConvertsToPascalCase(): void
    {
        // Arrange
        $schema = ['name' => 'access_tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensStorefrontResource', $result);
    }

    public function testGivenDotSeparatedNameWhenGeneratingThenConvertsToPascalCase(): void
    {
        // Arrange
        $schema = ['name' => 'access.tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensStorefrontResource', $result);
    }

    public function testGivenMixedSeparatorsWhenGeneratingThenNormalizesAll(): void
    {
        // Arrange
        $schema = ['name' => 'access_token-system.v2', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokenSystemV2StorefrontResource', $result);
    }

    public function testGivenNameWithVersionNumberWhenGeneratingThenPreservesNumbers(): void
    {
        // Arrange
        $schema = ['name' => 'api-v2-tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class ApiV2TokensStorefrontResource', $result);
    }

    public function testGivenNameWithSpecialCharsWhenGeneratingThenRemovesInvalidCharacters(): void
    {
        // Arrange
        $schema = ['name' => 'access@tokens#v2', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'Storefront');

        // Assert
        $this->assertStringContainsString('class AccessTokensV2StorefrontResource', $result);
    }

    public function testGivenEmptyStringWhenGeneratingThenThrowsException(): void
    {
        // Arrange
        $schema = ['name' => '', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name cannot be empty');

        // Act
        $generator->generate($schema, 'Storefront');
    }

    public function testGivenNameStartingWithNumberWhenGeneratingThenThrowsException(): void
    {
        // Arrange
        $schema = ['name' => '2fa-tokens', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name cannot start with a number');

        // Act
        $generator->generate($schema, 'Storefront');
    }

    public function testGivenComplexMultiWordNameWhenGeneratingThenNormalizesCorrectly(): void
    {
        // Arrange
        $schema = ['name' => 'user-profile-data-v3', 'properties' => []];
        $generator = $this->createClassGenerator();

        // Act
        $result = $generator->generate($schema, 'backend');

        // Assert
        $this->assertStringContainsString('class UserProfileDataV3BackendResource', $result);
    }

    protected function createClassGenerator(): ClassGenerator
    {
        $validationGroupMapper = $this->makeEmpty(ValidationGroupMapperInterface::class);

        return new ClassGenerator(new PhpTemplateRenderer(), $validationGroupMapper);
    }
}
