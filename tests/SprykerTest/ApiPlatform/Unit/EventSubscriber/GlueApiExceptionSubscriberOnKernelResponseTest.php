<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Codeception\Test\Unit;
use ReflectionMethod;
use Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\ApiPlatform\Validation\NestedObjectValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\SynthesizedViolation;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group GlueApiExceptionSubscriberOnKernelResponseTest
 * Add your own group annotations below this line
 */
class GlueApiExceptionSubscriberOnKernelResponseTest extends Unit
{
    protected ApiUnitTester $tester;

    protected const string NESTED_BOOL_RESOURCE_CLASS = 'Generated\\Api\\Storefront\\NestedBoolLeafResource';

    /**
     * The nested-object pass only cascades into properties typed under the generated
     * `Generated\Api\Storefront` namespace, so the fixture has to carry that FQCN. It is defined at
     * runtime because the namespace is reserved for generated code and has no source-tree home.
     */
    protected function _before(): void
    {
        if (class_exists(static::NESTED_BOOL_RESOURCE_CLASS)) {
            return;
        }

        // phpcs:ignore Squiz.PHP.Eval.Discouraged
        eval(
            'namespace Generated\\Api\\Storefront;'
            . ' class NestedBoolLeafValueObject { public ?bool $isComplete = null; }'
            . ' class NestedBoolLeafResource { public ?NestedBoolLeafValueObject $productConfigurationInstance = null; }'
        );
    }

