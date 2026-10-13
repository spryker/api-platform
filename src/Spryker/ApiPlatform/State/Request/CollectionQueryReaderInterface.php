<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Request;

use Symfony\Component\HttpFoundation\Request;

interface CollectionQueryReaderInterface
{
    /**
     * @param array<int, string> $sortableFields
     * @param string $invalidSortFieldErrorCode Glue error code reported when a sort field is outside `$sortableFields`.
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     *
     * @return array<int, \Generated\Shared\Transfer\SortTransfer>
     */
    public function getSortCollection(Request $request, array $sortableFields, string $invalidSortFieldErrorCode): array;

    /**
     * @return array<string, mixed>
     */
    public function getFilters(Request $request, string $filterKeyPrefix): array;

    /**
     * @param array<string, mixed> $filters
     */
    public function findFilterValue(array $filters, string $key): ?string;

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, string>
     */
    public function findFilterValues(array $filters, string $key): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    public function findBooleanFilterValue(array $filters, string $key): ?bool;

    /**
     * Returns the trimmed value when it is a date in the given format, null when absent, and rejects anything else
     * with the validation error (code 901) so the value never reaches the database unparsed.
     *
     * @param array<string, mixed> $filters
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    public function findDateTimeFilterValue(array $filters, string $key, string $format = CollectionQueryReader::DATE_TIME_FILTER_FORMAT): ?string;

    /**
     * @param array<string, mixed> $filters
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    public function findIntegerFilterValue(array $filters, string $key): ?int;
}
