<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Utility;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Utility
 * @group ApiTypeNormalizerTest
 * Add your own group annotations below this line
 */
class ApiTypeNormalizerTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenUppercaseInputWhenNormalizingForSchemaLookupThenReturnsLowercase(): void
    {
        // Act
        $result = ApiTypeNormalizer::normalizeForSchemaLookup('backend');

        // Assert
        $this->assertSame('backend', $result);
    }

    public function testGivenMixedCaseInputWhenNormalizingForSchemaLookupThenReturnsLowercase(): void
    {
        // Act
        $result = ApiTypeNormalizer::normalizeForSchemaLookup('backend');

        // Assert
        $this->assertSame('backend', $result);
    }

    public function testGivenLowercaseInputWhenNormalizingForSchemaLookupThenReturnsLowercase(): void
    {
        // Act
        $result = ApiTypeNormalizer::normalizeForSchemaLookup('backend');

        // Assert
        $this->assertSame('backend', $result);
    }

    public function testGivenLowercaseInputWhenNormalizingForGenerationThenReturnsUcfirst(): void
    {
        // Act
        $result = ApiTypeNormalizer::normalizeForGeneration('backend');

        // Assert
        $this->assertSame('Backend', $result);
    }

    public function testGivenUppercaseInputWhenNormalizingForGenerationThenReturnsUcfirst(): void
    {
        // Act
        $result = ApiTypeNormalizer::normalizeForGeneration('BACKEND');

        // Assert
        $this->assertSame('Backend', $result);
    }

    public function testGivenMixedCaseInputWhenNormalizingForGenerationThenReturnsUcfirst(): void
    {
        // Act
        $result = ApiTypeNormalizer::normalizeForGeneration('bAcKeNd');

        // Assert
        $this->assertSame('Backend', $result);
    }

    public function testGivenMatchingInputWhenFindingConfiguredTypeThenReturnsConfiguredType(): void
    {
        // Arrange
        $configuredTypes = ['backend', 'Storefront'];

        // Act
        $result = ApiTypeNormalizer::findMatchingConfiguredType('backend', $configuredTypes);

        // Assert
        $this->assertSame('backend', $result);
    }

    public function testGivenUppercaseMatchingInputWhenFindingConfiguredTypeThenReturnsConfiguredType(): void
    {
        // Arrange
        $configuredTypes = ['backend', 'Storefront'];

        // Act
        $result = ApiTypeNormalizer::findMatchingConfiguredType('STOREFRONT', $configuredTypes);

        // Assert
        $this->assertSame('Storefront', $result);
    }

    public function testGivenMixedCaseMatchingInputWhenFindingConfiguredTypeThenReturnsConfiguredType(): void
    {
        // Arrange
        $configuredTypes = ['backend', 'Storefront'];

        // Act
        $result = ApiTypeNormalizer::findMatchingConfiguredType('StOrEfRoNt', $configuredTypes);

        // Assert
        $this->assertSame('Storefront', $result);
    }

    public function testGivenNonMatchingInputWhenFindingConfiguredTypeThenReturnsNull(): void
    {
        // Arrange
        $configuredTypes = ['backend', 'Storefront'];

        // Act
        $result = ApiTypeNormalizer::findMatchingConfiguredType('NonExistent', $configuredTypes);

        // Assert
        $this->assertNull($result);
    }

    public function testGivenEmptyConfiguredTypesWhenFindingConfiguredTypeThenReturnsNull(): void
    {
        // Arrange
        $configuredTypes = [];

        // Act
        $result = ApiTypeNormalizer::findMatchingConfiguredType('backend', $configuredTypes);

        // Assert
        $this->assertNull($result);
    }
}
