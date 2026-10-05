<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Replay;

use Codeception\Test\Unit;
use LogicException;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Replay\OpenApiExampleReplayContext;
use Spryker\ApiPlatform\Contract\Replay\ReplayableRequest;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Replay
 * @group ReplayableRequestTest
 * Add your own group annotations below this line
 */
class ReplayableRequestTest extends Unit
{
    public function testGivenAContextWhenResolvingThenPathVariablesAreSubstitutedAndOverridesWritten(): void
    {
        // Arrange
        $request = new ReplayableRequest(
            new ApiOperation('POST', '/carts/{cartUuid}/items'),
            ['data' => ['type' => 'items', 'attributes' => ['sku' => 'placeholder', 'quantity' => 1]]],
            ['include' => 'items'],
            ['cartUuid'],
        );
        $context = new OpenApiExampleReplayContext(['cartUuid' => 'c-1'], [], ['sku' => '035_17360369', 'salesUnit.id' => 3]);

        // Act
        $resolved = $request->resolve($context);

        // Assert
        $this->assertSame('POST', $resolved['method']);
        $this->assertSame('/carts/c-1/items?include=items', $resolved['uri']);
        $this->assertSame(
            ['data' => ['type' => 'items', 'attributes' => ['sku' => '035_17360369', 'quantity' => 1, 'salesUnit' => ['id' => 3]]]],
            json_decode((string)$resolved['content'], true),
        );
    }

    public function testGivenAMissingUriVariableWhenResolvingThenTheCaseFailsNamingTheVariable(): void
    {
        // Arrange
        $request = new ReplayableRequest(new ApiOperation('GET', '/carts/{cartUuid}'), null, [], ['cartUuid']);

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The replay of GET /carts/{cartUuid} needs a value for the path variable {cartUuid}');

        // Act
        $request->resolve(new OpenApiExampleReplayContext());
    }
}
