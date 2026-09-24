<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Error;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Error\GlueApiErrorCollection;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Error
 * @group GlueApiErrorCollectionTest
 * Add your own group annotations below this line
 */
class GlueApiErrorCollectionTest extends Unit
{
    protected const string CODE_NOT_FOUND = '1227';

    protected const string CODE_MISMATCH = '1229';

    public function testIsEmptyUntilSomethingIsGathered(): void
    {
        // Arrange
        $glueApiErrorCollection = new GlueApiErrorCollection();

        // Act
        $isEmptyBeforeAdding = $glueApiErrorCollection->isEmpty();
        $glueApiErrorCollection->addError(static::CODE_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Not found.');

        // Assert
        $this->assertTrue($isEmptyBeforeAdding);
        $this->assertFalse($glueApiErrorCollection->isEmpty());
        $this->assertSame(1, $glueApiErrorCollection->count());
    }

    public function testKeepsTheOrderErrorsWereGatheredIn(): void
    {
        // Arrange
        $glueApiErrorCollection = (new GlueApiErrorCollection())
            ->addError(static::CODE_MISMATCH, Response::HTTP_UNPROCESSABLE_ENTITY, 'Belongs to another company.')
            ->addError(static::CODE_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Not found.');

        // Act
        $errors = $glueApiErrorCollection->getErrors();

        // Assert
        $this->assertSame([static::CODE_MISMATCH, static::CODE_NOT_FOUND], array_column($errors, 'code'));
    }

    public function testAnswersWithTheSharedStatusWhenEveryErrorAgrees(): void
    {
        // Arrange
        $glueApiErrorCollection = (new GlueApiErrorCollection())
            ->addError(static::CODE_NOT_FOUND, Response::HTTP_NOT_FOUND, 'First is missing.')
            ->addError(static::CODE_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Second is missing.');

        // Act
        $glueApiException = $glueApiErrorCollection->toGlueApiException();

        // Assert
        $this->assertSame(Response::HTTP_NOT_FOUND, $glueApiException->getStatusCode());
        $this->assertCount(2, $glueApiException->getErrors());
    }

    public function testAnswersUnprocessableWhenTheGatheredStatusesDisagree(): void
    {
        // Arrange
        $glueApiErrorCollection = (new GlueApiErrorCollection())
            ->addError(static::CODE_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Not found.')
            ->addError(static::CODE_MISMATCH, Response::HTTP_UNPROCESSABLE_ENTITY, 'Belongs to another company.');

        // Act
        $glueApiException = $glueApiErrorCollection->toGlueApiException();

        // Assert
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $glueApiException->getStatusCode());
        $this->assertSame(
            [Response::HTTP_NOT_FOUND, Response::HTTP_UNPROCESSABLE_ENTITY],
            array_column($glueApiException->getErrors(), 'status'),
            'Each entry keeps the status of the reason it reports.',
        );
    }

    public function testLeavesASingleErrorAsTheTopLevelOneWithoutListingIt(): void
    {
        // Arrange
        $glueApiErrorCollection = (new GlueApiErrorCollection())
            ->addError(static::CODE_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Not found.');

        // Act
        $glueApiException = $glueApiErrorCollection->toGlueApiException();

        // Assert
        $this->assertSame(static::CODE_NOT_FOUND, $glueApiException->getErrorCode());
        $this->assertSame('Not found.', $glueApiException->getMessage());
        $this->assertSame([], $glueApiException->getErrors());
    }

    public function testRefusesToBuildAnExceptionWhenNothingWasGathered(): void
    {
        // Arrange
        $glueApiErrorCollection = new GlueApiErrorCollection();

        // Expect
        $this->expectException(GlueApiException::class);

        // Act
        $glueApiErrorCollection->toGlueApiException();
    }
}
