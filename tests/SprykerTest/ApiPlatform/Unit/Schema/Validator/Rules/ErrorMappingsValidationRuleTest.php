<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator\Rules;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Validator\Rules\ErrorMappingsValidationRule;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping\FixtureErrorMappingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validator
 * @group Rules
 * @group ErrorMappingsValidationRuleTest
 * Add your own group annotations below this line
 */
class ErrorMappingsValidationRuleTest extends Unit
{
    public function testGivenAnExistingSourceWithReasonedNotAnsweredCodesWhenValidatingThenNoErrorIsReported(): void
    {
        // Arrange
        $schema = [
        'errorMappings' => [
            ['source' => FixtureErrorMappingConfig::class . '::getErrorIdentifierToRestErrorMapping', 'notAnswered' => ['118' => 'Only the merge answers it.']],
        ]];

        // Act
        $errors = (new ErrorMappingsValidationRule())->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenAnErrorMappingSourceThatDoesNotExistWhenValidatingTheSchemaThenValidationFails(): void
    {
        // Arrange
        $schema = [
            'sourceFile' => 'carts.resource.yml',
            'errorMappings' => [
                ['source' => FixtureErrorMappingConfig::class . '::getMissingMapping'],
                ['source' => FixtureErrorMappingConfig::class . '::getErrorIdentifierToRestErrorMapping', 'notAnswered' => ['118' => '']],
            ],
        ];

        // Act
        $errors = (new ErrorMappingsValidationRule())->validate($schema);

        // Assert
        $this->assertCount(2, $errors);
        $this->assertStringContainsString('errorMappings entry #0 of carts.resource.yml names', $errors[0]);
        $this->assertStringContainsString('marks code 118 notAnswered without a reason', $errors[1]);
    }
}
