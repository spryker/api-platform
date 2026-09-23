<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\PathItemOperationAccessor;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group ErrorResponse
 * @group PathItemOperationAccessorTest
 * Add your own group annotations below this line
 */
class PathItemOperationAccessorTest extends Unit
{
    protected const string METHOD_GET = 'get';

    protected const string METHOD_DELETE = 'delete';

    protected const string METHOD_QUERY = 'query';

    protected const string OPERATION_ID_QUERY = 'queryCategories';

    protected const string OPERATION_ID_GET = 'getCategory';

    protected const string OPERATION_ID_DELETE = 'deleteCategory';

    protected const string OPERATION_ID_REPLACED = 'getCategoryDocumented';

    protected ApiUnitTester $tester;

    public function testGivenPathItemWithTwoMethodsWhenGettingOperationsThenOnlyThoseAreReturnedKeyedByMethod(): void
    {
        // Arrange
        $pathItem = new PathItem(
            get: new Operation(operationId: static::OPERATION_ID_GET),
            delete: new Operation(operationId: static::OPERATION_ID_DELETE),
        );

        // Act
        $operations = (new PathItemOperationAccessor())->getOperations($pathItem);

        // Assert
        $this->assertSame([static::METHOD_GET, static::METHOD_DELETE], array_keys($operations));
        $this->assertSame(static::OPERATION_ID_GET, $operations[static::METHOD_GET]->getOperationId());
        $this->assertSame(static::OPERATION_ID_DELETE, $operations[static::METHOD_DELETE]->getOperationId());
    }

    public function testGivenPathItemWithAnOperationOnEveryMethodTheModelDefinesWhenGettingOperationsThenAllAreReturned(): void
    {
        // Arrange
        $pathItem = new PathItem(
            get: new Operation(operationId: 'get'),
            put: new Operation(operationId: 'put'),
            post: new Operation(operationId: 'post'),
            delete: new Operation(operationId: 'delete'),
            options: new Operation(operationId: 'options'),
            head: new Operation(operationId: 'head'),
            patch: new Operation(operationId: 'patch'),
            trace: new Operation(operationId: 'trace'),
            query: new Operation(operationId: 'query'),
        );

        // Act
        $operations = (new PathItemOperationAccessor())->getOperations($pathItem);

        // Assert
        $this->assertSame(array_map(strtolower(...), PathItem::$methods), array_keys($operations));

        foreach ($operations as $method => $operation) {
            $this->assertSame($method, $operation->getOperationId());
        }
    }

    public function testGivenMethodOutsideTheCommonFiveWhenReplacingItsOperationThenTheModelWitherIsUsed(): void
    {
        // Arrange
        $accessor = new PathItemOperationAccessor();

        // Act
        $pathItem = $accessor->withOperation(new PathItem(), static::METHOD_QUERY, new Operation(operationId: static::OPERATION_ID_QUERY));

        // Assert
        $this->assertSame(static::OPERATION_ID_QUERY, $pathItem->getQuery()?->getOperationId());
        $this->assertSame([static::METHOD_QUERY], array_keys($accessor->getOperations($pathItem)));
    }

    public function testGivenMethodWhenReplacingItsOperationThenTheOtherMethodsAreUntouched(): void
    {
        // Arrange
        $accessor = new PathItemOperationAccessor();
        $pathItem = new PathItem(
            get: new Operation(operationId: static::OPERATION_ID_GET),
            delete: new Operation(operationId: static::OPERATION_ID_DELETE),
        );

        // Act
        $pathItem = $accessor->withOperation($pathItem, static::METHOD_GET, new Operation(operationId: static::OPERATION_ID_REPLACED));

        // Assert
        $this->assertSame(static::OPERATION_ID_REPLACED, $accessor->getOperation($pathItem, static::METHOD_GET)?->getOperationId());
        $this->assertSame(static::OPERATION_ID_DELETE, $accessor->getOperation($pathItem, static::METHOD_DELETE)?->getOperationId());
        $this->assertNull($accessor->getOperation($pathItem, 'post'));
    }
}
