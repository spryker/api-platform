<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributePath;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ResponseAttributePathTest
 * Add your own group annotations below this line
 */
class ResponseAttributePathTest extends Unit
{
    public function testGivenAnIndexedPathWhenNormalizingThenTheIndexBecomesAWildcard(): void
    {
        // Arrange
        $path = 'customers[3].firstName';

        // Act
        $normalized = ResponseAttributePath::normalize($path);

        // Assert
        $this->assertSame('customers[].firstName', $normalized);
    }

    public function testGivenALeadingMemberSelectorWhenNormalizingThenItIsDropped(): void
    {
        // Arrange
        $path = '[1].name';

        // Act
        $normalized = ResponseAttributePath::normalize($path);

        // Assert
        $this->assertSame('name', $normalized);
    }

    public function testGivenALeadingMemberSelectorWhenReadingTheMemberIndexThenItIsExtracted(): void
    {
        // Arrange
        $path = '[1].name';

        // Act
        $memberIndex = ResponseAttributePath::memberIndex($path);

        // Assert
        $this->assertSame(1, $memberIndex);
    }

    public function testGivenAnIndexedPathWhenSplittingThenSegmentsCarryIntegerIndexes(): void
    {
        // Arrange
        $path = 'customers[0].firstName';

        // Act
        $segments = ResponseAttributePath::segments($path);

        // Assert
        $this->assertSame(['customers', 0, 'firstName'], $segments);
    }

    public function testGivenAWildcardPathWhenSplittingThenTheWildcardSegmentIsNotDecomposed(): void
    {
        // Arrange
        $path = 'lines[].sku';

        // Act
        $segments = ResponseAttributePath::segments($path);

        // Assert
        $this->assertSame(['lines[]', 'sku'], $segments);
    }

    public function testGivenAPathWithSpacesWhenValidatingThenItIsRejected(): void
    {
        // Act
        $isValid = ResponseAttributePath::isValid('customers[ ].email');

        // Assert
        $this->assertFalse($isValid);
    }

    public function testGivenTheFormsTheGrammarAcceptsWhenValidatingThenEachIsAccepted(): void
    {
        // Arrange
        $wildcardTruthPath = 'lines[].sku';
        $dottedPath = 'pagination.numFound';
        $barePath = 'name';

        // Act
        $isWildcardTruthPathValid = ResponseAttributePath::isValid($wildcardTruthPath);
        $isDottedPathValid = ResponseAttributePath::isValid($dottedPath);
        $isBarePathValid = ResponseAttributePath::isValid($barePath);

        // Assert
        $this->assertTrue($isWildcardTruthPathValid);
        $this->assertTrue($isDottedPathValid);
        $this->assertTrue($isBarePathValid);
    }
}
