<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\CoverageItem;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SelfDispatchingThrownStatusFixtureProcessor;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ThrownStatusFixtureProcessor;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ThrownStatusFixtureProvider;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ThrownStatusCollectorTest
 * Add your own group annotations below this line
 */
class ThrownStatusCollectorTest extends Unit
{
    protected const string URI_TEMPLATE_COLLECTION = '/thrown-status-fixture';

    protected const string URI_TEMPLATE_ITEM = '/thrown-status-fixture/{uuid}';

    public function testGivenAProcessorBuiltOnTheTemplateWhenCollectingAPostThenOnlyWhatProcessPostThrowsIsOwed(): void
    {
        // Act
        $keys = $this->collectKeys(new Post(), 'POST', static::URI_TEMPLATE_COLLECTION);

        // Assert
        $this->assertSame(['POST /thrown-status-fixture 422'], $keys);
    }

    public function testGivenAPatchWhenCollectingThenWhatTheProviderItemReadThrowsIsOwedToo(): void
    {
        // Act
        $keys = $this->collectKeys(new Patch(), 'PATCH', static::URI_TEMPLATE_ITEM);

        // Assert
        $this->assertContains('PATCH /thrown-status-fixture/{uuid} 410', $keys);
    }

    public function testGivenAnOperationThatReadsWhenCollectingThenWhatTheProviderItemReadThrowsIsOwed(): void
    {
        // Act
        $keys = $this->collectKeys(new Delete(), 'DELETE', static::URI_TEMPLATE_ITEM);

        // Assert
        $this->assertContains('DELETE /thrown-status-fixture/{uuid} 410', $keys);
    }

    public function testGivenAnOperationThatDoesNotReadWhenCollectingThenTheProviderOwesNothing(): void
    {
        // Act
        $keys = $this->collectKeys(new Delete(read: false), 'DELETE', static::URI_TEMPLATE_ITEM);

        // Assert
        $this->assertNotContains('DELETE /thrown-status-fixture/{uuid} 410', $keys);
    }

    public function testGivenACollectionReadWhenCollectingThenWhatProvideCollectionThrowsIsOwed(): void
    {
        // Act
        $keys = $this->collectKeys(new GetCollection(), 'GET', static::URI_TEMPLATE_COLLECTION);

        // Assert
        $this->assertSame(['GET /thrown-status-fixture 501'], $keys);
    }

    public function testGivenAProcessorThatDispatchesByItselfWhenCollectingThenEveryOperationOwesWhatProcessThrows(): void
    {
        // Act
        $keys = $this->collectKeys(new Post(), 'POST', static::URI_TEMPLATE_COLLECTION, SelfDispatchingThrownStatusFixtureProcessor::class);

        // Assert
        $this->assertSame(['POST /thrown-status-fixture 409', 'POST /thrown-status-fixture 410'], $keys);
    }

    /**
     * @param class-string $processorClassName
     *
     * @return array<string>
     */
    protected function collectKeys(
        HttpOperation $operation,
        string $verb,
        string $uriTemplate,
        string $processorClassName = ThrownStatusFixtureProcessor::class,
    ): array {
        $apiResource = new ApiResource(provider: ThrownStatusFixtureProvider::class, processor: $processorClassName);

        $thrownStatuses = ContractCoverageFactory::createThrownStatusCollector()->collect($operation, $apiResource, new ApiOperation($verb, $uriTemplate));
        $keys = array_values(array_unique(array_map(static fn (CoverageItem $thrownStatus): string => $thrownStatus->key(), $thrownStatuses)));
        sort($keys);

        return $keys;
    }
}
