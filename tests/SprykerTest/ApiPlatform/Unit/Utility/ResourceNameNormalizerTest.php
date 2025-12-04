<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Utility;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Utility\ResourceNameNormalizer;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Utility
 * @group ResourceNameNormalizerTest
 * Add your own group annotations below this line
 */
class ResourceNameNormalizerTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenKebabCaseNameWhenNormalizingThenConvertsToPascalCase(): void
    {
        // Arrange
        $input = 'access-tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokens', $result);
    }

    public function testGivenSnakeCaseNameWhenNormalizingThenConvertsToPascalCase(): void
    {
        // Arrange
        $input = 'access_tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokens', $result);
    }

    public function testGivenDotSeparatedNameWhenNormalizingThenConvertsToPascalCase(): void
    {
        // Arrange
        $input = 'access.tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokens', $result);
    }

    public function testGivenMultipleSpacesWhenNormalizingThenNormalizesToSingleWords(): void
    {
        // Arrange
        $input = 'Access  Tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokens', $result);
    }

    public function testGivenLeadingTrailingSpacesWhenNormalizingThenTrimsAndConverts(): void
    {
        // Arrange
        $input = ' Access Tokens ';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokens', $result);
    }

    public function testGivenNameWithMixedSeparatorsWhenNormalizingThenNormalizesAll(): void
    {
        // Arrange
        $input = 'access_token-system.v2';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokenSystemV2', $result);
    }

    // 2. Case Variations

    public function testGivenCamelCaseNameWhenNormalizingThenConvertsToPascalCase(): void
    {
        // Arrange
        $input = 'accessTokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('Accesstokens', $result);
    }

    public function testGivenUppercaseNameWhenNormalizingThenConvertsToPascalCase(): void
    {
        // Arrange
        $input = 'ACCESS TOKENS';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokens', $result);
    }

    public function testGivenLowercaseNameWhenNormalizingThenCapitalizesFirstLetter(): void
    {
        // Arrange
        $input = 'customer';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('Customer', $result);
    }

    public function testGivenAlreadyPascalCaseWhenNormalizingThenRemainsUnchanged(): void
    {
        // Arrange
        $input = 'AccessTokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('Accesstokens', $result);
    }

    public function testGivenSingleWordLowercaseWhenNormalizingThenCapitalizes(): void
    {
        // Arrange
        $input = 'customer';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('Customer', $result);
    }

    // 3. Numbers in Names

    public function testGivenNameWithVersionNumberWhenNormalizingThenPreservesNumbers(): void
    {
        // Arrange
        $input = 'api-v2-tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('ApiV2Tokens', $result);
    }

    public function testGivenNameWithOAuthPatternWhenNormalizingThenPreservesNumbers(): void
    {
        // Arrange
        $input = 'oauth2-tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('Oauth2Tokens', $result);
    }

    public function testGivenNameWithNumbersWhenNormalizingThenPreservesNumbers(): void
    {
        // Arrange
        $input = 'api2tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('Api2tokens', $result);
    }

    // 4. Special Characters

    public function testGivenNameWithParenthesesWhenNormalizingThenRemovesSpecialChars(): void
    {
        // Arrange
        $input = 'Access (Tokens)';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokens', $result);
    }

    public function testGivenNameWithSpecialCharsWhenNormalizingThenRemovesInvalidCharacters(): void
    {
        // Arrange
        $input = 'access@tokens#v2';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokensV2', $result);
    }

    public function testGivenNameWithBracketsWhenNormalizingThenRemovesSpecialChars(): void
    {
        // Arrange
        $input = 'Access [Tokens]';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokens', $result);
    }

    // 5. Edge Cases and Validation

    public function testGivenEmptyStringWhenNormalizingThenThrowsException(): void
    {
        // Arrange
        $input = '';

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name cannot be empty');

        // Act
        ResourceNameNormalizer::normalize($input);
    }

    public function testGivenOnlyWhitespaceWhenNormalizingThenThrowsException(): void
    {
        // Arrange
        $input = '   ';

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name cannot be empty');

        // Act
        ResourceNameNormalizer::normalize($input);
    }

    public function testGivenOnlySpecialCharsWhenNormalizingThenThrowsException(): void
    {
        // Arrange
        $input = '@#$';

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name must contain at least one alphanumeric character');

        // Act
        ResourceNameNormalizer::normalize($input);
    }

    public function testGivenNameStartingWithNumberWhenNormalizingThenThrowsException(): void
    {
        // Arrange
        $input = '2fa-tokens';

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name cannot start with a number');

        // Act
        ResourceNameNormalizer::normalize($input);
    }

    public function testGivenNameWithUnicodeCharsWhenNormalizingThenRemovesInvalidCharacters(): void
    {
        // Arrange
        $input = 'Acçess Tökens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AcEssTKens', $result);
    }

    // 6. Complex Real-World Scenarios

    public function testGivenRestApiPatternWhenNormalizingThenConvertsToPascalCase(): void
    {
        // Arrange
        $input = 'REST API Tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('RestApiTokens', $result);
    }

    public function testGivenMultiWordWithNumbersWhenNormalizingThenNormalizesCorrectly(): void
    {
        // Arrange
        $input = 'user-profile-data-v3';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('UserProfileDataV3', $result);
    }

    public function testGivenAcronymsWhenNormalizingThenCapitalizesEachPart(): void
    {
        // Arrange
        $input = 'JWT-API-tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('JwtApiTokens', $result);
    }

    // Additional Edge Cases

    public function testGivenSlashSeparatorWhenNormalizingThenHandlesCorrectly(): void
    {
        // Arrange
        $input = 'api/v1/tokens';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('ApiV1Tokens', $result);
    }

    public function testGivenMixedCaseWithSeparatorsWhenNormalizingThenNormalizesCorrectly(): void
    {
        // Arrange
        $input = 'UserProfile_Data-v2.Final';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('UserprofileDataV2Final', $result);
    }

    public function testGivenNumberInMiddleWhenNormalizingThenPreservesNumber(): void
    {
        // Arrange
        $input = 'oauth-2-authentication';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('Oauth2Authentication', $result);
    }

    public function testGivenConsecutiveSpecialCharsWhenNormalizingThenHandlesGracefully(): void
    {
        // Arrange
        $input = 'access---tokens___system';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('AccessTokensSystem', $result);
    }

    public function testGivenSingleLetterWhenNormalizingThenCapitalizes(): void
    {
        // Arrange
        $input = 'a';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('A', $result);
    }

    public function testGivenSingleLetterWithSpacesWhenNormalizingThenCapitalizes(): void
    {
        // Arrange
        $input = '  a  ';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('A', $result);
    }

    public function testGivenAllUppercaseWithSeparatorsWhenNormalizingThenNormalizesCorrectly(): void
    {
        // Arrange
        $input = 'API_V2_TOKENS';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('ApiV2Tokens', $result);
    }

    public function testGivenNumberOnlyWhenNormalizingThenThrowsException(): void
    {
        // Arrange
        $input = '123';

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage('Resource name cannot start with a number');

        // Act
        ResourceNameNormalizer::normalize($input);
    }

    public function testGivenMixedSpecialCharsAndSpacesWhenNormalizingThenCleansCorrectly(): void
    {
        // Arrange
        $input = 'user @profile #data';

        // Act
        $result = ResourceNameNormalizer::normalize($input);

        // Assert
        $this->assertSame('UserProfileData', $result);
    }
}
