<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Test;

/**
 * Test-only probe: an example-replay case that names no resource in `#[ReplaysOpenApiExamples]`.
 * PHP attributes are not inherited, so it names none although its parent does.
 *
 * The file name intentionally omits the "Test" suffix so Codeception does not collect it as a test
 * class; it is called directly by AbstractOpenApiExampleReplayTestCaseTest.
 */
class UnnamedOpenApiExampleReplayProbe extends OpenApiExampleReplayProbe
{
}
