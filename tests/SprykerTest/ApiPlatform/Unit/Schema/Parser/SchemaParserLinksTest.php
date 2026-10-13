<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Parser;

use Codeception\Test\Unit;
use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Schema\Parser\SchemaParser;
use Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;
use Spryker\ApiPlatform\Schema\Validator\PreMergeValidatorInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Parser
 * @group SchemaParserLinksTest
 * Add your own group annotations below this line
 */
class SchemaParserLinksTest extends Unit
{
    protected ApiUnitTester $tester;

    /**
     * @dataProvider provideLinkNormalization
     *
     * @param array<string, mixed> $resource
     * @param array<string, array<string, mixed>> $expectedLinks
     */
    public function testParseNormalizesLinks(array $resource, array $expectedLinks): void
    {
        // Arrange
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse(['resource' => $resource + ['name' => 'Discounts']], new SplFileInfo(__FILE__));

        // Assert
        $this->assertSame($expectedLinks, $result['links']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function provideLinkNormalization(): array
    {
        return [
            'given no links key, when parsing, then links is an empty array' => [
                'resource' => [],
                'expectedLinks' => [],
            ],
            'given a list of links, when parsing, then they are keyed by their name' => [
                'resource' => [
                    'links' => [
                        ['name' => 'voucherCodesExport', 'route' => 'exportDiscountVoucherCodes'],
                        ['name' => 'auditLog', 'route' => 'getDiscountAuditLog'],
                    ],
                ],
                'expectedLinks' => [
                    'voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes'],
                    'auditLog' => ['route' => 'getDiscountAuditLog'],
                ],
            ],
            'given a link with parameters, condition, operations and description, when parsing, then all of them are carried through' => [
                'resource' => [
                    'links' => [
                        [
                            'name' => 'voucherCodesExport',
                            'route' => 'exportDiscountVoucherCodes',
                            'description' => 'CSV download of all voucher codes of this discount.',
                            'parameters' => ['uuid' => 'uuid'],
                            'condition' => ['property' => 'discountType', 'equals' => 'voucher'],
                            'operations' => ['Get'],
                        ],
                    ],
                ],
                'expectedLinks' => [
                    'voucherCodesExport' => [
                        'route' => 'exportDiscountVoucherCodes',
                        'description' => 'CSV download of all voucher codes of this discount.',
                        'parameters' => ['uuid' => 'uuid'],
                        'condition' => ['property' => 'discountType', 'equals' => 'voucher'],
                        'operations' => ['Get'],
                    ],
                ],
            ],
            'given a link that is already keyed by name, when parsing, then the key is the link name' => [
                'resource' => [
                    'links' => [
                        'voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes'],
                    ],
                ],
                'expectedLinks' => [
                    'voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes'],
                ],
            ],
            'given two layers declaring the same link name, when parsing, then the map holds one entry' => [
                'resource' => [
                    'links' => [
                        ['name' => 'voucherCodesExport', 'route' => 'exportDiscountVoucherCodes'],
                        ['name' => 'voucherCodesExport', 'route' => 'exportProjectVoucherCodes'],
                    ],
                ],
                'expectedLinks' => [
                    'voucherCodesExport' => ['route' => 'exportProjectVoucherCodes'],
                ],
            ],
            'given a link with an unsupported key, when parsing, then the key is carried through for the validation rule to reject' => [
                'resource' => [
                    'links' => [
                        ['name' => 'voucherCodesExport', 'route' => 'exportDiscountVoucherCodes', 'method' => 'GET'],
                    ],
                ],
                'expectedLinks' => [
                    'voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'method' => 'GET'],
                ],
            ],
            'given a link key declared with the wrong type, when parsing, then the value is carried through for the validation rule to reject' => [
                'resource' => [
                    'links' => [
                        [
                            'name' => 'voucherCodesExport',
                            'route' => 'exportDiscountVoucherCodes',
                            'parameters' => 'uuid',
                            'operations' => 'Get',
                        ],
                    ],
                ],
                'expectedLinks' => [
                    'voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'parameters' => 'uuid', 'operations' => 'Get'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideMalformedLinks
     */
    public function testParseRejectsMalformedLinks(mixed $links, string $expectedMessage): void
    {
        // Arrange
        $parser = $this->createSchemaParser();

        // Expect
        $this->expectException(ApiSchemaValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        // Act
        $parser->parse(['resource' => ['name' => 'Discounts', 'links' => $links]], new SplFileInfo(__FILE__));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function provideMalformedLinks(): array
    {
        return [
            'given links declared as a string, when parsing, then the schema is rejected' => [
                'links' => 'voucherCodesExport',
                'expectedMessage' => 'Links must be an array',
            ],
            'given a scalar list entry, when parsing, then the schema is rejected' => [
                'links' => ['voucherCodesExport'],
                'expectedMessage' => 'Every link must be an array',
            ],
            'given a link without a name, when parsing, then the schema is rejected' => [
                'links' => [['route' => 'exportDiscountVoucherCodes']],
                'expectedMessage' => 'Every link must have a non-empty "name"',
            ],
            'given a link with an empty name, when parsing, then the schema is rejected' => [
                'links' => [['name' => '', 'route' => 'exportDiscountVoucherCodes']],
                'expectedMessage' => 'Every link must have a non-empty "name"',
            ],
            'given a link whose name is not a string, when parsing, then the schema is rejected' => [
                'links' => [['name' => 42, 'route' => 'exportDiscountVoucherCodes']],
                'expectedMessage' => 'Every link must have a non-empty "name"',
            ],
        ];
    }

    protected function createSchemaParser(): SchemaParser
    {
        $this->tester->getContainer()->set(
            PreMergeValidatorInterface::class,
            $this->makeEmpty(PreMergeValidatorInterface::class),
        );
        $this->tester->getContainer()->set(
            ValidationGroupMapperInterface::class,
            $this->makeEmpty(ValidationGroupMapperInterface::class),
        );

        return $this->tester->getContainer()->get(SchemaParserInterface::class);
    }
}
