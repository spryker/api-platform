<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Test;

use Spryker\ApiPlatform\Contract\Attribute\ReplaysOpenApiExamples;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Replay\OpenApiExampleReplayContext;
use SprykerTest\ApiPlatform\Test\AbstractOpenApiExampleReplayTestCase;

/**
 * Test-only probe: an example-replay case whose discovery reads the checked-in replay fixtures
 * instead of the generated resources. A real directory, not vfsStream: `glob()` does not support
 * stream wrappers, and the discovered classes have to be autoloadable.
 *
 * The file name intentionally omits the "Test" suffix so Codeception does not collect it as a test
 * class; it is called directly by AbstractOpenApiExampleReplayTestCaseTest.
 */
#[ReplaysOpenApiExamples('replay-items')]
class OpenApiExampleReplayProbe extends AbstractOpenApiExampleReplayTestCase
{
    protected const string FIXTURE_NAMESPACE = 'SprykerTest\\ApiPlatform\\Unit\\Replay\\Fixture';

    protected const string FIXTURE_DIRECTORY = '/../Replay/Fixture';

    protected function createReplayContext(ApiOperation $operation): OpenApiExampleReplayContext
    {
        return new OpenApiExampleReplayContext();
    }

    protected static function generatedResourceDirectory(): string
    {
        return __DIR__ . static::FIXTURE_DIRECTORY;
    }

    protected static function generatedResourceNamespace(): string
    {
        return static::FIXTURE_NAMESPACE;
    }
}
