<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ResourceNameMatcher;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ResourceNameMatcherTest
 * Add your own group annotations below this line
 */
class ResourceNameMatcherTest extends Unit
{
    /**
     * @var array<string>
     */
    protected const array RESOURCE_NAMES = ['wishlists', 'wishlist-items'];

    public function testGivenNoFiltersWhenMatchingThenEveryResourceIsSelected(): void
    {
        // Arrange
        $matcher = new ResourceNameMatcher();

        // Act
        $selected = $matcher->match(static::RESOURCE_NAMES, []);

        // Assert
        $this->assertSame(static::RESOURCE_NAMES, $selected);
    }

    /**
     * @dataProvider spellingProvider
     */
    public function testGivenAnySpellingOfAModuleWhenMatchingThenTheSameResourceIsSelected(
        string $filter,
        string $expectedResource
    ): void {
        // Arrange
        $matcher = new ResourceNameMatcher();

        // Act
        $selected = $matcher->match(static::RESOURCE_NAMES, [$filter]);

        // Assert
        $this->assertSame([$expectedResource], $selected);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function spellingProvider(): array
    {
        return [
            'pascal case, singular' => ['Wishlist', 'wishlists'],
            'lower case, singular' => ['wishlist', 'wishlists'],
            'lower case, plural' => ['wishlists', 'wishlists'],
            'pascal case module, plural' => ['WishlistItems', 'wishlist-items'],
            'kebab case, plural' => ['wishlist-items', 'wishlist-items'],
            'camel case, singular' => ['wishlistItem', 'wishlist-items'],
        ];
    }

    public function testGivenAModuleWhenMatchingThenASiblingResourceIsNotSelected(): void
    {
        // Arrange
        $matcher = new ResourceNameMatcher();

        // Act
        $selected = $matcher->match(static::RESOURCE_NAMES, ['Wishlist']);

        // Assert
        $this->assertNotContains('wishlist-items', $selected);
    }

    public function testGivenSeveralFiltersWhenMatchingThenEachNamedResourceIsSelected(): void
    {
        // Arrange
        $matcher = new ResourceNameMatcher();

        // Act
        $selected = $matcher->match(static::RESOURCE_NAMES, ['wishlist-items', 'Wishlist']);

        // Assert
        $this->assertSame(['wishlists', 'wishlist-items'], $selected);
    }

    public function testGivenAnUnknownFilterWhenCollectingUnmatchedThenItIsReported(): void
    {
        // Arrange
        $matcher = new ResourceNameMatcher();

        // Act
        $unmatched = $matcher->unmatchedFilters(static::RESOURCE_NAMES, ['Wishlist', 'bogus']);

        // Assert
        $this->assertSame(['bogus'], $unmatched);
    }

    public function testGivenOnlyKnownFiltersWhenCollectingUnmatchedThenNoneAreReported(): void
    {
        // Arrange
        $matcher = new ResourceNameMatcher();

        // Act
        $unmatched = $matcher->unmatchedFilters(static::RESOURCE_NAMES, ['Wishlist', 'wishlist-items']);

        // Assert
        $this->assertSame([], $unmatched);
    }
}
