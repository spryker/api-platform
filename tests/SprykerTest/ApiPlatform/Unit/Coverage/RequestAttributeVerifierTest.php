<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\RecordedExchange;
use Spryker\ApiPlatform\Contract\Coverage\RequestAttributePathExtractor;
use Spryker\ApiPlatform\Contract\Coverage\RequestAttributeVerifier;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group RequestAttributeVerifierTest
 * Add your own group annotations below this line
 */
class RequestAttributeVerifierTest extends Unit
{
    protected const string DISPATCH_KEY = 'PATCH /guest-carts/{cartUuid}';

    public function testGivenOnlyAFailedRequestSentTheAttributeWhenVerifyingThenItIsMissing(): void
    {
        // Arrange
        $exchanges = [
            new RecordedExchange(new ApiOperation('PATCH', '/guest-carts/{cartUuid}'), 422, ['currency' => 'XXX']),
            new RecordedExchange(new ApiOperation('PATCH', '/guest-carts/{cartUuid}'), 200, ['name' => 'Mine']),
        ];

        // Act
        $missing = (new RequestAttributeVerifier(new RequestAttributePathExtractor()))->verify([static::DISPATCH_KEY => ['currency', 'name']], $exchanges);

        // Assert
        $this->assertSame([static::DISPATCH_KEY => ['currency']], $missing);
    }

    public function testGivenTwoSuccessfulRequestsSplittingTheAttributesWhenVerifyingThenNothingIsMissing(): void
    {
        // Arrange
        $exchanges = [
            new RecordedExchange(new ApiOperation('PATCH', '/guest-carts/{cartUuid}'), 200, ['currency' => 'CHF']),
            new RecordedExchange(new ApiOperation('PATCH', '/guest-carts/{cartUuid}'), 200, ['priceMode' => 'NET_MODE']),
        ];

        // Act
        $missing = (new RequestAttributeVerifier(new RequestAttributePathExtractor()))->verify([static::DISPATCH_KEY => ['currency', 'priceMode']], $exchanges);

        // Assert
        $this->assertSame([], $missing);
    }
}
