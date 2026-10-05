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
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;
use Spryker\ApiPlatform\Contract\Coverage\RecordedExchange;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Test
 * @group AbstractApiTestCaseVerificationTest
 * Add your own group annotations below this line
 */
class AbstractApiTestCaseVerificationTest extends Unit
{
    protected const string CART_CODE_REMOVAL_DISPATCH_KEY = 'DELETE /carts/{cartUuid}/cart-codes/{code}';

    protected const string CART_UPDATE_DISPATCH_KEY = 'PATCH /carts/{cartUuid}';

    public function testGivenErrorCodesEnforcedWhenAResponseCarriesAnUndeclaredCodeThenTheTestFails(): void
    {
        // Arrange
        $probe = $this->createCartCodeRemovalProbe(ContractCoverageEnforcement::all());
        $probe->recordExchange($this->createCartCodeRemovalExchange(['3301', '3399']));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertStringContainsString('observed DELETE /carts/{cartUuid}/cart-codes/{code} 422 code 3399, which the resource schema does not declare', (string)$failure);
    }

    public function testGivenErrorCodesNotEnforcedWhenAResponseCarriesAnUndeclaredCodeThenTheTestPasses(): void
    {
        // Arrange
        $probe = $this->createCartCodeRemovalProbe(ContractCoverageEnforcement::none());
        $probe->recordExchange($this->createCartCodeRemovalExchange(['3301', '3399']));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertNull($failure);
    }

    public function testGivenACodeClaimWhenTheResponseCarriedTheCodeThenTheTestPasses(): void
    {
        // Arrange
        $probe = $this->createCartCodeRemovalProbe(ContractCoverageEnforcement::all());
        $probe->recordExchange($this->createCartCodeRemovalExchange(['3301']));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertNull($failure);
    }

    public function testGivenACodeClaimWhenNoResponseCarriedTheCodeThenTheTestFails(): void
    {
        // Arrange
        $probe = $this->createCartCodeRemovalProbe(ContractCoverageEnforcement::none());
        $probe->recordExchange($this->createCartCodeRemovalExchange([]));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertStringContainsString('were never produced', (string)$failure);
    }

    public function testGivenAnIncludesClaimWhenTheIncludeWasRequestedAndAssertedThenTheTestPasses(): void
    {
        // Arrange
        $probe = $this->createProbe('probeCartReadWithVouchers', ContractCoverageEnforcement::none());
        $probe->recordExchange(new RecordedExchange(new ApiOperation('GET', '/carts/{cartUuid}'), 200, includeRelationshipNames: ['vouchers']));
        $probe->recordAssertedRelationship('vouchers');

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertNull($failure);
    }

    public function testGivenAnIncludesClaimWhenTheIncludeWasRequestedButNeverAssertedThenTheTestFailsEvenUnenforced(): void
    {
        // Arrange
        $probe = $this->createProbe('probeCartReadWithVouchers', ContractCoverageEnforcement::none());
        $probe->recordExchange(new RecordedExchange(new ApiOperation('GET', '/carts/{cartUuid}'), 200, includeRelationshipNames: ['vouchers']));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertStringContainsString('assertIncludedRelationship() never asserted vouchers', (string)$failure);
    }

    public function testGivenABareRequestAttributesClaimWhenASchemaPathWasNotSentThenTheTestFailsNamingIt(): void
    {
        // Arrange
        $probe = $this->createCartUpdateProbe('probeCartUpdateOfEveryAttribute');
        $probe->recordExchange($this->createCartUpdateExchange(['currency' => 'EUR']));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertStringContainsString('no successful request sent: PATCH /carts/{cartUuid}: priceMode', (string)$failure);
    }

    public function testGivenABareRequestAttributesClaimWhenEverySchemaPathWasSentThenTheTestPasses(): void
    {
        // Arrange
        $probe = $this->createCartUpdateProbe('probeCartUpdateOfEveryAttribute');
        $probe->recordExchange($this->createCartUpdateExchange(['currency' => 'EUR', 'priceMode' => 'GROSS_MODE']));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertNull($failure);
    }

    public function testGivenARequestAttributesClaimNamingAPathWhenOnlyThatPathWasSentThenTheTestPasses(): void
    {
        // Arrange
        $probe = $this->createCartUpdateProbe('probeCartUpdateOfCurrency');
        $probe->recordExchange($this->createCartUpdateExchange(['currency' => 'EUR']));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertNull($failure);
    }

    public function testGivenValidationEvidenceEnforcedWhenNoResponseProvedAnUnbaselinedValidationThenTheTestFails(): void
    {
        // Arrange
        $probe = $this->createProbe('probeAddressValidation', ContractCoverageEnforcement::all());
        $probe->recordExchange(new RecordedExchange(new ApiOperation('POST', '/customers/{customerReference}/addresses'), 422));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertStringContainsString('declares validations no response proved', (string)$failure);
    }

    public function testGivenValidationEvidenceNotEnforcedWhenNoResponseProvedAValidationThenTheTestPasses(): void
    {
        // Arrange
        $probe = $this->createProbe('probeAddressValidation', ContractCoverageEnforcement::none());
        $probe->recordExchange(new RecordedExchange(new ApiOperation('POST', '/customers/{customerReference}/addresses'), 422));

        // Act
        $failure = $probe->verifyPostConditions();

        // Assert
        $this->assertNull($failure);
    }

    protected function createCartCodeRemovalProbe(ContractCoverageEnforcement $enforcement): ContractCoverageBaselineProbe
    {
        $probe = $this->createProbe('probeCartCodeRemovalError', $enforcement);
        $probe->declaredErrorCodes = [static::CART_CODE_REMOVAL_DISPATCH_KEY => [422 => ['3301']]];

        return $probe;
    }

    protected function createCartUpdateProbe(string $methodName): ContractCoverageBaselineProbe
    {
        $probe = $this->createProbe($methodName, ContractCoverageEnforcement::none());
        $probe->requestAttributePaths = [static::CART_UPDATE_DISPATCH_KEY => ['currency', 'priceMode']];

        return $probe;
    }

    /**
     * @param array<string> $errorCodes
     */
    protected function createCartCodeRemovalExchange(array $errorCodes): RecordedExchange
    {
        return new RecordedExchange(new ApiOperation('DELETE', '/carts/{cartUuid}/cart-codes/{code}'), 422, errorCodes: $errorCodes);
    }

    /**
     * @param array<string, mixed> $requestAttributes
     */
    protected function createCartUpdateExchange(array $requestAttributes): RecordedExchange
    {
        return new RecordedExchange(new ApiOperation('PATCH', '/carts/{cartUuid}'), 200, requestAttributes: $requestAttributes);
    }

    protected function createProbe(string $methodName, ContractCoverageEnforcement $enforcement): ContractCoverageBaselineProbe
    {
        $probe = new ContractCoverageBaselineProbe('probe');
        $probe->baseline = ContractCoverageBaseline::none();
        $probe->enforcement = $enforcement;
        $probe->armFor($methodName);

        return $probe;
    }
}
