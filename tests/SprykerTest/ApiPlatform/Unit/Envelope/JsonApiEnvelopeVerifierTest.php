<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Envelope;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Envelope\JsonApiEnvelopeVerifier;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Envelope
 * @group JsonApiEnvelopeVerifierTest
 * Add your own group annotations below this line
 */
class JsonApiEnvelopeVerifierTest extends Unit
{
    protected const string VERB_GET = 'GET';

    protected const string VERB_POST = 'POST';

    protected const string URI_TEMPLATE = '/wishlists/{uuid}';

    protected const string RESOURCE_SHORT_NAME = 'wishlists';

    /**
     * @var array<string>
     */
    protected const array SCHEMA_RESOURCE_SHORT_NAMES = ['wishlists', 'wishlist-items', 'carts'];

    /**
     * `carts` is deliberately absent: a resource whose schema declares no identifier may answer
     * without one, which is the exemption the identifier check derives rather than hard-codes.
     *
     * @var array<string>
     */
    protected const array IDENTIFIER_DECLARING_RESOURCE_SHORT_NAMES = ['wishlists', 'wishlist-items'];

    protected const string MEDIA_TYPE_JSON_API = 'application/vnd.api+json';

    protected const int STATUS_OK = 200;

    protected const int STATUS_NO_CONTENT = 204;

    protected const int STATUS_NOT_FOUND = 404;

