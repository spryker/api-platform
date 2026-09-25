<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\OperationVerifier;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group OperationVerifierTest
 * Add your own group annotations below this line
 */
class OperationVerifierTest extends Unit
{
    public function testGivenEveryDeclaredOperationWasRecordedWhenVerifyingThenNothingIsUnverified(): void
    {
        // Arrange
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('GET', '/wishlists')];
        $recorded = [new ApiOperation('GET', '/wishlists'), new ApiOperation('POST', '/wishlists')];

        // Act
        $unverified = $verifier->findUnverified($declared, $recorded);

        // Assert
        $this->assertSame([], $unverified);
    }

    public function testGivenADeclaredOperationThatWasNeverRecordedWhenVerifyingThenItIsReportedUnverified(): void
    {
        // Arrange
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('POST', '/wishlists')];
        $recorded = [new ApiOperation('GET', '/wishlists')];

        // Act
        $unverified = $verifier->findUnverified($declared, $recorded);

        // Assert
        $this->assertCount(1, $unverified);
        $this->assertSame('POST /wishlists', $unverified[0]->key());
    }

    public function testGivenADeclaredErrorResponseWhenItsOperationWasDispatchedThenItCountsAsVerified(): void
    {
        // Arrange — the recorder observes operations at kernel-request time, so recordings never
        // carry a status; a status-carrying declaration must still verify by dispatch alone.
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('GET', '/wishlists/{uuid}', 404)];
        $recorded = [new ApiOperation('GET', '/wishlists/{uuid}')];

        // Act
        $unverified = $verifier->findUnverified($declared, $recorded);

        // Assert
        $this->assertSame([], $unverified);
    }

    public function testGivenVerbCaseDiffersWhenVerifyingThenTheOperationStillCountsAsRecorded(): void
    {
        // Arrange
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('get', '/wishlists')];
        $recorded = [new ApiOperation('GET', '/wishlists')];

        // Act
        $unverified = $verifier->findUnverified($declared, $recorded);

        // Assert
        $this->assertSame([], $unverified);
    }

    public function testGivenDeclared404WhenOnlyA403WasObservedThenTheDeclarationIsUnverified(): void
    {
        // Arrange — the anti-enumeration case: the stack answers 403, so a 404 claim is a fiction.
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('PATCH', '/customer-password/{customerReference}', 404)];
        $recordedDispatches = [new ApiOperation('PATCH', '/customer-password/{customerReference}')];
        $recordedResponses = [new ApiOperation('PATCH', '/customer-password/{customerReference}', 403)];

        // Act
        $result = $verifier->verify($declared, $recordedDispatches, $recordedResponses, []);

        // Assert
        $this->assertCount(1, $result->unverified);
        $this->assertSame('PATCH /customer-password/{customerReference} 404', $result->unverified[0]->key());
    }

    public function testGivenDeclared404WhenA404WasObservedThenTheDeclarationIsVerified(): void
    {
        // Arrange
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('GET', '/wishlists/{uuid}', 404)];
        $recordedDispatches = [new ApiOperation('GET', '/wishlists/{uuid}')];
        $recordedResponses = [new ApiOperation('GET', '/wishlists/{uuid}', 404)];

        // Act
        $result = $verifier->verify($declared, $recordedDispatches, $recordedResponses, []);

        // Assert
        $this->assertSame([], $result->unverified);
    }

    public function testGivenObservedStatusNotDeclaredInSchemaWhenVerifyingThenItIsReportedAsUndeclaredObservation(): void
    {
        // Arrange
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('PATCH', '/customer-password/{customerReference}', 403)];
        $recordedDispatches = [new ApiOperation('PATCH', '/customer-password/{customerReference}')];
        $recordedResponses = [new ApiOperation('PATCH', '/customer-password/{customerReference}', 403)];
        $declaredResponses = ['PATCH /customer-password/{customerReference}' => [204, 422]];

        // Act
        $result = $verifier->verify($declared, $recordedDispatches, $recordedResponses, $declaredResponses);

        // Assert
        $this->assertCount(1, $result->undeclaredObservations);
        $this->assertSame('PATCH /customer-password/{customerReference} 403', $result->undeclaredObservations[0]->key());
    }

    public function testGivenAnOperationWithNoSchemaEntryWhenVerifyingThenObservationsAreNotJudged(): void
    {
        // Arrange — the gate reports a schema defect for this operation; the test must not also fail.
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('GET', '/stores')];
        $recordedDispatches = [new ApiOperation('GET', '/stores')];
        $recordedResponses = [new ApiOperation('GET', '/stores', 200)];

        // Act
        $result = $verifier->verify($declared, $recordedDispatches, $recordedResponses, []);

        // Assert
        $this->assertSame([], $result->undeclaredObservations);
        $this->assertSame([], $result->unverified);
    }

    public function testGivenStatusNullDeclarationWhenObservedStatusIsADeclaredSuccessThenItIsVerified(): void
    {
        // Arrange
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('GET', '/wishlists')];
        $recordedDispatches = [new ApiOperation('GET', '/wishlists')];
        $recordedResponses = [new ApiOperation('GET', '/wishlists', 200)];
        $declaredResponses = ['GET /wishlists' => [200, 401]];

        // Act
        $result = $verifier->verify($declared, $recordedDispatches, $recordedResponses, $declaredResponses);

        // Assert
        $this->assertSame([], $result->unverified);
        $this->assertSame([], $result->undeclaredObservations);
    }

    public function testGivenStatusNullDeclarationWhenObserved201ButSchemaDeclaresOnly200ThenItIsReportedAsUndeclaredObservation(): void
    {
        // Arrange — the customers-confirm-registration shape: the endpoint answers 201, the schema
        // documents 200, and one of the two has to change.
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('POST', '/customers-confirm-registration')];
        $recordedDispatches = [new ApiOperation('POST', '/customers-confirm-registration')];
        $recordedResponses = [new ApiOperation('POST', '/customers-confirm-registration', 201)];
        $declaredResponses = ['POST /customers-confirm-registration' => [200, 422]];

        // Act
        $result = $verifier->verify($declared, $recordedDispatches, $recordedResponses, $declaredResponses);

        // Assert
        $this->assertCount(1, $result->undeclaredObservations);
        $this->assertSame('POST /customers-confirm-registration 201', $result->undeclaredObservations[0]->key());
        $this->assertCount(1, $result->unverified);
    }

    public function testGivenAStatusNullDeclarationWhenTheOnlyObservedStatusIsAnErrorThenItIsUnverified(): void
    {
        // Arrange — a test that only ever got a 401 has not covered the success path.
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('GET', '/wishlists')];
        $recordedDispatches = [new ApiOperation('GET', '/wishlists')];
        $recordedResponses = [new ApiOperation('GET', '/wishlists', 401)];
        $declaredResponses = ['GET /wishlists' => [200, 401]];

        // Act
        $result = $verifier->verify($declared, $recordedDispatches, $recordedResponses, $declaredResponses);

        // Assert
        $this->assertCount(1, $result->unverified);
        $this->assertSame([], $result->undeclaredObservations);
    }

    public function testGivenAnObservationOnAnOperationWhenTheTestNeverDeclaredItThenItIsNotJudged(): void
    {
        // Arrange — helper requests (logins, fixtures) run through other operations; only the
        // operations the test claims are its contract.
        $verifier = new OperationVerifier();
        $declared = [new ApiOperation('GET', '/wishlists')];
        $recordedDispatches = [new ApiOperation('GET', '/wishlists'), new ApiOperation('POST', '/access-tokens')];
        $recordedResponses = [
            new ApiOperation('GET', '/wishlists', 200),
            new ApiOperation('POST', '/access-tokens', 201),
        ];
        $declaredResponses = ['GET /wishlists' => [200], 'POST /access-tokens' => [200]];

        // Act
        $result = $verifier->verify($declared, $recordedDispatches, $recordedResponses, $declaredResponses);

        // Assert
        $this->assertSame([], $result->undeclaredObservations);
    }
}
