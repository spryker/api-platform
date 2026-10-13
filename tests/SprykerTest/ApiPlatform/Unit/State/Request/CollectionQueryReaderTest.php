<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State\Request;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\ApiPlatform\State\Request\CollectionQueryReader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group Request
 * @group CollectionQueryReaderTest
 * Add your own group annotations below this line
 */
class CollectionQueryReaderTest extends Unit
{
    protected const string DETAILS_INVALID_SORT_FIELD = 'Sort field "%s" is not supported. Supported fields: %s.';

    protected const string ERROR_CODE_INVALID_SORT_FIELD = '5740';

    protected const string FILTER_KEY_PREFIX = 'discounts.';

    protected const string FILTER_DISCOUNT_TYPE = 'discountType';

    protected const string FILTER_IS_ACTIVE = 'isActive';

    protected const string FILTER_VALID_FROM = 'validFrom';

    protected const string FILTER_VOUCHER_BATCH = 'voucherBatch';

    protected const string DETAILS_INVALID_DATE_TIME_FILTER_PREFIX = 'Filter "%s" must be a date in the format %s';

    protected const string DETAILS_INVALID_INTEGER_FILTER = 'Filter "%s" must be an integer.';

    protected const string DETAILS_INVALID_BOOLEAN_FILTER = 'Filter "%s" must be a boolean: true or false.';

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const string SORTABLE_FIELD_DISPLAY_NAME = 'displayName';

    protected const string SORTABLE_FIELD_PRIORITY = 'priority';

    /**
     * @var array<int, string>
     */
    protected const array SORTABLE_FIELDS = [self::SORTABLE_FIELD_DISPLAY_NAME, self::SORTABLE_FIELD_PRIORITY];

