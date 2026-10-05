<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\IncludeEvidenceVerifier;
use Spryker\ApiPlatform\Contract\Coverage\RecordedExchange;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group IncludeEvidenceVerifierTest
 * Add your own group annotations below this line
 */
class IncludeEvidenceVerifierTest extends Unit
{
    protected const string DISPATCH_KEY = 'GET /carts/{cartUuid}';

    public function testGivenTheIncludeWasRequestedAndAssertedWhenVerifyingThenItIsProven(): void
    {
        // Arrange
        $exchange = new RecordedExchange(new ApiOperation('GET', '/carts/{cartUuid}'), 200, includeRelationshipNames: ['vouchers']);

        // Act
        $unproven = (new IncludeEvidenceVerifier())->verify([static::DISPATCH_KEY => ['vouchers']], [$exchange], ['vouchers']);

        // Assert
        $this->assertSame([], $unproven);
    }

    public function testGivenTheIncludeWasAssertedButNeverRequestedWhenVerifyingThenItIsUnproven(): void
    {
        // Arrange
        $exchange = new RecordedExchange(new ApiOperation('GET', '/carts/{cartUuid}'), 200);

        // Act
        $unproven = (new IncludeEvidenceVerifier())->verify([static::DISPATCH_KEY => ['vouchers']], [$exchange], ['vouchers']);

        // Assert
        $this->assertSame(['GET /carts/{cartUuid} include vouchers: no successful GET /carts/{cartUuid} request asked for ?include=vouchers'], $unproven);
    }

    public function testGivenTheIncludeWasRequestedOnlyOnAnotherOperationWhenVerifyingThenItIsUnproven(): void
    {
        // Arrange
        $exchange = new RecordedExchange(new ApiOperation('GET', '/carts'), 200, includeRelationshipNames: ['vouchers']);

        // Act
        $unproven = (new IncludeEvidenceVerifier())->verify([static::DISPATCH_KEY => ['vouchers']], [$exchange], []);

        // Assert
        $this->assertCount(1, $unproven);
        $this->assertStringContainsString('and assertIncludedRelationship() never asserted vouchers', $unproven[0]);
    }
}
