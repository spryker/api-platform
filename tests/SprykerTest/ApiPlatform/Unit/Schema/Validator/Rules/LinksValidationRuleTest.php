<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator\Rules;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Validator\Rules\LinksValidationRule;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validator
 * @group Rules
 * @group LinksValidationRuleTest
 * Add your own group annotations below this line
 */
class LinksValidationRuleTest extends Unit
{
    protected const string SOURCE_FILE = 'discounts.resource.yml';

    protected ApiUnitTester $tester;

    /**
     * @dataProvider provideLinkDeclarations
     *
     * @param array<string> $expectedMessageFragments
     */
    public function testValidate(mixed $links, int $expectedErrorCount, array $expectedMessageFragments): void
    {
        // Arrange
        $schema = ['sourceFile' => static::SOURCE_FILE];

        if ($links !== null) {
            $schema['links'] = $links;
        }

        $rule = $this->createLinksValidationRule();

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertCount($expectedErrorCount, $errors);

        foreach ($expectedMessageFragments as $index => $expectedMessageFragment) {
            $this->assertStringContainsString($expectedMessageFragment, $errors[$index]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function provideLinkDeclarations(): array
    {
        $validLink = [
            'route' => 'exportDiscountVoucherCodes',
            'parameters' => ['uuid' => 'uuid'],
            'condition' => ['property' => 'discountType', 'equals' => 'voucher'],
            'operations' => ['Get'],
        ];

        return [
            'given a link with a mistyped "params" key, when validating, then one error naming the unknown key' => [
                'links' => ['voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'params' => ['uuid' => 'uuid']]],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['unknown key(s) "params"; allowed keys are "route"'],
            ],
            'given parameters declared as a string, when validating, then one error' => [
                'links' => ['voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'parameters' => 'uuid']],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['"parameters" as a map'],
            ],
            'given a description that is not a string, when validating, then one error' => [
                'links' => ['voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'description' => ['not' => 'a string']]],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['"description" as a string'],
            ],
            'given no links, when validating, then no errors' => [
                'links' => null,
                'expectedErrorCount' => 0,
                'expectedMessageFragments' => [],
            ],
            'given a fully declared link, when validating, then no errors' => [
                'links' => ['voucherCodesExport' => $validLink],
                'expectedErrorCount' => 0,
                'expectedMessageFragments' => [],
            ],
            'given a link with a route only, when validating, then no errors' => [
                'links' => ['voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes']],
                'expectedErrorCount' => 0,
                'expectedMessageFragments' => [],
            ],
            'given links declared as a string, when validating, then one error' => [
                'links' => 'voucherCodesExport',
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['links must be a list of link definitions in ' . static::SOURCE_FILE],
            ],
            'given a link declared as a string, when validating, then one error naming the link' => [
                'links' => ['voucherCodesExport' => 'exportDiscountVoucherCodes'],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['Link "voucherCodesExport" must be an array in ' . static::SOURCE_FILE],
            ],
            'given a link without a route, when validating, then one error naming the link and the source file' => [
                'links' => ['voucherCodesExport' => ['parameters' => ['uuid' => 'uuid']]],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['Link "voucherCodesExport" must declare a non-empty "route" in ' . static::SOURCE_FILE],
            ],
            'given a parameter mapped to a non-string property path, when validating, then one error' => [
                'links' => ['voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'parameters' => ['uuid' => 42]]],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['Link "voucherCodesExport" must map each route parameter to a non-empty property path'],
            ],
            'given a condition declared as a string, when validating, then one error' => [
                'links' => ['voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'condition' => 'voucher']],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['Link "voucherCodesExport" condition must be an array'],
            ],
            'given a condition without a property, when validating, then one error' => [
                'links' => ['voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'condition' => ['equals' => 'voucher']]],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['condition must declare a non-empty "property"'],
            ],
            'given a condition comparing against null, when validating, then no errors' => [
                'links' => [
                    'voucherCodesExport' => [
                        'route' => 'exportDiscountVoucherCodes',
                        'condition' => ['property' => 'deactivatedAt', 'equals' => null],
                    ],
                ],
                'expectedErrorCount' => 0,
                'expectedMessageFragments' => [],
            ],
            'given operations declared as a string, when validating, then one error' => [
                'links' => ['voucherCodesExport' => ['route' => 'exportDiscountVoucherCodes', 'operations' => 'Get']],
                'expectedErrorCount' => 1,
                'expectedMessageFragments' => ['Link "voucherCodesExport" must declare "operations" as a list'],
            ],
            'given one link broken in three ways, when validating, then one error per problem' => [
                'links' => [
                    'voucherCodesExport' => [
                        'parameters' => ['uuid' => ''],
                        'condition' => ['equals' => 'voucher'],
                    ],
                ],
                'expectedErrorCount' => 3,
                'expectedMessageFragments' => [
                    'must declare a non-empty "route"',
                    'must map each route parameter to a non-empty property path',
                    'condition must declare a non-empty "property"',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideLinkReferences
     *
     * @param array<string, mixed> $link
     * @param array<string> $expectedMessageFragments
     */
    public function testValidateChecksTheReferencesAgainstTheResource(array $link, array $expectedMessageFragments): void
    {
        // Arrange
        $schema = [
            'sourceFile' => static::SOURCE_FILE,
            'properties' => ['uuid' => ['type' => 'string'], 'discountType' => ['type' => 'string'], 'promotion' => ['type' => 'object']],
            'operations' => [['type' => 'Get'], ['type' => 'Patch', 'name' => 'activateDiscount']],
            'links' => ['voucherCodesExport' => $link],
        ];

        // Act
        $errors = $this->createLinksValidationRule()->validate($schema);

        // Assert
        $this->assertCount(count($expectedMessageFragments), $errors);

        foreach ($expectedMessageFragments as $index => $expectedMessageFragment) {
            $this->assertStringContainsString($expectedMessageFragment, $errors[$index]);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string>}>
     */
    public function provideLinkReferences(): array
    {
        return [
            'given references that all exist, when validating, then no errors' => [
                [
                    'route' => 'exportDiscountVoucherCodes',
                    'parameters' => ['uuid' => 'uuid'],
                    'condition' => ['property' => 'discountType', 'equals' => 'voucher'],
                    'operations' => ['Get', 'activateDiscount'],
                ],
                [],
            ],
            'given a nested property path, when validating, then its root property is what must exist' => [
                ['route' => 'exportDiscountVoucherCodes', 'parameters' => ['quantity' => 'promotion.quantity']],
                [],
            ],
            'given a mistyped condition property, when validating, then one error naming it' => [
                ['route' => 'exportDiscountVoucherCodes', 'condition' => ['property' => 'discountTyp', 'equals' => 'voucher']],
                ['condition refers to the undeclared property "discountTyp"'],
            ],
            'given a mistyped parameter property, when validating, then one error naming the parameter and the property' => [
                ['route' => 'exportDiscountVoucherCodes', 'parameters' => ['uuid' => 'uid']],
                ['maps route parameter "uuid" to the undeclared property "uid"'],
            ],
            'given an operation the resource does not declare, when validating, then one error naming it' => [
                ['route' => 'exportDiscountVoucherCodes', 'operations' => ['Gte']],
                ['is scoped to "Gte", which is neither an operation type nor an operation name'],
            ],
            'given three mistyped references, when validating, then one error per reference' => [
                [
                    'route' => 'exportDiscountVoucherCodes',
                    'parameters' => ['uuid' => 'uid'],
                    'condition' => ['property' => 'discountTyp', 'equals' => 'voucher'],
                    'operations' => ['Gte'],
                ],
                ['undeclared property "discountTyp"', 'undeclared property "uid"', 'is scoped to "Gte"'],
            ],
        ];
    }

    protected function createLinksValidationRule(): LinksValidationRule
    {
        return $this->tester->getContainer()->get(LinksValidationRule::class);
    }
}
