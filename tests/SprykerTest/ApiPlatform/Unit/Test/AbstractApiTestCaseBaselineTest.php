<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Test;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageBaseline;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;
use Spryker\ApiPlatform\Contract\Coverage\RecordedConstraintViolation;
use Spryker\ApiPlatform\Contract\Coverage\RecordedExchange;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Test
 * @group AbstractApiTestCaseBaselineTest
 * Add your own group annotations below this line
 */
class AbstractApiTestCaseBaselineTest extends Unit
{
    protected const string ARRAY_PATH = 'items[].calculatedDiscounts';

    protected const string ASSERTED_ARRAY_PATH = 'items[0].calculatedDiscounts';

    protected const string ARRAY_ITEM_KEY = 'GET /orders/{orderReference}  items[].calculatedDiscounts';

    protected const string ARRAY_REASON = 'Order items never carry their calculated discounts.';

    protected const string ELEMENT_PATH = 'discounts[].code';

    protected const string ASSERTED_ELEMENT_ARRAY_PATH = 'discounts';

    protected const string ASSERTED_ELEMENT_PATH = 'discounts[0].code';

    protected const string ELEMENT_ARRAY_ITEM_KEY = 'GET /orders/{orderReference}  discounts';

    protected const string ELEMENT_ARRAY_REASON = 'An order read never carries its discounts.';

    protected const string ELEMENT_CODE = 'SUMMER';

    protected const string VALIDATION_ITEM_KEY = 'customer-addresses.iso2Code.Length.max on POST /customers/{customerReference}/addresses';

    protected const string VALIDATION_REASON = 'An over-long country code is cut instead of rejected.';

    public function testGivenAPathTheBaselineListsAsAlwaysEmptyWhenOnlyAnEmptyValueWasAssertedThenTheTestPasses(): void
    {
        // Arrange
        $probe = $this->createOrderReadProbe([ContractCoverageDimension::NON_EMPTY_ARRAYS->value => [static::ARRAY_ITEM_KEY => static::ARRAY_REASON]]);
        $probe->recordAssertedValue(static::ASSERTED_ARRAY_PATH, []);

        // Act
        $failure = $probe->verifyResponseAttributes();

        // Assert
        $this->assertNull($failure);
    }

    public function testGivenAPathTheBaselineDoesNotListWhenOnlyAnEmptyValueWasAssertedThenTheTestFails(): void
    {
        // Arrange
        $probe = $this->createOrderReadProbe([]);
        $probe->recordAssertedValue(static::ASSERTED_ARRAY_PATH, []);

        // Act
        $failure = $probe->verifyResponseAttributes();

        // Assert
        $this->assertStringContainsString('Asserted only as an empty value', (string)$failure);
    }

    public function testGivenAPathTheBaselineListsAsAlwaysEmptyWhenAValueWasAssertedThenTheTestFailsAskingToRemoveTheEntry(): void
    {
        // Arrange
        $probe = $this->createOrderReadProbe([ContractCoverageDimension::NON_EMPTY_ARRAYS->value => [static::ARRAY_ITEM_KEY => static::ARRAY_REASON]]);
        $probe->recordAssertedValue(static::ASSERTED_ARRAY_PATH, [['sumAmount' => 1]]);

        // Act
        $failure = $probe->verifyResponseAttributes();

        // Assert
        $this->assertStringContainsString('remove the entry from the baseline', (string)$failure);
    }

    public function testGivenAnArrayTheBaselineListsAsAlwaysEmptyWhenOnlyAnEmptyListWasAssertedThenItsElementPathsAreNotOwed(): void
    {
        // Arrange
        $probe = $this->createOrderReadWithElementPathProbe([ContractCoverageDimension::NON_EMPTY_ARRAYS->value => [static::ELEMENT_ARRAY_ITEM_KEY => static::ELEMENT_ARRAY_REASON]]);
        $probe->recordAssertedValue(static::ASSERTED_ELEMENT_ARRAY_PATH, []);

        // Act
        $failure = $probe->verifyResponseAttributes();

        // Assert
        $this->assertNull($failure);
    }

