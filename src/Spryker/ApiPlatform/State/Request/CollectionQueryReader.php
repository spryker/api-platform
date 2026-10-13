<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Request;

use DateTimeImmutable;
use Generated\Shared\Transfer\SortTransfer;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CollectionQueryReader implements CollectionQueryReaderInterface
{
    protected const string QUERY_PARAM_SORT = 'sort';

    protected const string QUERY_PARAM_FILTER = 'filter';

    protected const string SORT_DESCENDING_PREFIX = '-';

    protected const string DETAILS_INVALID_SORT_FIELD = 'Sort field "%s" is not supported. Supported fields: %s.';

    protected const string DETAILS_INVALID_BOOLEAN_FILTER = 'Filter "%s" must be a boolean: true or false.';

    protected const string DETAILS_INVALID_DATE_TIME_FILTER = 'Filter "%s" must be a date in the format %s, for example %s.';

    protected const string DETAILS_INVALID_INTEGER_FILTER = 'Filter "%s" must be an integer.';

    public const string DATE_TIME_FILTER_FORMAT = 'Y-m-d H:i:s';

    /**
     * @uses \Spryker\ApiPlatform\Serializer\TranslatingConstraintViolationListNormalizer::ERROR_CODE_VALIDATION
     */
    protected const string ERROR_CODE_VALIDATION = '901';

    /**
     * @var non-empty-string
     */
    protected const string FILTER_VALUE_SEPARATOR = ',';

    /**
     * @var non-empty-string
     */
    protected const string SORT_FIELD_SEPARATOR = ',';

    /**
     * @param array<int, string> $sortableFields
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     *
     * @return array<int, \Generated\Shared\Transfer\SortTransfer>
     */
    public function getSortCollection(
        Request $request,
        array $sortableFields,
        string $invalidSortFieldErrorCode
    ): array {
        $sort = $request->query->get(static::QUERY_PARAM_SORT);

        if (!is_string($sort) || $sort === '') {
            return [];
        }

        $sortTransfers = [];

        foreach (explode(static::SORT_FIELD_SEPARATOR, $sort) as $sortField) {
            $sortField = trim($sortField);

            if ($sortField === '') {
                continue;
            }

            $isAscending = !str_starts_with($sortField, static::SORT_DESCENDING_PREFIX);
            $field = ltrim($sortField, static::SORT_DESCENDING_PREFIX);

            if (!in_array($field, $sortableFields, true)) {
                throw $this->createInvalidSortFieldException($field, $sortableFields, $invalidSortFieldErrorCode);
            }

            $sortTransfers[] = (new SortTransfer())->setField($field)->setIsAscending($isAscending);
        }

        return $sortTransfers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(
        Request $request,
        string $filterKeyPrefix
    ): array {
        $filters = [];

        foreach ($request->query->all(static::QUERY_PARAM_FILTER) as $key => $value) {
            $property = str_starts_with($key, $filterKeyPrefix) ? substr($key, strlen($filterKeyPrefix)) : $key;
            $filters[$property] = $value;
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function findFilterValue(array $filters, string $key): ?string
    {
        if (!isset($filters[$key]) || !is_scalar($filters[$key])) {
            return null;
        }

        $value = trim((string)$filters[$key]);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, string>
     */
    public function findFilterValues(array $filters, string $key): array
    {
        if (!isset($filters[$key])) {
            return [];
        }

        $rawValues = is_array($filters[$key])
            ? $filters[$key]
            : explode(static::FILTER_VALUE_SEPARATOR, (string)$filters[$key]);

        $values = [];

        foreach ($rawValues as $rawValue) {
            if (!is_scalar($rawValue)) {
                continue;
            }

            $value = trim((string)$rawValue);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    public function findBooleanFilterValue(array $filters, string $key): ?bool
    {
        $value = $this->findFilterValue($filters, $key);

        if ($value === null) {
            return null;
        }

        $booleanValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($booleanValue === null) {
            throw new GlueApiException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                static::ERROR_CODE_VALIDATION,
                sprintf(static::DETAILS_INVALID_BOOLEAN_FILTER, $key),
            );
        }

        return $booleanValue;
    }

    /**
     * @param array<int, string> $sortableFields
     */
    protected function createInvalidSortFieldException(
        string $sortField,
        array $sortableFields,
        string $invalidSortFieldErrorCode
    ): GlueApiException {
        return new GlueApiException(
            Response::HTTP_BAD_REQUEST,
            $invalidSortFieldErrorCode,
            sprintf(static::DETAILS_INVALID_SORT_FIELD, $sortField, implode(', ', $sortableFields)),
        );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    public function findDateTimeFilterValue(array $filters, string $key, string $format = self::DATE_TIME_FILTER_FORMAT): ?string
    {
        $value = $this->findFilterValue($filters, $key);

        if ($value === null) {
            return null;
        }

        $dateTime = DateTimeImmutable::createFromFormat($format, $value);

        if ($dateTime === false || $dateTime->format($format) !== $value) {
            throw new GlueApiException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                static::ERROR_CODE_VALIDATION,
                sprintf(static::DETAILS_INVALID_DATE_TIME_FILTER, $key, $format, (new DateTimeImmutable('2026-01-31 23:59:59'))->format($format)),
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    public function findIntegerFilterValue(array $filters, string $key): ?int
    {
        $value = $this->findFilterValue($filters, $key);

        if ($value === null) {
            return null;
        }

        $integerValue = filter_var($value, FILTER_VALIDATE_INT);

        if ($integerValue === false) {
            throw new GlueApiException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                static::ERROR_CODE_VALIDATION,
                sprintf(static::DETAILS_INVALID_INTEGER_FILTER, $key),
            );
        }

        return $integerValue;
    }
}
