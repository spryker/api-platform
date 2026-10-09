<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ThrownStatusAnalyzer;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SelfDispatchingThrownStatusFixtureProcessor;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ThrownStatusFixtureArgumentRequiredHttpException;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ThrownStatusFixtureProcessor;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ThrownStatusFixtureProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ThrownStatusAnalyzerTest
 * Add your own group annotations below this line
 */
class ThrownStatusAnalyzerTest extends Unit
{
    public function testGivenAnExceptionFactoryWhenAnalyzingThenTheStatusTheFactoryReturnsIsThrown(): void
    {
        // Act
        $statuses = (new ThrownStatusAnalyzer())->thrownStatuses(ThrownStatusFixtureProvider::class, 'provideItem');

        // Assert
        $this->assertSame([Response::HTTP_GONE], $statuses);
    }

    public function testGivenAFactoryStatusLeftAtItsDefaultWhenAnalyzingThenTheDefaultIsThrown(): void
    {
        // Act
        $statuses = (new ThrownStatusAnalyzer())->thrownStatuses(ThrownStatusFixtureProcessor::class, 'processPost');

        // Assert
        $this->assertSame([Response::HTTP_UNPROCESSABLE_ENTITY], $statuses);
    }

    public function testGivenAFactoryStatusPassedByTheCallerAndAThrowInACalledMethodWhenAnalyzingThenBothAreThrown(): void
    {
        // Act
        $statuses = (new ThrownStatusAnalyzer())->thrownStatuses(ThrownStatusFixtureProcessor::class, 'processPatch');

        // Assert
        $this->assertSame([Response::HTTP_BAD_REQUEST, Response::HTTP_NOT_FOUND], $statuses);
    }

    public function testGivenAnAccessDeniedAndAFixedStatusHttpExceptionWhenAnalyzingThenForbiddenAndTheFixedStatusAreThrown(): void
    {
        // Act
        $statuses = (new ThrownStatusAnalyzer())->thrownStatuses(ThrownStatusFixtureProcessor::class, 'processDelete');

        // Assert
        $this->assertSame([Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND], $statuses);
    }

    public function testGivenAStatusConstructorArgumentWhenAnalyzingThenItsStatusIsThrown(): void
    {
        // Act
        $statuses = (new ThrownStatusAnalyzer())->thrownStatuses(ThrownStatusFixtureProvider::class, 'provideCollection');

        // Assert
        $this->assertSame([Response::HTTP_NOT_IMPLEMENTED], $statuses);
    }

    public function testGivenATernaryStatusWhenAnalyzingThenBothBranchesAreThrown(): void
    {
        // Act
        $statuses = (new ThrownStatusAnalyzer())->thrownStatuses(SelfDispatchingThrownStatusFixtureProcessor::class, 'process');

        // Assert
        $this->assertSame([Response::HTTP_CONFLICT, Response::HTTP_GONE], $statuses);
    }

    public function testGivenAStatusReadFromAnErrorMappingAtRuntimeWhenAnalyzingThenNoStatusIsReported(): void
    {
        // Act
        $statuses = (new ThrownStatusAnalyzer())->thrownStatuses(ThrownStatusFixtureProcessor::class, 'throwMappedException');

        // Assert
        $this->assertSame([], $statuses);
    }

    public function testGivenAnHttpExceptionWhoseConstructorRequiresAnArgumentWhenAnalyzingThenItIsReportedUnreadableInsteadOfThrowing(): void
    {
        // Arrange
        $thrownStatusAnalyzer = new ThrownStatusAnalyzer();

        // Act
        $statuses = $thrownStatusAnalyzer->thrownStatuses(ThrownStatusFixtureProcessor::class, 'throwArgumentRequiredException');
        $unreadableExceptionClassNames = $thrownStatusAnalyzer->unreadableExceptionClassNames(ThrownStatusFixtureProcessor::class, 'throwArgumentRequiredException');

        // Assert
        $this->assertSame([], $statuses);
        $this->assertSame([ThrownStatusFixtureArgumentRequiredHttpException::class], $unreadableExceptionClassNames);
    }
}