    public function testGivenAnArrayTheBaselineDoesNotListWhenOnlyAnEmptyListWasAssertedThenItsElementPathsAreOwed(): void
    {
        // Arrange
        $probe = $this->createOrderReadWithElementPathProbe([]);
        $probe->recordAssertedValue(static::ASSERTED_ELEMENT_ARRAY_PATH, []);

        // Act
        $failure = $probe->verifyResponseAttributes();

        // Assert
        $this->assertStringContainsString(sprintf('never asserted: %s', static::ELEMENT_PATH), (string)$failure);
    }

    public function testGivenAnArrayTheBaselineListsAsAlwaysEmptyWhenAnElementWasAssertedThenTheTestFailsAskingToRemoveTheEntry(): void
    {
        // Arrange
        $probe = $this->createOrderReadWithElementPathProbe([ContractCoverageDimension::NON_EMPTY_ARRAYS->value => [static::ELEMENT_ARRAY_ITEM_KEY => static::ELEMENT_ARRAY_REASON]]);
        $probe->recordAssertedValue(static::ASSERTED_ELEMENT_PATH, static::ELEMENT_CODE);

        // Act
        $failure = $probe->verifyResponseAttributes();

        // Assert
        $this->assertStringContainsString(static::ELEMENT_ARRAY_ITEM_KEY, (string)$failure);
        $this->assertStringContainsString('remove the entry from the baseline', (string)$failure);
    }

    public function testGivenABaselinedValidationWhenNoResponseProvedItThenTheEnforcedCheckPasses(): void
    {
        // Arrange
        $probe = $this->createAddressValidationProbe([ContractCoverageDimension::VALIDATION_EVIDENCE->value => [static::VALIDATION_ITEM_KEY => static::VALIDATION_REASON]]);

        // Act
        $failure = $probe->verifyValidations(true);

        // Assert
        $this->assertNull($failure);
    }

    public function testGivenABaselinedValidationWhenAResponseProvesItThenTheTestFailsAskingToRemoveTheEntryEvenUnenforced(): void
    {
        // Arrange
        $probe = $this->createAddressValidationProbe([ContractCoverageDimension::VALIDATION_EVIDENCE->value => [static::VALIDATION_ITEM_KEY => static::VALIDATION_REASON]]);
        $probe->recordExchange(new RecordedExchange(
            new ApiOperation('POST', '/customers/{customerReference}/addresses'),
            422,
            constraintViolations: [new RecordedConstraintViolation('iso2Code', 'Length.max')],
        ));

        // Act
        $failure = $probe->verifyValidations(false);

        // Assert
        $this->assertStringContainsString('remove the entry from the baseline', (string)$failure);
    }

    /**
     * @param array<string, array<string, string>> $baselineConfiguration
     */
    protected function createOrderReadProbe(array $baselineConfiguration): ContractCoverageBaselineProbe
    {
        $probe = $this->createProbe($baselineConfiguration, 'probeOrderRead');
        $probe->responseAttributePaths = [static::ARRAY_PATH];

        return $probe;
    }

    /**
     * @param array<string, array<string, string>> $baselineConfiguration
     */
    protected function createOrderReadWithElementPathProbe(array $baselineConfiguration): ContractCoverageBaselineProbe
    {
        $probe = $this->createProbe($baselineConfiguration, 'probeOrderRead');
        $probe->responseAttributePaths = [static::ELEMENT_PATH];

        return $probe;
    }

    /**
     * @param array<string, array<string, string>> $baselineConfiguration
     */
    protected function createAddressValidationProbe(array $baselineConfiguration): ContractCoverageBaselineProbe
    {
        return $this->createProbe($baselineConfiguration, 'probeAddressValidation');
    }

    /**
     * @param array<string, array<string, string>> $baselineConfiguration
     */
    protected function createProbe(array $baselineConfiguration, string $methodName): ContractCoverageBaselineProbe
    {
        $probe = new ContractCoverageBaselineProbe('probe');
        $probe->baseline = ContractCoverageBaseline::fromConfiguration($baselineConfiguration);
        $probe->enforcement = ContractCoverageEnforcement::all();
        $probe->armFor($methodName);

        return $probe;
    }
}
