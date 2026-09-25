<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute;
use Spryker\ApiPlatform\Contract\Coverage\ScopeResolver;
use Spryker\ApiPlatform\Contract\Coverage\TruthSet;
use Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ScopeResolverTest
 * Add your own group annotations below this line
 */
class ScopeResolverTest extends Unit
{
    public function testGivenEnforcedAndExcludedResourcesWhenResolvingThenEnforcedHoldsTheSelectionAndExistenceHoldsAll(): void
    {
        // Arrange
        $truthByResource = [
            'wishlists' => new TruthSet([new ApiOperation('GET', '/wishlists')], [], [new ValidationConstraint('wishlists', 'name', 'NotBlank', 'POST', '/wishlists')]),
            'orders' => new TruthSet([new ApiOperation('GET', '/orders')], [], []),
        ];

        // Act
        $resolution = (new ScopeResolver())->resolve($truthByResource, ['wishlists']);

        // Assert
        $this->assertSame(['GET /wishlists'], $this->operationKeys($resolution->enforcedTruth->servableOperations));
        $this->assertSame(['GET /wishlists', 'GET /orders'], $this->operationKeys($resolution->existenceTruth->servableOperations));
    }

    public function testGivenEnforcedResourcesCarryingResponseAttributesWhenResolvingThenTheEnforcedTruthCarriesEveryOne(): void
    {
        // Arrange — the merged truth is rebuilt field by field, so a field the merge drops leaves
        // an empty enforced set that passes the gate without anyone noticing.
        $truthByResource = [
            'wishlists' => new TruthSet([], [], [], responseAttributes: [new ResponseAttribute('GET /wishlists', 'name')]),
            'orders' => new TruthSet([], [], [], responseAttributes: [new ResponseAttribute('GET /orders', 'reference')]),
        ];

        // Act
        $resolution = (new ScopeResolver())->resolve($truthByResource, ['wishlists', 'orders']);

        // Assert
        $this->assertSame(
            ['GET /wishlists  name', 'GET /orders  reference'],
            array_map(
                static fn (ResponseAttribute $responseAttribute): string => $responseAttribute->key(),
                $resolution->enforcedTruth->responseAttributes,
            ),
        );
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $operations
     *
     * @return array<string>
     */
    protected function operationKeys(array $operations): array
    {
        return array_map(static fn (ApiOperation $operation): string => $operation->key(), $operations);
    }
}
