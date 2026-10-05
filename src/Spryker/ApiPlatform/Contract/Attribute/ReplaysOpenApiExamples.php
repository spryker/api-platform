<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Attribute;

use Attribute;

/**
 * Names the resources an example-replay test class replays: every servable operation of each one is
 * sent as its generated OpenAPI example describes it, and must not answer with a server error. Belongs
 * on a class extending \SprykerTest\ApiPlatform\Test\AbstractOpenApiExampleReplayTestCase.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ReplaysOpenApiExamples
{
    /**
     * @var array<string>
     */
    public array $resourceShortNames;

    public function __construct(string ...$resourceShortNames)
    {
        $this->resourceShortNames = array_values($resourceShortNames);
    }
}
