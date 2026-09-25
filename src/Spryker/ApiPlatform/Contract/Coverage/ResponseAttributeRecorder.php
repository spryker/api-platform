<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Records the response attribute paths one test method actually asserted on — the runtime
 * counterpart of {@see ResponseAttributeTruthCollector}, with
 * {@see ResponseAttributeRecorder::verify()} naming the difference.
 *
 * Both sides pass through {@see ResponseAttributePath::normalize()}, so an assertion on
 * `customers[0].firstName` satisfies the truth path `customers[].firstName`.
 */
class ResponseAttributeRecorder
{
    /**
     * @var array<string, true>
     */
    protected array $asserted = [];

    public function record(string $path): void
    {
        $this->asserted[ResponseAttributePath::normalize($path)] = true;
    }

    /**
     * @param array<string> $expectedPaths
     *
     * @return array<string>
     */
    public function verify(array $expectedPaths): array
    {
        return array_values(array_filter(
            $expectedPaths,
            fn (string $path): bool => !isset($this->asserted[ResponseAttributePath::normalize($path)]),
        ));
    }
}