    /**
     * On a write-only operation the leaf is assigned through a typed setter, where PHP's weak mode
     * turns any non-empty string into `true` before `Assert\Type` can see it.
     */
    public function testGivenNonBooleanNestedLeafOnPatchWhenOnKernelResponseThenPassingResponseIsPromotedTo422(): void
    {
        // Arrange
        $request = $this->createRequest(
            static::NESTED_BOOL_RESOURCE_CLASS,
            ['productConfigurationInstance' => ['isComplete' => 'yes']],
            Request::METHOD_PATCH,
        );
        $event = $this->createResponseEvent($request, new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json']));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $event->getResponse()->getStatusCode());
        $this->assertContains(
            'productConfigurationInstance.isComplete => This value should be of type boolean.',
            $this->extractDetails($event),
        );
    }

    public function testGivenBooleanNestedLeafOnPatchWhenOnKernelResponseThenResponseIsLeftAlone(): void
    {
        // Arrange
        $request = $this->createRequest(
            static::NESTED_BOOL_RESOURCE_CLASS,
            ['productConfigurationInstance' => ['isComplete' => true]],
            Request::METHOD_PATCH,
        );
        $event = $this->createResponseEvent($request, new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json']));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertSame(Response::HTTP_OK, $event->getResponse()->getStatusCode());
    }

    /**
     * A read never carries a body to re-check, so the pass must not touch its response.
     */
    public function testGivenNonBooleanNestedLeafOnGetWhenOnKernelResponseThenResponseIsLeftAlone(): void
    {
        // Arrange
        $request = $this->createRequest(
            static::NESTED_BOOL_RESOURCE_CLASS,
            ['productConfigurationInstance' => ['isComplete' => 'yes']],
            Request::METHOD_GET,
        );
        $event = $this->createResponseEvent($request, new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json']));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertSame(Response::HTTP_OK, $event->getResponse()->getStatusCode());
    }

    public function testGivenEmptyStringQuantityWhenOnKernelResponseThenTypeIntegerAndGreaterThanErrorsAreAdded(): void
    {
        // Arrange
        $resource = new class {
            #[Type('integer')]
            #[GreaterThan(0)]
            public ?int $quantity = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => '']);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'quantity => This value should not be blank.'],
        ]);

        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        // Assert
        $this->assertContains('quantity => This value should be of type integer.', $details);
        $this->assertContains('quantity => This value should be greater than 0.', $details);
    }

    public function testGivenNumericStringQuantityWhenOnKernelResponseThenTypeIntegerErrorIsPrepended(): void
    {
        // Arrange
        $resource = new class {
            #[Type('integer')]
            #[GreaterThan(0)]
            public ?int $quantity = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => '-2']);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'quantity => This value should be greater than 0.'],
        ]);

        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        $data = json_decode((string)$event->getResponse()->getContent(), true);

        // Assert
        $this->assertSame('quantity => This value should be of type integer.', $data['errors'][0]['detail']);
    }

    public function testGivenNonNumericStringQuantityWhenOnKernelResponseThenTypeNumericIsReplacedWithTypeInteger(): void
    {
        // Arrange
        $resource = new class {
            #[Type('integer')]
            #[GreaterThan(0)]
            public ?int $quantity = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => 'test']);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'quantity => This value should be of type numeric.'],
        ]);

        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        // Assert
        $this->assertContains('quantity => This value should be of type integer.', $details);
        $this->assertNotContains('quantity => This value should be of type numeric.', $details);
    }

    public function testGivenRequiredBoolFieldAbsentWhenOnKernelResponseThenFieldMissingErrorIsAdded(): void
    {
        // Arrange
        $resource = new class {
            public ?string $sku = null;

            #[ApiProperty(required: true)]
            public ?bool $accepted = null;
        };

        $request = $this->createRequest(get_class($resource), ['sku' => 'test-sku']);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'sku => This value should not be blank.'],
        ]);

        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        // Assert
        $this->assertContains('accepted => This field is missing.', $details);
    }

    public function testGivenRequiredBoolFieldAsEmptyStringWhenOnKernelResponseThenShouldBeTrueErrorIsAdded(): void
    {
        // Arrange
        $resource = new class {
            public ?string $sku = null;

            #[ApiProperty(required: true)]
            public ?bool $accepted = null;
        };

        $request = $this->createRequest(get_class($resource), ['sku' => 'test-sku', 'accepted' => '']);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'sku => This value should not be blank.'],
        ]);

        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        // Assert
        $this->assertContains('accepted => This value should be true.', $details);
    }

    public function testGivenEmptyStringQuantityWhenOnKernelResponseThenTheSynthesizedViolationsAreKeptOnTheRequestAndOffTheWire(): void
    {
        // Arrange
        $resource = new class {
            #[Type('integer')]
            #[GreaterThan(0)]
            public ?int $quantity = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => '']);
        $event = $this->createResponseEvent($request, $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'quantity => This value should not be blank.'],
        ]));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertEquals(
            [new SynthesizedViolation('quantity', 'Type'), new SynthesizedViolation('quantity', 'GreaterThan')],
            $request->attributes->get(RequestAttribute::SYNTHESIZED_VIOLATIONS),
        );
        $this->assertStringNotContainsString(SynthesizedViolation::ERROR_KEY, (string)$event->getResponse()->getContent());
    }

    public function testGivenNonBooleanNestedLeafOnPatchWhenOnKernelResponseThenTheSynthesizedViolationNamesTheFullPath(): void
    {
        // Arrange
        $request = $this->createRequest(
            static::NESTED_BOOL_RESOURCE_CLASS,
            ['productConfigurationInstance' => ['isComplete' => 'yes']],
            Request::METHOD_PATCH,
        );
        $event = $this->createResponseEvent($request, new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json']));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertEquals(
            [new SynthesizedViolation('productConfigurationInstance.isComplete', 'Type')],
            $request->attributes->get(RequestAttribute::SYNTHESIZED_VIOLATIONS),
        );
        $this->assertStringNotContainsString(SynthesizedViolation::ERROR_KEY, (string)$event->getResponse()->getContent());
    }

    public function testGivenAnUnassignableNestedLeafWhenOnKernelResponseThenTheUnprefixedDetailKeepsTheFullPathInItsSynthesizedViolation(): void
    {
        // Arrange
        $request = $this->createRequest(static::NESTED_BOOL_RESOURCE_CLASS, ['productConfigurationInstance' => ['isComplete' => 'yes']]);
        $event = $this->createResponseEvent($request, $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'productConfigurationInstance => This value should be of type object.'],
        ]));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertContains('isComplete => This value should be of type bool.', $this->extractDetails($event));
        $paths = array_map(
            static fn (SynthesizedViolation $violation): string => $violation->propertyPath,
            $request->attributes->get(RequestAttribute::SYNTHESIZED_VIOLATIONS),
        );
        $this->assertSame(['productConfigurationInstance.isComplete'], array_values(array_unique($paths)));
    }

    public function testGivenRequiredBoolFieldAbsentWhenOnKernelResponseThenOnlyTheDeclaredConstraintsANullFailsAreSynthesized(): void
    {
        // Arrange
        $resource = new class {
            #[ApiProperty(required: true)]
            #[NotNull]
            #[IsTrue]
            public ?bool $accepted = null;
        };

        $request = $this->createRequest(get_class($resource), ['sku' => 'test-sku']);
        $event = $this->createResponseEvent($request, $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'sku => This value should not be blank.'],
        ]));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertEquals(
            [new SynthesizedViolation('accepted', 'NotNull')],
            $request->attributes->get(RequestAttribute::SYNTHESIZED_VIOLATIONS),
        );
    }

    public function testGivenEmptyStringQuantityWhenOnKernelResponseThenOnlyTheComparisonsTheEmptyStringFailsAreSynthesized(): void
    {
        // Arrange: '' > 0 is false under PHP 8 string comparison, '' < 10 is true.
        $resource = new class {
            #[Type('integer')]
            #[GreaterThan(0)]
            #[LessThan(10)]
            public ?int $quantity = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => '']);
        $event = $this->createResponseEvent($request, $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'quantity => This value should not be blank.'],
        ]));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertContains('quantity => This value should be less than 10.', $this->extractDetails($event));
        $this->assertEquals(
            [new SynthesizedViolation('quantity', 'Type'), new SynthesizedViolation('quantity', 'GreaterThan')],
            $request->attributes->get(RequestAttribute::SYNTHESIZED_VIOLATIONS),
        );
    }

    public function testGivenRequiredBoolFieldAbsentWithANotNullOfAnotherGroupWhenOnKernelResponseThenNothingIsSynthesized(): void
    {
        // Arrange
        $resource = new class {
            #[ApiProperty(required: true)]
            #[NotNull(groups: ['other'])]
            public ?bool $accepted = null;
        };

        $request = $this->createRequest(get_class($resource), ['sku' => 'test-sku']);
        $request->attributes->set('_api_operation', new Post(validationContext: ['groups' => ['create']]));
        $event = $this->createResponseEvent($request, $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'sku => This value should not be blank.'],
        ]));

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $this->assertContains('accepted => This field is missing.', $this->extractDetails($event));
        $this->assertSame([], $request->attributes->get(RequestAttribute::SYNTHESIZED_VIOLATIONS));
    }

    public function testGivenNoRequestWhenRewritingErrorsThenTheSynthesizedViolationsAreStillKeptOffTheWire(): void
    {
        // Arrange
        $response = $this->createUnprocessableResponse([['code' => '901', 'detail' => 'quantity => This value should not be blank.']]);
        $augment = static fn (array $errors): array => [
            ...$errors,
            ['code' => '901', 'detail' => 'quantity => x', SynthesizedViolation::ERROR_KEY => [new SynthesizedViolation('quantity', 'Type')]],
        ];

        // Act
        (new ReflectionMethod(GlueApiExceptionSubscriber::class, 'rewriteErrors'))->invoke($this->createSubscriber(), $response, $augment);

        // Assert
        $this->assertStringNotContainsString(SynthesizedViolation::ERROR_KEY, (string)$response->getContent());
        $this->assertStringContainsString('quantity => x', (string)$response->getContent());
    }

    public function testGivenConcatenatedErrorDetailWhenOnKernelResponseThenErrorIsSplitIntoSeparateObjects(): void
    {
        // Arrange
        $resource = new class {
            public ?int $quantity = null;

            public ?string $sku = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => 1, 'sku' => '']);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => "quantity: This value should not be blank.\nsku: This value should not be blank."],
        ]);

        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        // Assert
        $this->assertContains('quantity => This value should not be blank.', $details);
        $this->assertContains('sku => This value should not be blank.', $details);
    }

    public function testGivenConcatenatedDotPathErrorDetailWhenOnKernelResponseThenSplitWithArrowFormat(): void
    {
        // Arrange
        $resource = new class {
            public mixed $billingAddress = null;

            public mixed $shipment = null;
        };

        // Nested value objects validated via an `Assert\Valid` cascade produce dot-notation property
        // paths (`billingAddress.salutation`), unlike the bracket notation of array Collections. The
        // reformatter must still split + convert these to the `path => message` BC shape.
        $request = $this->createRequest(get_class($resource), ['billingAddress' => [], 'shipment' => []]);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => "billingAddress.salutation: This value should not be blank.\nshipment.idShipmentMethod: This field is missing."],
        ]);

        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        // Assert
        $this->assertContains('billingAddress.salutation => This value should not be blank.', $details);
        $this->assertContains('shipment.idShipmentMethod => This field is missing.', $details);
    }

    public function testGivenFieldNotInRequestBodyWhenOnKernelResponseThenDetailBecomesFieldMissing(): void
    {
        // Arrange
        $resource = new class {
            public ?int $quantity = null;

            public ?string $sku = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => 1]);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'sku: This value should not be blank.'],
        ]);

        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        // Assert
        $this->assertContains('sku => This field is missing.', $details);
        $this->assertNotContains('sku => This value should not be blank.', $details);
    }

    public function testGiven400DenormalizeErrorWhenOnKernelResponseThenResponseBecomesStatus422WithCode901(): void
    {
        // Arrange
        $resource = new class {
            public ?int $quantity = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => 'not-a-number']);
        $response = new Response(
            (string)json_encode(['errors' => [['detail' => 'Failed to denormalize attribute "quantity" value for class "SomeClass": Expected argument of type "?int", "string" given']]]),
            Response::HTTP_BAD_REQUEST,
            ['Content-Type' => 'application/json'],
        );
        $event = $this->createResponseEvent($request, $response);

        // Act
        $this->createSubscriber()->onKernelResponse($event);

        // Assert
        $convertedResponse = $event->getResponse();
        $data = json_decode((string)$convertedResponse->getContent(), true);

        // Assert
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $convertedResponse->getStatusCode());
        $this->assertSame('901', $data['errors'][0]['code']);
        $this->assertStringContainsString('quantity => This value should be of type numeric.', $data['errors'][0]['detail']);
    }

    public function testGivenRelativeSelfLinkWhenOnKernelResponseThenLinkIsPromotedToAbsolute(): void
    {
        // Arrange
        $request = Request::create('/test-resources/1');
        $request->attributes->set('_api_resource_class', 'App\\Resource\\TestResource');
        $response = new Response(
            '{"data":{"id":"1","type":"test-resources","links":{"self":"/test-resources/1"}}}',
            Response::HTTP_OK,
            ['Content-Type' => 'application/vnd.api+json'],
        );

        // Act
        $this->createSubscriber()->onKernelResponse($this->createResponseEvent($request, $response));

        // Assert
        $data = json_decode((string)$response->getContent(), true);
        $this->assertSame('http://localhost/test-resources/1', $data['data']['links']['self']);
    }

    public function testGivenAbsoluteLinksWhenOnKernelResponseThenBodyStaysByteIdentical(): void
    {
        // Arrange: relative "url" is an attribute VALUE, not a link — must not trigger the decode/promotion
        $content = '{"data":{"id":"1","type":"test-resources","attributes":{"url":"/en/test-page"},"links":{"self":"http://localhost/test-resources/1"}}}';
        $request = Request::create('/test-resources/1');
        $request->attributes->set('_api_resource_class', 'App\\Resource\\TestResource');
        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/vnd.api+json']);

        // Act
        $this->createSubscriber()->onKernelResponse($this->createResponseEvent($request, $response));

        // Assert
        $this->assertSame($content, $response->getContent());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function createRequest(string $resourceClass, array $attributes, string $method = Request::METHOD_POST): Request
    {
        $request = Request::create('/test', $method, [], [], [], [], (string)json_encode([
            'data' => ['attributes' => $attributes],
        ]));
        $request->attributes->set('_api_resource_class', $resourceClass);

        return $request;
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    protected function createUnprocessableResponse(array $errors): Response
    {
        return new Response(
            (string)json_encode(['errors' => $errors]),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['Content-Type' => 'application/json'],
        );
    }

    protected function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    protected function createSubscriber(): GlueApiExceptionSubscriber
    {
        $constraintReader = new ValidationConstraintReader();

        return new GlueApiExceptionSubscriber(
            new IdentityTranslator(),
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
            $constraintReader,
            new NestedObjectValidationErrorAugmenter($constraintReader, new IdentityTranslator()),
            true,
        );
    }

    /**
     * @return array<string>
     */
    protected function extractDetails(ResponseEvent $event): array
    {
        $data = json_decode((string)$event->getResponse()->getContent(), true);

        return array_column($data['errors'], 'detail');
    }
}