    public function testGivenACompleteItemDocumentWhenVerifyingThenItReportsNoViolation(): void
    {
        // Arrange
        $body = $this->encode([
            'links' => ['self' => '/wishlists/abc'],
            'data' => [
                'id' => 'abc',
                'type' => 'wishlists',
                'links' => ['self' => '/wishlists/abc'],
            ],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenACompleteCollectionDocumentWhenVerifyingThenItReportsNoViolation(): void
    {
        // Arrange
        $body = $this->encode([
            'links' => ['self' => '/wishlists'],
            'data' => [
                ['id' => 'a', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/a']],
                ['id' => 'b', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/b']],
            ],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenAnEmptyCollectionWhenVerifyingThenItReportsNoViolation(): void
    {
        // Arrange
        $body = $this->encode(['links' => ['self' => '/wishlists'], 'data' => []]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenAnotherResourceTypeInDataWhenVerifyingThenItReportsTheMismatch(): void
    {
        // Arrange
        $body = $this->encode([
            'links' => ['self' => '/wishlists/abc'],
            'data' => ['id' => 'abc', 'type' => 'wishlist-items', 'links' => ['self' => '/wishlists/abc']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('data.type "wishlist-items"', $violations[0]);
    }

    public function testGivenADataElementWithoutASelfLinkWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'links' => ['self' => '/wishlists'],
            'data' => [['id' => 'a', 'type' => 'wishlists']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered data[0] with no "links.self".'],
            $violations,
        );
    }

    public function testGivenADataElementWithoutATypeWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'links' => ['self' => '/wishlists/abc'],
            'data' => ['id' => 'abc', 'links' => ['self' => '/wishlists/abc']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered data with no "type".'],
            $violations,
        );
    }

    /**
     * Write responses and some item GETs answer without one, so the document-level link is asserted
     * per response rather than demanded of every response.
     */
    public function testGivenADocumentWithoutASelfLinkWhenVerifyingThenItReportsNothing(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => 'abc', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenADocumentWithoutADataMemberWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode(['meta' => ['totalItems' => 0]]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered a document with no "data" member.'],
            $violations,
        );
    }

    public function testGivenAnIncludedElementWithoutASelfLinkWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'links' => ['self' => '/wishlists/abc'],
            'data' => ['id' => 'abc', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
            'included' => [['id' => 'i', 'type' => 'wishlist-items']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered included[0] with no "links.self".'],
            $violations,
        );
    }

    public function testGivenTheWrongMediaTypeWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'links' => ['self' => '/wishlists/abc'],
            'data' => ['id' => 'abc', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
        ]);

        // Act
        $violations = $this->verifyAs(static::VERB_GET, static::STATUS_OK, 'application/json', $body);

        // Assert
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('media type "application/json"', $violations[0]);
    }

    public function testGivenACharsetSuffixOnTheMediaTypeWhenVerifyingThenItAcceptsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'links' => ['self' => '/wishlists/abc'],
            'data' => ['id' => 'abc', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
        ]);

        // Act
        $violations = $this->verifyAs(static::VERB_GET, static::STATUS_OK, static::MEDIA_TYPE_JSON_API . '; charset=utf-8', $body);

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenAnUndecodableBodyWhenVerifyingThenItReportsItRatherThanSkipping(): void
    {
        // Act
        $violations = $this->verify('<html>gateway timeout</html>');

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered 200 with a body that is not a JSON document.'],
            $violations,
        );
    }

    public function testGivenAnErrorResponseWhenVerifyingThenItReportsNothing(): void
    {
        // Act
        $violations = $this->verifyAs(static::VERB_GET, static::STATUS_NOT_FOUND, 'application/problem+json', '{"errors":[]}');

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenANoContentResponseWithoutABodyWhenVerifyingThenItReportsNothing(): void
    {
        // Act
        $violations = $this->verifyAs(static::VERB_GET, static::STATUS_NO_CONTENT, null, '');

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenANoContentResponseCarryingABodyWhenVerifyingThenItReportsIt(): void
    {
        // Act
        $violations = $this->verifyAs(static::VERB_POST, static::STATUS_NO_CONTENT, static::MEDIA_TYPE_JSON_API, '{"data":null}');

        // Assert
        $this->assertSame(
            ['POST /wishlists/{uuid} answered 204 with a body; a no-content response carries none.'],
            $violations,
        );
    }

    /**
     * Adding a cart item answers the cart, which is the contract, so a write is held to naming some
     * resource the schema defines rather than the one its operation is declared on.
     */
    public function testGivenAWriteAnsweringAnotherDeclaredResourceWhenVerifyingThenItReportsNothing(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => 'abc', 'type' => 'carts', 'links' => ['self' => '/carts/abc']],
        ]);

        // Act
        $violations = $this->verifyAs(static::VERB_POST, static::STATUS_OK, static::MEDIA_TYPE_JSON_API, $body);

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenAWriteAnsweringATypeNoResourceDefinesWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => 'abc', 'type' => 'not-a-resource', 'links' => ['self' => '/carts/abc']],
        ]);

        // Act
        $violations = $this->verifyAs(static::VERB_POST, static::STATUS_OK, static::MEDIA_TYPE_JSON_API, $body);

        // Assert
        $this->assertSame(
            ['POST /wishlists/{uuid} answered data.type "not-a-resource", which no generated resource defines.'],
            $violations,
        );
    }

    public function testGivenAResourceObjectWithoutAnIdentifierWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered data with no "id"; the "wishlists" resource declares an identifier.'],
            $violations,
        );
    }

    public function testGivenAnEmptyStringIdentifierWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => '', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered data with no "id"; the "wishlists" resource declares an identifier.'],
            $violations,
        );
    }

    public function testGivenACollectionMemberWithoutAnIdentifierWhenVerifyingThenTheMessageNamesItsPosition(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => [
                ['id' => 'a', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/a']],
                ['type' => 'wishlists', 'links' => ['self' => '/wishlists/b']],
            ],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered data[1] with no "id"; the "wishlists" resource declares an identifier.'],
            $violations,
        );
    }

    /**
     * A resource whose schema declares an identifier owes one on the wire; answering `null` is the
     * shape the check exists to catch, not an accepted way of saying "this resource has no id".
     */
    public function testGivenANullIdentifierOnAResourceDeclaringOneWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => null, 'type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered data with no "id"; the "wishlists" resource declares an identifier.'],
            $violations,
        );
    }

    /**
     * The exemption is read from schema truth: a resource declaring no identifier is outside the
     * guarantee, so a null one is not a violation.
     */
    public function testGivenANullIdentifierOnAResourceDeclaringNoneWhenVerifyingThenItReportsNothing(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => null, 'type' => 'carts', 'links' => ['self' => '/carts']],
        ]);

        // Act
        $violations = $this->verifyAs(static::VERB_POST, static::STATUS_OK, static::MEDIA_TYPE_JSON_API, $body);

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenAResourceDeclaringNoIdentifierAndOmittingItWhenVerifyingThenItReportsNothing(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['type' => 'carts', 'links' => ['self' => '/carts']],
        ]);

        // Act
        $violations = $this->verifyAs(static::VERB_POST, static::STATUS_OK, static::MEDIA_TYPE_JSON_API, $body);

        // Assert
        $this->assertSame([], $violations);
    }

    public function testGivenAnIncludedResourceObjectWithoutAnIdentifierWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => 'abc', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
            'included' => [
                ['id' => 'i', 'type' => 'wishlist-items', 'links' => ['self' => '/wishlist-items/i']],
                ['type' => 'wishlist-items', 'links' => ['self' => '/wishlist-items/j']],
            ],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered included[1] with no "id"; the "wishlist-items" resource declares an identifier.'],
            $violations,
        );
    }

    /**
     * An included resource object is judged by its own `type`, never by the type the operation
     * serves, so the exemption has to be looked up per resource object.
     */
    public function testGivenAnIncludedResourceObjectDeclaringNoIdentifierWhenVerifyingThenItReportsNothing(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => 'abc', 'type' => 'wishlists', 'links' => ['self' => '/wishlists/abc']],
            'included' => [['id' => null, 'type' => 'carts', 'links' => ['self' => '/carts']]],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame([], $violations);
    }

    /**
     * Every identifier this codebase emits passes through a string cast, so a JSON number is a
     * defect rather than a shape to tolerate.
     */
    public function testGivenANumericIdentifierWhenVerifyingThenItReportsIt(): void
    {
        // Arrange
        $body = $this->encode([
            'data' => ['id' => 42, 'type' => 'wishlists', 'links' => ['self' => '/wishlists/42']],
        ]);

        // Act
        $violations = $this->verify($body);

        // Assert
        $this->assertSame(
            ['GET /wishlists/{uuid} answered data with no "id"; the "wishlists" resource declares an identifier.'],
            $violations,
        );
    }

    /**
     * @return array<string>
     */
    protected function verify(string $body): array
    {
        return $this->verifyAs(static::VERB_GET, static::STATUS_OK, static::MEDIA_TYPE_JSON_API, $body);
    }

    /**
     * @return array<string>
     */
    protected function verifyAs(string $verb, int $status, ?string $mediaType, string $body): array
    {
        return (new JsonApiEnvelopeVerifier())->verify(
            new ApiOperation($verb, static::URI_TEMPLATE),
            $this->buildResponse($status, $mediaType, $body),
            static::RESOURCE_SHORT_NAME,
            static::SCHEMA_RESOURCE_SHORT_NAMES,
            static::IDENTIFIER_DECLARING_RESOURCE_SHORT_NAMES,
        );
    }

    protected function buildResponse(int $status, ?string $mediaType, string $body): Response
    {
        $response = new Response($body, $status);
        $response->headers->remove('Content-Type');

        if ($mediaType !== null) {
            $response->headers->set('Content-Type', $mediaType);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $document
     */
    protected function encode(array $document): string
    {
        return (string)json_encode($document);
    }
}
