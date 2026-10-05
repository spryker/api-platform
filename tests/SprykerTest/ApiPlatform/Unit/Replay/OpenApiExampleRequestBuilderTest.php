<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Replay;

use Codeception\Test\Unit;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use SprykerTest\ApiPlatform\Unit\Replay\Fixture\ReplayFixtureResource;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Replay
 * @group OpenApiExampleRequestBuilderTest
 * Add your own group annotations below this line
 */
class OpenApiExampleRequestBuilderTest extends Unit
{
    public function testGivenWritablePropertiesWithExamplesWhenBuildingAPostThenTheBodyCarriesExactlyThoseAttributes(): void
    {
        // Act
        $request = $this->buildRequests()['POST /replay-items'];

        // Assert
        $this->assertSame(
            ['data' => ['type' => 'replay-items', 'attributes' => ['name' => 'Wishlist', 'store' => 'DE', 'salesUnit' => ['id' => 7, 'amount' => '1.5']]]],
            $request->body,
        );
    }

    public function testGivenAReadOnlyPropertyWithAnExampleWhenBuildingThenItIsLeftOut(): void
    {
        // Act
        $attributes = $this->buildRequests()['POST /replay-items']->body['data']['attributes'] ?? [];

        // Assert
        $this->assertArrayNotHasKey('createdAt', $attributes);
        $this->assertArrayNotHasKey('uuid', $attributes);
    }

    public function testGivenWritableOnPostWhenBuildingAPatchThenThePropertyIsLeftOut(): void
    {
        // Act
        $attributes = $this->buildRequests()['PATCH /replay-items/{uuid}']->body['data']['attributes'] ?? [];

        // Assert
        $this->assertArrayNotHasKey('store', $attributes);
        $this->assertArrayHasKey('name', $attributes);
    }

    public function testGivenANestedValueObjectWhenBuildingThenItsChildExamplesAreNested(): void
    {
        // Act
        $attributes = $this->buildRequests()['PATCH /replay-items/{uuid}']->body['data']['attributes'] ?? [];

        // Assert
        $this->assertSame(['id' => 7, 'amount' => '1.5'], $attributes['salesUnit'] ?? null);
    }

    public function testGivenQueryParametersWithExamplesWhenBuildingAGetThenTheQueryCarriesThem(): void
    {
        // Act
        $request = $this->buildRequests()['GET /customers/{customerReference}/replay-items'];

        // Assert
        $this->assertSame(['page[limit]' => '5', 'filter[store]' => '1'], $request->query);
        $this->assertNull($request->body);
    }

    public function testGivenAUriTemplateWhenBuildingThenItsVariableNamesAreListed(): void
    {
        // Act
        $request = $this->buildRequests()['GET /customers/{customerReference}/replay-items'];

        // Assert
        $this->assertSame(['customerReference'], $request->uriVariableNames);
    }

    public function testGivenANonServableItemGetWhenBuildingThenNoRequestIsReplayed(): void
    {
        // Act
        $requests = $this->buildRequests();

        // Assert
        $this->assertArrayNotHasKey('GET /replay-items/{uuid}', $requests);
    }

    /**
     * @return array<string, \Spryker\ApiPlatform\Contract\Replay\ReplayableRequest>
     */
    protected function buildRequests(): array
    {
        $requests = [];
        foreach (ContractCoverageFactory::createOpenApiExampleRequestBuilder()->build(ReplayFixtureResource::class) as $request) {
            $requests[$request->operation->dispatchKey()] = $request;
        }

        return $requests;
    }
}
