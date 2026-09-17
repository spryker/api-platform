<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\OpenApi\Decorator\OpenApiDecorator;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group Decorator
 * @group OpenApiDecoratorTest
 * Add your own group annotations below this line
 */
class OpenApiDecoratorTest extends Unit
{
    protected const string PATH_COLLECTION = '/glossary-keys';

    protected const string PATH_ITEM = '/glossary-keys/{key}';

    protected const string PARAMETER_PAGE = 'page';

    protected const string PARAMETER_ITEMS_PER_PAGE = 'itemsPerPage';

    protected const string PARAMETER_PAGE_LIMIT = 'page[limit]';

    protected const string PARAMETER_PAGE_OFFSET = 'page[offset]';

    protected const string PARAMETER_SORT = 'sort';

    protected const string PARAMETER_FIELDS = 'fields[]';

    protected const string PARAMETER_ACCEPT_LANGUAGE = 'Accept-Language';

    protected const string PARAMETER_IN_QUERY = 'query';

    protected ApiUnitTester $tester;

    public function testGivenPaginatedCollectionWhenDecoratingThenPageParametersAreReplacedByJsonApiWindow(): void
    {
        // Arrange
        $decorator = $this->createDecorator([
            static::PATH_COLLECTION => new Operation(parameters: [
                new Parameter(static::PARAMETER_PAGE, static::PARAMETER_IN_QUERY),
                new Parameter(static::PARAMETER_ITEMS_PER_PAGE, static::PARAMETER_IN_QUERY),
                new Parameter(static::PARAMETER_SORT, static::PARAMETER_IN_QUERY),
            ]),
        ]);

        // Act
        $parameterNames = $this->getGetParameterNames($decorator(), static::PATH_COLLECTION);

        // Assert
        $this->assertNotContains(static::PARAMETER_PAGE, $parameterNames);
        $this->assertNotContains(static::PARAMETER_ITEMS_PER_PAGE, $parameterNames);
        $this->assertContains(static::PARAMETER_PAGE_LIMIT, $parameterNames);
        $this->assertContains(static::PARAMETER_PAGE_OFFSET, $parameterNames);
        $this->assertContains(static::PARAMETER_SORT, $parameterNames, 'Schema-declared parameters are kept.');
    }

    public function testGivenSchemaDeclaredPageWindowWhenDecoratingThenItIsNotDuplicated(): void
    {
        // Arrange
        $decorator = $this->createDecorator([
            static::PATH_COLLECTION => new Operation(parameters: [
                new Parameter(static::PARAMETER_PAGE, static::PARAMETER_IN_QUERY),
                new Parameter(static::PARAMETER_PAGE_LIMIT, static::PARAMETER_IN_QUERY, 'Declared in the schema.'),
            ]),
        ]);

        // Act
        $parameterNames = $this->getGetParameterNames($decorator(), static::PATH_COLLECTION);

        // Assert
        $this->assertSame(1, count(array_keys($parameterNames, static::PARAMETER_PAGE_LIMIT, true)));
        $this->assertContains(static::PARAMETER_PAGE_OFFSET, $parameterNames);
    }

    public function testGivenNonPaginatedOperationWhenDecoratingThenNoPaginationParametersAreAdded(): void
    {
        // Arrange
        $decorator = $this->createDecorator([
            static::PATH_ITEM => new Operation(parameters: []),
        ]);

        // Act
        $parameterNames = $this->getGetParameterNames($decorator(), static::PATH_ITEM);

        // Assert
        $this->assertNotContains(static::PARAMETER_PAGE_LIMIT, $parameterNames);
        $this->assertNotContains(static::PARAMETER_PAGE_OFFSET, $parameterNames);
        $this->assertContains(static::PARAMETER_FIELDS, $parameterNames);
        $this->assertContains(static::PARAMETER_ACCEPT_LANGUAGE, $parameterNames);
    }

    /**
     * @param array<string, \ApiPlatform\OpenApi\Model\Operation> $getOperationsByPath
     */
    protected function createDecorator(array $getOperationsByPath): OpenApiDecorator
    {
        $paths = new Paths();

        foreach ($getOperationsByPath as $path => $operation) {
            $paths->addPath($path, new PathItem(get: $operation));
        }

        $openApiFactoryMock = $this->createMock(OpenApiFactoryInterface::class);
        $openApiFactoryMock->method('__invoke')->willReturn(new OpenApi(new Info('Spryker Backend API', '0.0.0'), [], $paths));

        return new OpenApiDecorator($openApiFactoryMock, []);
    }

    /**
     * @return array<string>
     */
    protected function getGetParameterNames(OpenApi $openApi, string $path): array
    {
        $operation = $openApi->getPaths()->getPath($path)?->getGet();
        $this->assertNotNull($operation);

        return array_map(
            static fn (Parameter $parameter): string => $parameter->getName(),
            $operation->getParameters(),
        );
    }
}
