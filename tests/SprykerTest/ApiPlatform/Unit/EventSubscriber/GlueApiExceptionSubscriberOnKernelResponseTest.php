<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    public function testGivenEmptyStringQuantityWhenOnKernelResponseThenTypeIntegerAndGreaterThanErrorsAreAdded(): void
    {
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
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        $this->assertContains('quantity => This value should be of type integer.', $details);
        $this->assertContains('quantity => This value should be greater than 0.', $details);
    }

    public function testGivenNumericStringQuantityWhenOnKernelResponseThenTypeIntegerErrorIsPrepended(): void
    {
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
        $this->createSubscriber()->onKernelResponse($event);

        $data = json_decode((string)$event->getResponse()->getContent(), true);

        $this->assertSame('quantity => This value should be of type integer.', $data['errors'][0]['detail']);
    }

    public function testGivenNonNumericStringQuantityWhenOnKernelResponseThenTypeNumericIsReplacedWithTypeInteger(): void
    {
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
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        $this->assertContains('quantity => This value should be of type integer.', $details);
        $this->assertNotContains('quantity => This value should be of type numeric.', $details);
    }

    public function testGivenRequiredBoolFieldAbsentWhenOnKernelResponseThenFieldMissingErrorIsAdded(): void
    {
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
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        $this->assertContains('accepted => This field is missing.', $details);
    }

    public function testGivenRequiredBoolFieldAsEmptyStringWhenOnKernelResponseThenShouldBeTrueErrorIsAdded(): void
    {
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
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        $this->assertContains('accepted => This value should be true.', $details);
    }

    public function testGivenConcatenatedErrorDetailWhenOnKernelResponseThenErrorIsSplitIntoSeparateObjects(): void
    {
        $resource = new class {
            public ?int $quantity = null;

            public ?string $sku = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => 1, 'sku' => '']);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => "quantity: This value should not be blank.\nsku: This value should not be blank."],
        ]);

        $event = $this->createResponseEvent($request, $response);
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        $this->assertContains('quantity => This value should not be blank.', $details);
        $this->assertContains('sku => This value should not be blank.', $details);
    }

    public function testGivenFieldNotInRequestBodyWhenOnKernelResponseThenDetailBecomesFieldMissing(): void
    {
        $resource = new class {
            public ?int $quantity = null;

            public ?string $sku = null;
        };

        $request = $this->createRequest(get_class($resource), ['quantity' => 1]);
        $response = $this->createUnprocessableResponse([
            ['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'sku: This value should not be blank.'],
        ]);

        $event = $this->createResponseEvent($request, $response);
        $this->createSubscriber()->onKernelResponse($event);

        $details = $this->extractDetails($event);

        $this->assertContains('sku => This field is missing.', $details);
        $this->assertNotContains('sku => This value should not be blank.', $details);
    }

    public function testGiven400DenormalizeErrorWhenOnKernelResponseThenResponseBecomesStatus422WithCode901(): void
    {
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
        $this->createSubscriber()->onKernelResponse($event);

        $convertedResponse = $event->getResponse();
        $data = json_decode((string)$convertedResponse->getContent(), true);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $convertedResponse->getStatusCode());
        $this->assertSame('901', $data['errors'][0]['code']);
        $this->assertStringContainsString('quantity => This value should be of type numeric.', $data['errors'][0]['detail']);
    }

    protected function createRequest(string $resourceClass, array $attributes): Request
    {
        $request = Request::create('/test', 'POST', [], [], [], [], (string)json_encode([
            'data' => ['attributes' => $attributes],
        ]));
        $request->attributes->set('_api_resource_class', $resourceClass);

        return $request;
    }

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
        return new GlueApiExceptionSubscriber(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
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
