<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validation\Collector;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Validation\Collector\ExcludedValidationRuleCollector;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validation
 * @group Collector
 * @group ExcludedValidationRuleCollectorTest
 * Add your own group annotations below this line
 */
class ExcludedValidationRuleCollectorTest extends Unit
{
    public function testGivenAnExcludedRuleTheMergedSchemaLacksWhenCollectingThenReportsIt(): void
    {
        // Arrange
        $excludedSchema = [
            'post' => [
                'password' => ['NotBlank', 'NotCompromisedPassword'],
            ],
        ];
        $mergedSchema = [
            'post' => [
                'password' => ['NotBlank'],
            ],
        ];
        $collector = new ExcludedValidationRuleCollector();

        // Act
        $lostRules = $collector->collectLostRules($excludedSchema, $mergedSchema);

        // Assert
        $this->assertSame(['post.password.NotCompromisedPassword'], $lostRules);
    }

    public function testGivenAnExcludedSchemaThatOnlyRepeatsMergedRulesWhenCollectingThenReportsNothing(): void
    {
        // Arrange — differing options do not make a rule new; the constraint is what runs or does not.
        $excludedSchema = [
            'post' => [
                'email' => ['NotBlank', ['Email' => ['message' => 'validation.email']]],
            ],
        ];
        $mergedSchema = [
            'post' => [
                'email' => [['NotBlank' => null], 'Email'],
            ],
        ];
        $collector = new ExcludedValidationRuleCollector();

        // Act
        $lostRules = $collector->collectLostRules($excludedSchema, $mergedSchema);

        // Assert
        $this->assertSame([], $lostRules);
    }

    public function testGivenARuleUnderACollectionFieldWhenCollectingThenNamesItByItsFieldPath(): void
    {
        // Arrange
        $excludedSchema = [
            'post' => [
                'quote' => [
                    [
                        'Collection' => [
                            'fields' => [
                                'customer' => [
                                    [
                                        'Collection' => [
                                            'fields' => [
                                                'email' => ['Email'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $collector = new ExcludedValidationRuleCollector();

        // Act
        $lostRules = $collector->collectLostRules($excludedSchema, []);

        // Assert
        $this->assertSame(['post.quote.customer.email.Email'], $lostRules);
    }

    public function testGivenARuleBehindACascadingConstraintWhenCollectingThenKeepsTheOwningPath(): void
    {
        // Arrange — `Optional` and `All` hold constraints rather than declaring one, so they add no
        // path segment of their own.
        $excludedSchema = [
            'patch' => [
                'prices' => [
                    [
                        'Optional' => [
                            'constraints' => [
                                ['All' => ['constraints' => ['NotBlank']]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $collector = new ExcludedValidationRuleCollector();

        // Act
        $lostRules = $collector->collectLostRules($excludedSchema, []);

        // Assert
        $this->assertSame(['patch.prices.NotBlank'], $lostRules);
    }

    public function testGivenALengthThatTightensOneBoundWhenCollectingThenReportsOnlyThatBound(): void
    {
        // Arrange
        $excludedSchema = [
            'post' => [
                'refreshToken' => [['Length' => ['min' => 10]]],
            ],
        ];
        $mergedSchema = [
            'post' => [
                'refreshToken' => [['Length' => ['max' => 128]]],
            ],
        ];
        $collector = new ExcludedValidationRuleCollector();

        // Act
        $lostRules = $collector->collectLostRules($excludedSchema, $mergedSchema);

        // Assert
        $this->assertSame(['post.refreshToken.Length.min'], $lostRules);
    }

    public function testGivenAFullyQualifiedConstraintWhenCollectingThenNamesItByItsShortName(): void
    {
        // Arrange
        $excludedSchema = [
            'post' => [
                'email' => ['\Spryker\Zed\Customer\Business\Validator\UniqueEmail'],
            ],
        ];
        $collector = new ExcludedValidationRuleCollector();

        // Act
        $lostRules = $collector->collectLostRules($excludedSchema, []);

        // Assert
        $this->assertSame(['post.email.UniqueEmail'], $lostRules);
    }
}
