<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Test;

use Codeception\Test\Unit;
use LogicException;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Test
 * @group AbstractOpenApiExampleReplayTestCaseTest
 * Add your own group annotations below this line
 */
class AbstractOpenApiExampleReplayTestCaseTest extends Unit
{
    public function testGivenACaseNamingAResourceWhenListingReplayableOperationsThenEachServableOperationOfItIsACase(): void
    {
        // Act
        $operations = iterator_to_array(OpenApiExampleReplayProbe::replayableOperations());

        // Assert
        $this->assertSame([
            'GET /customers/{customerReference}/replay-items' => ['GET /customers/{customerReference}/replay-items'],
            'PATCH /replay-items/{uuid}' => ['PATCH /replay-items/{uuid}'],
            'POST /replay-items' => ['POST /replay-items'],
        ], $operations);
    }

    public function testGivenACaseNamingNoResourceWhenListingReplayableOperationsThenItFails(): void
    {
        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s names no resource in #[ReplaysOpenApiExamples].', UnnamedOpenApiExampleReplayProbe::class));

        // Act
        iterator_to_array(UnnamedOpenApiExampleReplayProbe::replayableOperations());
    }
}
