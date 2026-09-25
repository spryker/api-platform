<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use PHPUnit\Framework\AssertionFailedError;
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
 * @group ResponseAttributeAssertionsTest
 * Add your own group annotations below this line
 */
class ResponseAttributeAssertionsTest extends Unit
{
    use JsonApiResponseAssertionsTrait;

    protected ?ResponseAttributeRecorder $responseAttributeRecorder = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->responseAttributeRecorder = new ResponseAttributeRecorder();
    }

    public function testGivenACollectionDocumentWhenAssertingAMemberAttributeThenItMatchesAndIsRecorded(): void
    {
        // Arrange
        $response = $this->createResponse([
            'data' => [
                [
                    'type' => 'customerCollection',
                    'id' => '1',
                    'attributes' => [
                        'customers' => [
                            ['firstName' => 'Sonia'],
                            ['firstName' => 'Max'],
                        ],
                    ],
                ],
            ],
        ]);

        // Act
        $this->assertResponseAttributes($response, ['customers[0].firstName' => 'Sonia']);

        // Assert
        $this->assertSame([], $this->responseAttributeRecorder?->verify(['customers[].firstName']));
    }

    public function testGivenAnItemDocumentWhenTheExpectedValueDiffersThenTheFailureNamesThePath(): void
    {
        // Arrange
        $response = $this->createResponse([
            'data' => [
                'type' => 'customers',
                'id' => '1',
                'attributes' => ['firstName' => 'Sonia'],
            ],
        ]);

        // Act
        $failureMessage = $this->captureFailureMessage(function () use ($response): void {
            $this->assertResponseAttributes($response, ['firstName' => 'Max']);
        });

        // Assert
        $this->assertNotNull($failureMessage, 'A mismatching attribute value must fail the assertion.');
        $this->assertStringContainsString('firstName: expected "Max", got "Sonia"', $failureMessage);
    }

    public function testGivenAnItemDocumentWhenTheAttributeIsAbsentThenTheFailureNamesTheMissingSegment(): void
    {
        // Arrange
        $response = $this->createResponse([
            'data' => [
                'type' => 'customers',
                'id' => '1',
                'attributes' => ['firstName' => 'Sonia'],
            ],
        ]);

        // Act
        $failureMessage = $this->captureFailureMessage(function () use ($response): void {
            $this->assertResponseAttributesPresent($response, ['lastName']);
        });

        // Assert
        $this->assertNotNull($failureMessage, 'An absent attribute must fail the assertion.');
        $this->assertStringContainsString('segment "lastName" is missing from the response', $failureMessage);
    }

    public function testGivenAWildcardTruthPathWhenAssertingThenTheFailureNamesTheConcreteFormToUse(): void
    {
        // Arrange — the attribute the path names is present; only the path form is a truth path.
        $response = $this->createResponse([
            'data' => [
                'type' => 'wishlists',
                'id' => '1',
                'attributes' => [
                    'lines' => [
                        ['sku' => '001'],
                    ],
                ],
            ],
        ]);

        // Act — the obvious move after a runtime failure: copy the reported path into the assertion.
        $failureMessage = $this->captureFailureMessage(function () use ($response): void {
            $this->assertResponseAttributesPresent($response, ['lines[].sku']);
        });

        // Assert — a "segment is missing" verdict would send the author looking for an attribute
        // that is right there, so the wildcard has to be rejected by name instead.
        $this->assertNotNull($failureMessage, 'A wildcard path must fail the assertion.');
        $this->assertStringContainsString(
            '"lines[].sku" is a truth path; assert a concrete member, e.g. lines[0].sku',
            $failureMessage,
        );
        $this->assertStringNotContainsString('is missing from the response', $failureMessage);
    }

    public function testGivenAWriteThatAnswersItsParentWhenAssertingAnIncludedAttributeThenItMatchesAndIsRecorded(): void
    {
        // Arrange
        $response = $this->createResponse($this->buildCompoundDocument());

        // Act
        $this->assertIncludedResourceAttributes($response, 'items', [
            'sku' => 'SKU-1',
            'calculations.unitPrice' => 888,
        ]);

        // Assert
        $this->assertSame([], $this->responseAttributeRecorder?->verify(['sku', 'calculations.unitPrice']));
    }

    /**
     * The included bag holds more than one type, and more than one resource of the asserted type,
     * so the selector has to pick within the filtered members rather than within the bag.
     */
    public function testGivenSeveralIncludedResourcesWhenAssertingAMemberByIndexThenItReadsTheNamedOne(): void
    {
        // Arrange
        $response = $this->createResponse($this->buildCompoundDocument());

        // Act
        $this->assertIncludedResourceAttributes($response, 'items', ['[1].sku' => 'SKU-2']);

        // Assert
        $this->assertSame([], $this->responseAttributeRecorder?->verify(['sku']));
    }

    public function testGivenNoIncludedResourceOfThatTypeWhenAssertingThenTheFailureNamesTheType(): void
    {
        // Arrange
        $response = $this->createResponse($this->buildCompoundDocument());

        // Act
        $failureMessage = $this->captureFailureMessage(function () use ($response): void {
            $this->assertIncludedResourceAttributes($response, 'gift-cards', ['code' => 'SUMMER']);
        });

        // Assert
        $this->assertStringContainsString('no included "gift-cards" resource', (string)$failureMessage);
    }

    public function testGivenANullIncludedAttributeWhenAssertingItIsPresentThenTheFailureNamesThePath(): void
    {
        // Arrange
        $response = $this->createResponse($this->buildCompoundDocument());

        // Act
        $failureMessage = $this->captureFailureMessage(function () use ($response): void {
            $this->assertIncludedResourceAttributesPresent($response, 'items', ['merchantReference']);
        });

        // Assert
        $this->assertStringContainsString('items merchantReference is null', (string)$failureMessage);
    }

    /**
     * A cart write as the resources answer it: the cart in `data`, its items in `included`, and a
     * resource of another type beside them.
     *
     * @return array<string, mixed>
     */
    protected function buildCompoundDocument(): array
    {
        return [
            'data' => [
                'type' => 'carts',
                'id' => 'cart-uuid',
                'attributes' => ['name' => 'Shopping cart'],
            ],
            'included' => [
                [
                    'type' => 'vouchers',
                    'id' => 'voucher-1',
                    'attributes' => ['code' => 'WINTER'],
                ],
                [
                    'type' => 'items',
                    'id' => 'group-key-1',
                    'attributes' => [
                        'sku' => 'SKU-1',
                        'merchantReference' => null,
                        'calculations' => ['unitPrice' => 888],
                    ],
                ],
                [
                    'type' => 'items',
                    'id' => 'group-key-2',
                    'attributes' => ['sku' => 'SKU-2'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    protected function createResponse(array $document): Response
    {
        return new Response((string)json_encode($document, JSON_THROW_ON_ERROR));
    }

    /**
     * The message the assertion failed with, or null when it did not fail. Narrowed to an assertion
     * failure so any other throwable reaches the runner instead of being read as the expected one.
     *
     * @param callable(): void $assertion
     */
    protected function captureFailureMessage(callable $assertion): ?string
    {
        try {
            $assertion();
        } catch (AssertionFailedError $assertionFailedError) {
            return $assertionFailedError->getMessage();
        }

        return null;
    }
}
