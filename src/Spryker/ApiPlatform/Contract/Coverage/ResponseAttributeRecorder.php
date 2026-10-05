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
 *
 * A path asserted with an empty value (`[]` or `null`) is kept apart. An empty array has no element,
 * so a test that saw one owes none of its element paths (`discounts[].code`): the test that gives the
 * fixture an element proves them. Once empty values are rejected, the empty assertion no longer
 * counts for the bare path either.
 */
class ResponseAttributeRecorder
{
    protected const string ELEMENT_PATH_MARKER = '[].';

    /**
     * @var array<string, true>
     */
    protected array $asserted = [];

    /**
     * @var array<string, true>
     */
    protected array $emptyAsserted = [];

    public function record(string $path): void
    {
        $this->asserted[ResponseAttributePath::normalize($path)] = true;
    }

    /**
     * Records a path together with the value the assertion saw, so an empty value can be told apart.
     */
    public function recordValue(string $path, mixed $value): void
    {
        if ($value === [] || $value === null) {
            $this->emptyAsserted[ResponseAttributePath::normalize($path)] = true;

            return;
        }

        $this->record($path);
    }

    /**
     * @param array<string> $expectedPaths
     * @param bool $isEmptyValueRejected Whether a path asserted only with an empty value stays unasserted,
     *   including the element paths of an array asserted only as empty.
     *
     * @return array<string>
     */
    public function verify(array $expectedPaths, bool $isEmptyValueRejected = false): array
    {
        return array_values(array_filter(
            $expectedPaths,
            fn (string $path): bool => !$this->isAsserted(ResponseAttributePath::normalize($path), $isEmptyValueRejected)
                && ($isEmptyValueRejected || !$this->isElementOfAnEmptyArray(ResponseAttributePath::normalize($path))),
        ));
    }

    /**
     * The paths asserted only with an empty value, for the failure message.
     *
     * @return array<string>
     */
    public function emptyAssertedPaths(): array
    {
        return array_keys(array_diff_key($this->emptyAsserted, $this->asserted));
    }

    /**
     * Whether the test asserted the path with a value that is neither `[]` nor `null`.
     */
    public function isAssertedNonEmpty(string $path): bool
    {
        return isset($this->asserted[ResponseAttributePath::normalize($path)]);
    }

    protected function isAsserted(string $normalizedPath, bool $isEmptyValueRejected): bool
    {
        return isset($this->asserted[$normalizedPath]) || (!$isEmptyValueRejected && isset($this->emptyAsserted[$normalizedPath]));
    }

    protected function isElementOfAnEmptyArray(string $normalizedPath): bool
    {
        $markerPosition = strrpos($normalizedPath, static::ELEMENT_PATH_MARKER);
        if ($markerPosition === false) {
            return false;
        }

        $arrayPath = substr($normalizedPath, 0, $markerPosition);

        return isset($this->emptyAsserted[$arrayPath]) && !$this->hasAssertedElement($arrayPath);
    }

    /**
     * An element proven by the same test beats an empty assertion, e.g. on another member of a
     * collection response.
     */
    protected function hasAssertedElement(string $arrayPath): bool
    {
        foreach (array_keys($this->asserted) as $assertedPath) {
            if (str_starts_with($assertedPath, $arrayPath . static::ELEMENT_PATH_MARKER)) {
                return true;
            }
        }

        return false;
    }
}