    /**
     * @dataProvider provideSortQueries
     *
     * @param array<string, mixed> $query
     * @param array<int, array{string, bool}> $expectedSorts
     */
    public function testReadsTheSortCollectionFromTheRequest(
        array $query,
        array $expectedSorts
    ): void {
        // Act
        $sortTransfers = (new CollectionQueryReader())->getSortCollection(
            new Request($query),
            static::SORTABLE_FIELDS,
            static::ERROR_CODE_INVALID_SORT_FIELD,
        );

        // Assert
        $this->assertSame(
            $expectedSorts,
            array_map(
                static fn ($sortTransfer): array => [(string)$sortTransfer->getField(), (bool)$sortTransfer->getIsAscending()],
                $sortTransfers,
            ),
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, array{string, bool}>}>
     */
    public function provideSortQueries(): array
    {
        return [
            'given no sort parameter, when the sort collection is read, then it is empty' => [
                [], [],
            ],
            'given an empty sort parameter, when the sort collection is read, then it is empty' => [
                ['sort' => ''], [],
            ],
            'given separators only, when the sort collection is read, then it is empty' => [
                ['sort' => ' , '], [],
            ],
            'given a bare field, when the sort collection is read, then it sorts ascending' => [
                ['sort' => static::SORTABLE_FIELD_DISPLAY_NAME], [[static::SORTABLE_FIELD_DISPLAY_NAME, true]],
            ],
            'given a field with the descending prefix, when the sort collection is read, then it sorts descending' => [
                ['sort' => '-' . static::SORTABLE_FIELD_PRIORITY], [[static::SORTABLE_FIELD_PRIORITY, false]],
            ],
            'given two comma separated fields, when the sort collection is read, then both are kept in order' => [
                ['sort' => static::SORTABLE_FIELD_DISPLAY_NAME . ',-' . static::SORTABLE_FIELD_PRIORITY],
                [[static::SORTABLE_FIELD_DISPLAY_NAME, true], [static::SORTABLE_FIELD_PRIORITY, false]],
            ],
            'given surrounding whitespace, when the sort collection is read, then the fields are trimmed' => [
                ['sort' => ' ' . static::SORTABLE_FIELD_DISPLAY_NAME . ' , -' . static::SORTABLE_FIELD_PRIORITY . ' '],
                [[static::SORTABLE_FIELD_DISPLAY_NAME, true], [static::SORTABLE_FIELD_PRIORITY, false]],
            ],
        ];
    }

    /**
     * @dataProvider provideRejectedSortValues
     */
    public function testRejectsASortFieldOutsideTheAllowList(
        string $sortValue,
        string $expectedField
    ): void {
        // Act
        try {
            (new CollectionQueryReader())->getSortCollection(
                new Request(['sort' => $sortValue]),
                static::SORTABLE_FIELDS,
                static::ERROR_CODE_INVALID_SORT_FIELD,
            );
            $this->fail('Expected a GlueApiException to be thrown.');
        } catch (GlueApiException $glueApiException) {
            // Assert
            $this->assertSame(Response::HTTP_BAD_REQUEST, $glueApiException->getStatusCode());
            $this->assertSame(static::ERROR_CODE_INVALID_SORT_FIELD, $glueApiException->getErrorCode());
            $this->assertSame(
                sprintf(
                    static::DETAILS_INVALID_SORT_FIELD,
                    $expectedField,
                    implode(', ', static::SORTABLE_FIELDS),
                ),
                $glueApiException->getMessage(),
            );
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function provideRejectedSortValues(): array
    {
        return [
            'given an unknown field, when the sort collection is read, then the request is rejected' => [
                'unknownField', 'unknownField',
            ],
            'given a sql fragment, when the sort collection is read, then the request is rejected' => [
                'displayName; DROP TABLE spy_discount; --', 'displayName; DROP TABLE spy_discount; --',
            ],
            'given one unknown field among known ones, when the sort collection is read, then the request is rejected' => [
                static::SORTABLE_FIELD_DISPLAY_NAME . ',unknownField', 'unknownField',
            ],
            'given a known field carrying a trailing fragment, when the sort collection is read, then the request is rejected' => [
                'displayName priority', 'displayName priority',
            ],
        ];
    }

    /**
     * @dataProvider provideFilterQueries
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $expectedFilters
     */
    public function testStripsTheResourcePrefixFromTheFilterKeys(
        array $query,
        array $expectedFilters,
        string $lookupKey,
        ?string $expectedFilterValue
    ): void {
        // Arrange
        $collectionQueryReader = new CollectionQueryReader();

        // Act
        $filters = $collectionQueryReader->getFilters(new Request($query), static::FILTER_KEY_PREFIX);

        // Assert
        $this->assertSame($expectedFilters, $filters);
        $this->assertSame($expectedFilterValue, $collectionQueryReader->findFilterValue($filters, $lookupKey));
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, string, string|null}>
     */
    public function provideFilterQueries(): array
    {
        return [
            'given a prefixed filter key, when the filters are read, then the prefix is stripped' => [
                ['filter' => ['discounts.displayName' => 'spring']],
                ['displayName' => 'spring'],
                'displayName',
                'spring',
            ],
            'given a bare filter key, when the filters are read, then it is kept as it is' => [
                ['filter' => ['displayName' => 'spring']],
                ['displayName' => 'spring'],
                'displayName',
                'spring',
            ],
            'given a filter key of another resource, when the filters are read, then it passes through untouched' => [
                ['filter' => ['discount-voucher-codes.code' => 'SALE']],
                ['discount-voucher-codes.code' => 'SALE'],
                'displayName',
                null,
            ],
            'given no filter parameter, when the filters are read, then there are none' => [
                [],
                [],
                'displayName',
                null,
            ],
            'given a filter value of whitespace only, when it is looked up, then it reads as absent' => [
                ['filter' => ['discounts.displayName' => '  ']],
                ['displayName' => '  '],
                'displayName',
                null,
            ],
            'given a filter value with surrounding whitespace, when it is looked up, then it is trimmed' => [
                ['filter' => ['discounts.displayName' => ' spring ']],
                ['displayName' => ' spring '],
                'displayName',
                'spring',
            ],
            'given a non-scalar filter value, when it is looked up, then it reads as absent' => [
                ['filter' => ['discounts.displayName' => ['spring']]],
                ['displayName' => ['spring']],
                'displayName',
                null,
            ],
        ];
    }

    /**
     * @dataProvider provideListFilterQueries
     *
     * @param array<string, mixed> $query
     * @param array<int, string> $expectedValues
     */
    public function testReadsAListFilterValue(
        array $query,
        array $expectedValues
    ): void {
        // Arrange
        $collectionQueryReader = new CollectionQueryReader();

        // Act
        $filters = $collectionQueryReader->getFilters(new Request($query), static::FILTER_KEY_PREFIX);

        // Assert
        $this->assertSame($expectedValues, $collectionQueryReader->findFilterValues($filters, static::FILTER_DISCOUNT_TYPE));
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, string>}>
     */
    public function provideListFilterQueries(): array
    {
        return [
            'given no list filter, when it is looked up, then the list is empty' => [
                [],
                [],
            ],
            'given a single value, when it is looked up, then the list holds that value' => [
                ['filter' => ['discounts.discountType' => 'voucher']],
                ['voucher'],
            ],
            'given a comma separated value, when it is looked up, then it is split and trimmed' => [
                ['filter' => ['discounts.discountType' => ' cart_rule , voucher ']],
                ['cart_rule', 'voucher'],
            ],
            'given repeated array members, when it is looked up, then every member is kept in order' => [
                ['filter' => ['discounts.discountType' => ['cart_rule', 'voucher']]],
                ['cart_rule', 'voucher'],
            ],
            'given blank entries, when it is looked up, then they are dropped' => [
                ['filter' => ['discounts.discountType' => ['', ' ', 'voucher']]],
                ['voucher'],
            ],
            'given a nested array member, when it is looked up, then it is skipped' => [
                ['filter' => ['discounts.discountType' => [['voucher'], 'cart_rule']]],
                ['cart_rule'],
            ],
        ];
    }

    /**
     * @dataProvider provideBooleanFilterQueries
     *
     * @param array<string, mixed> $query
     */
    public function testReadsABooleanFilterValue(
        array $query,
        ?bool $expectedValue
    ): void {
        // Arrange
        $collectionQueryReader = new CollectionQueryReader();

        // Act
        $filters = $collectionQueryReader->getFilters(new Request($query), static::FILTER_KEY_PREFIX);

        // Assert
        $this->assertSame($expectedValue, $collectionQueryReader->findBooleanFilterValue($filters, static::FILTER_IS_ACTIVE));
    }

    /**
     * @return array<string, array{array<string, mixed>, bool|null}>
     */
    public function provideBooleanFilterQueries(): array
    {
        return [
            'given no boolean filter, when it is looked up, then it reads as absent' => [
                [],
                null,
            ],
            'given a blank boolean filter, when it is looked up, then it reads as absent' => [
                ['filter' => ['discounts.isActive' => ' ']],
                null,
            ],
            'given "true", when it is looked up, then it is true' => [
                ['filter' => ['discounts.isActive' => 'true']],
                true,
            ],
            'given "1", when it is looked up, then it is true' => [
                ['filter' => ['discounts.isActive' => '1']],
                true,
            ],
            'given "false", when it is looked up, then it is false' => [
                ['filter' => ['discounts.isActive' => 'false']],
                false,
            ],
            'given "0", when it is looked up, then it is false' => [
                ['filter' => ['discounts.isActive' => '0']],
                false,
            ],
        ];
    }

    public function testRejectsANonBooleanFilterValue(): void
    {
        // Arrange
        $collectionQueryReader = new CollectionQueryReader();
        $filters = $collectionQueryReader->getFilters(
            new Request(['filter' => ['discounts.isActive' => 'maybe']]),
            static::FILTER_KEY_PREFIX,
        );

        // Act
        try {
            $collectionQueryReader->findBooleanFilterValue($filters, static::FILTER_IS_ACTIVE);
            $this->fail('Expected a GlueApiException to be thrown.');
        } catch (GlueApiException $glueApiException) {
            // Assert
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $glueApiException->getStatusCode());
            $this->assertSame(static::ERROR_CODE_VALIDATION, $glueApiException->getErrorCode());
            $this->assertSame(
                sprintf(static::DETAILS_INVALID_BOOLEAN_FILTER, static::FILTER_IS_ACTIVE),
                $glueApiException->getMessage(),
            );
        }
    }

    /**
     * @dataProvider provideDateTimeFilterQueries
     *
     * @param array<string, mixed> $query
     */
    public function testReadsADateTimeFilterValue(array $query, ?string $expectedValue): void
    {
        // Arrange
        $collectionQueryReader = new CollectionQueryReader();

        // Act
        $filters = $collectionQueryReader->getFilters(new Request($query), static::FILTER_KEY_PREFIX);

        // Assert
        $this->assertSame($expectedValue, $collectionQueryReader->findDateTimeFilterValue($filters, static::FILTER_VALID_FROM));
    }

    /**
     * @return array<string, array{array<string, mixed>, string|null}>
     */
    public function provideDateTimeFilterQueries(): array
    {
        return [
            'given no date filter, when it is looked up, then it reads as absent' => [
                [],
                null,
            ],
            'given a date in the expected format, when it is looked up, then it is returned trimmed' => [
                ['filter' => ['discounts.validFrom' => ' 2026-01-31 23:59:59 ']],
                '2026-01-31 23:59:59',
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidDateTimeFilterValues
     */
    public function testRejectsADateTimeFilterValueOutsideTheFormat(string $value): void
    {
        // Arrange
        $collectionQueryReader = new CollectionQueryReader();
        $filters = $collectionQueryReader->getFilters(
            new Request(['filter' => ['discounts.validFrom' => $value]]),
            static::FILTER_KEY_PREFIX,
        );

        // Act
        try {
            $collectionQueryReader->findDateTimeFilterValue($filters, static::FILTER_VALID_FROM);
            $this->fail('Expected a GlueApiException to be thrown.');
        } catch (GlueApiException $glueApiException) {
            // Assert
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $glueApiException->getStatusCode());
            $this->assertSame(static::ERROR_CODE_VALIDATION, $glueApiException->getErrorCode());
            $this->assertStringStartsWith(
                sprintf(static::DETAILS_INVALID_DATE_TIME_FILTER_PREFIX, static::FILTER_VALID_FROM, CollectionQueryReader::DATE_TIME_FILTER_FORMAT),
                $glueApiException->getMessage(),
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public function provideInvalidDateTimeFilterValues(): array
    {
        return [
            'given a word, when it is looked up, then it is rejected' => ['abc'],
            'given a date without a time, when it is looked up, then it is rejected' => ['2026-01-31'],
            'given an impossible calendar date, when it is looked up, then it is rejected' => ['2026-02-30 00:00:00'],
            'given a sql fragment, when it is looked up, then it is rejected' => ["2026-01-01 00:00:00' OR 1=1 --"],
        ];
    }

    /**
     * @dataProvider provideIntegerFilterQueries
     *
     * @param array<string, mixed> $query
     */
    public function testReadsAnIntegerFilterValue(array $query, ?int $expectedValue): void
    {
        // Arrange
        $collectionQueryReader = new CollectionQueryReader();

        // Act
        $filters = $collectionQueryReader->getFilters(new Request($query), static::FILTER_KEY_PREFIX);

        // Assert
        $this->assertSame($expectedValue, $collectionQueryReader->findIntegerFilterValue($filters, static::FILTER_VOUCHER_BATCH));
    }

    /**
     * @return array<string, array{array<string, mixed>, int|null}>
     */
    public function provideIntegerFilterQueries(): array
    {
        return [
            'given no integer filter, when it is looked up, then it reads as absent' => [
                [],
                null,
            ],
            'given "0", when it is looked up, then it is zero' => [
                ['filter' => ['discounts.voucherBatch' => '0']],
                0,
            ],
            'given a padded number, when it is looked up, then it is the integer' => [
                ['filter' => ['discounts.voucherBatch' => ' 42 ']],
                42,
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidIntegerFilterValues
     */
    public function testRejectsANonIntegerFilterValue(string $value): void
    {
        // Arrange
        $collectionQueryReader = new CollectionQueryReader();
        $filters = $collectionQueryReader->getFilters(
            new Request(['filter' => ['discounts.voucherBatch' => $value]]),
            static::FILTER_KEY_PREFIX,
        );

        // Act
        try {
            $collectionQueryReader->findIntegerFilterValue($filters, static::FILTER_VOUCHER_BATCH);
            $this->fail('Expected a GlueApiException to be thrown.');
        } catch (GlueApiException $glueApiException) {
            // Assert
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $glueApiException->getStatusCode());
            $this->assertSame(static::ERROR_CODE_VALIDATION, $glueApiException->getErrorCode());
            $this->assertSame(
                sprintf(static::DETAILS_INVALID_INTEGER_FILTER, static::FILTER_VOUCHER_BATCH),
                $glueApiException->getMessage(),
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public function provideInvalidIntegerFilterValues(): array
    {
        return [
            'given a word, when it is looked up, then it is rejected' => ['abc'],
            'given a decimal, when it is looked up, then it is rejected' => ['1.5'],
            'given a number with a suffix, when it is looked up, then it is rejected' => ['3 DROP'],
        ];
    }
}
