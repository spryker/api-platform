<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use PHPUnit\Exception as PHPUnitException;
use Spryker\ApiPlatform\Contract\Coverage\IncludedRelationshipRecorder;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeRecorder;
use SprykerTest\ApiPlatform\Test\JsonApiResponseAssertionsTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group IncludedRelationshipAssertionsTest
 * Add your own group annotations below this line
 */
class IncludedRelationshipAssertionsTest extends Unit
{
    use JsonApiResponseAssertionsTrait;

    protected ?ResponseAttributeRecorder $responseAttributeRecorder = null;

    protected ?IncludedRelationshipRecorder $includedRelationshipRecorder = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->includedRelationshipRecorder = new IncludedRelationshipRecorder();
    }

    public function testGivenACompoundDocumentWhenAssertingTheIncludedRelationshipThenItPassesAndRecordsTheName(): void
    {
        // Arrange
        $response = $this->createResponse([
            'data' => ['type' => 'carts', 'id' => 'c1', 'relationships' => ['vouchers' => ['data' => [['type' => 'vouchers', 'id' => 'SUMMER']]]]],
            'included' => [['type' => 'vouchers', 'id' => 'SUMMER', 'attributes' => []]],
        ]);

        // Act
        $this->assertIncludedRelationship($response, 'vouchers', ['SUMMER']);

        // Assert
        $this->assertSame(['vouchers'], $this->includedRelationshipRecorder?->assertedRelationshipNames());
    }

    public function testGivenARelationshipWhoseMemberIsMissingFromIncludedWhenAssertingThenItFails(): void
    {
        // Arrange
        $response = $this->createResponse([
            'data' => ['type' => 'carts', 'id' => 'c1', 'relationships' => ['vouchers' => ['data' => [['type' => 'vouchers', 'id' => 'SUMMER']]]]],
            'included' => [],
        ]);

        // Act
        $failureMessage = $this->captureFailureMessage(fn () => $this->assertIncludedRelationship($response, 'vouchers', ['SUMMER']));

        // Assert
        $this->assertStringContainsString('vouchers "SUMMER" is referenced by relationships.vouchers but missing from included', (string)$failureMessage);
        $this->assertSame([], $this->includedRelationshipRecorder?->assertedRelationshipNames());
    }

    public function testGivenAnEmptyRelationshipWhenAssertingThenItFails(): void
    {
        // Arrange
        $response = $this->createResponse(['data' => ['type' => 'carts', 'id' => 'c1', 'relationships' => ['vouchers' => ['data' => []]]]]);

        // Act
        $failureMessage = $this->captureFailureMessage(fn () => $this->assertIncludedRelationship($response, 'vouchers', []));

        // Assert
        $this->assertStringContainsString('An include is proven by at least one included resource', (string)$failureMessage);
    }

    /**
     * @param array<string, mixed> $document
     */
    protected function createResponse(array $document): Response
    {
        return new Response((string)json_encode($document, JSON_THROW_ON_ERROR));
    }

    /**
     * @param callable(): void $assertion
     */
    protected function captureFailureMessage(callable $assertion): ?string
    {
        try {
            $assertion();
        } catch (PHPUnitException $assertionFailure) {
            return $assertionFailure->getMessage();
        }

        return null;
    }
}
