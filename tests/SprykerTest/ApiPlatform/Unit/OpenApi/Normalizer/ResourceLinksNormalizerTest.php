<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\Normalizer;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use Codeception\Test\Unit;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spryker\ApiPlatform\OpenApi\Normalizer\ResourceLinksNormalizer;
use stdClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group Normalizer
 * @group ResourceLinksNormalizerTest
 * Add your own group annotations below this line
 */
class ResourceLinksNormalizerTest extends Unit
{
    protected const string FORMAT_JSON_API = 'jsonapi';

    protected const string EXPORT_ROUTE = 'exportDiscountVoucherCodes';

    protected const string EXPORT_LINK_NAME = 'voucherCodesExport';

    /**
     * @dataProvider provideNormalizationSupport
     *
     * @param array<string, mixed> $context
     */
    public function testSupportsNormalization(mixed $data, ?string $format, array $context, bool $expected): void
    {
        // Arrange
        $normalizer = $this->createNormalizer([]);

        // Act
        $result = $normalizer->supportsNormalization($data, $format, $context);

        // Assert
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function provideNormalizationSupport(): array
    {
        return [
            'given a jsonapi object, when asked, then the normalizer supports it' => [
                'data' => new stdClass(),
                'format' => static::FORMAT_JSON_API,
                'context' => [],
                'expected' => true,
            ],
            'given another format, when asked, then the normalizer declines' => [
                'data' => new stdClass(),
                'format' => 'json',
                'context' => [],
                'expected' => false,
            ],
            'given a scalar, when asked, then the normalizer declines' => [
                'data' => 'discounts',
                'format' => static::FORMAT_JSON_API,
                'context' => [],
                'expected' => false,
            ],
            'given the recursion guard is set, when asked, then the normalizer declines' => [
                'data' => new stdClass(),
                'format' => static::FORMAT_JSON_API,
                'context' => [ResourceLinksNormalizer::class => true],
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider provideLinkRendering
     *
     * @param array<string, mixed> $extraProperties
     * @param array<string, string>|null $expectedLinks
     * @param array<string, mixed>|null $innerData
     */
    public function testNormalizeAddsLinks(
        array $extraProperties,
        ?Operation $operation,
        mixed $object,
        ?array $expectedLinks,
        ?array $innerData = null,
    ): void {
        // Arrange
        $innerData ??= ['data' => ['type' => 'discounts', 'id' => 'discount-uuid']];
        $normalizer = $this->createNormalizer($innerData);
        $context = $operation === null ? [] : ['operation' => $operation->withExtraProperties($extraProperties)];

        // Act
        $result = $normalizer->normalize($object, static::FORMAT_JSON_API, $context);

        // Assert
        $this->assertSame($expectedLinks, $result['data']['links'] ?? null);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function provideLinkRendering(): array
    {
        $voucherDiscount = (object)['uuid' => 'discount-uuid', 'discountType' => 'voucher'];
        $cartRuleDiscount = (object)['uuid' => 'discount-uuid', 'discountType' => 'cart_rule'];
        $exportLink = [
            'route' => static::EXPORT_ROUTE,
            'parameters' => ['uuid' => 'uuid'],
            'condition' => ['property' => 'discountType', 'equals' => 'voucher'],
            'operations' => ['Get'],
        ];
        $exportUrl = 'https://glue.spryker.test/' . static::EXPORT_ROUTE . '?uuid=discount-uuid';

        return [
            'given a matching condition on a scoped operation, when normalizing, then the link is an absolute url' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => $exportLink]],
                'operation' => new Get(name: '_api_/discounts/{uuid}_get'),
                'object' => $voucherDiscount,
                'expectedLinks' => [static::EXPORT_LINK_NAME => $exportUrl],
            ],
            'given a condition that does not match, when normalizing, then the link is omitted' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => $exportLink]],
                'operation' => new Get(name: '_api_/discounts/{uuid}_get'),
                'object' => $cartRuleDiscount,
                'expectedLinks' => null,
            ],
            'given an operation outside the declared scope, when normalizing, then the link is omitted' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => $exportLink]],
                'operation' => new Patch(name: '_api_/discounts/{uuid}_patch'),
                'object' => $voucherDiscount,
                'expectedLinks' => null,
            ],
            'given a scope naming the operation, when normalizing, then the link is rendered' => [
                'extraProperties' => [
                    'links' => [
                        static::EXPORT_LINK_NAME => ['route' => static::EXPORT_ROUTE, 'operations' => ['activateDiscount']],
                    ],
                ],
                'operation' => new Patch(name: 'activateDiscount'),
                'object' => $voucherDiscount,
                'expectedLinks' => [static::EXPORT_LINK_NAME => 'https://glue.spryker.test/' . static::EXPORT_ROUTE],
            ],
            'given no scope at all, when normalizing, then the link is rendered on every operation' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => ['route' => static::EXPORT_ROUTE]]],
                'operation' => new Patch(name: '_api_/discounts/{uuid}_patch'),
                'object' => $voucherDiscount,
                'expectedLinks' => [static::EXPORT_LINK_NAME => 'https://glue.spryker.test/' . static::EXPORT_ROUTE],
            ],
            'given a route parameter that resolves to null, when normalizing, then the link is omitted' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => $exportLink]],
                'operation' => new Get(name: '_api_/discounts/{uuid}_get'),
                'object' => (object)['uuid' => null, 'discountType' => 'voucher'],
                'expectedLinks' => null,
            ],
            'given a route parameter the resource does not carry, when normalizing, then the link is omitted' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => $exportLink]],
                'operation' => new Get(name: '_api_/discounts/{uuid}_get'),
                'object' => (object)['discountType' => 'voucher'],
                'expectedLinks' => null,
            ],
            'given a link without a route, when normalizing, then the link is omitted' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => ['parameters' => ['uuid' => 'uuid']]]],
                'operation' => new Get(name: '_api_/discounts/{uuid}_get'),
                'object' => $voucherDiscount,
                'expectedLinks' => null,
            ],
            'given links declared as a string, when normalizing, then no link is rendered' => [
                'extraProperties' => ['links' => static::EXPORT_LINK_NAME],
                'operation' => new Get(name: '_api_/discounts/{uuid}_get'),
                'object' => $voucherDiscount,
                'expectedLinks' => null,
            ],
            'given a self link written by an outer normalizer, when normalizing, then it is preserved' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => $exportLink]],
                'operation' => new Get(name: '_api_/discounts/{uuid}_get'),
                'object' => $voucherDiscount,
                'expectedLinks' => [
                    'self' => 'https://glue.spryker.test/discounts/discount-uuid',
                    static::EXPORT_LINK_NAME => $exportUrl,
                ],
                'innerData' => [
                    'data' => [
                        'type' => 'discounts',
                        'id' => 'discount-uuid',
                        'links' => ['self' => 'https://glue.spryker.test/discounts/discount-uuid'],
                    ],
                ],
            ],
            'given a collection document, when normalizing, then it is left untouched' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => $exportLink]],
                'operation' => new GetCollection(name: '_api_/discounts_get_collection'),
                'object' => $voucherDiscount,
                'expectedLinks' => null,
                'innerData' => ['data' => [['type' => 'discounts', 'id' => 'discount-uuid']]],
            ],
            'given no operation in the context, when normalizing, then no link is rendered' => [
                'extraProperties' => ['links' => [static::EXPORT_LINK_NAME => $exportLink]],
                'operation' => null,
                'object' => $voucherDiscount,
                'expectedLinks' => null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $innerData
     */
    protected function createNormalizer(array $innerData): ResourceLinksNormalizer
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                $path = '/' . $route . ($parameters === [] ? '' : '?' . http_build_query($parameters));

                return $referenceType === UrlGeneratorInterface::ABSOLUTE_URL ? 'https://glue.spryker.test' . $path : $path;
            },
        );

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn($innerData);

        $normalizer = new ResourceLinksNormalizer($urlGenerator, PropertyAccess::createPropertyAccessor(), new NullLogger());
        $normalizer->setNormalizer($innerNormalizer);

        return $normalizer;
    }

    /**
     * @dataProvider provideRoutingFailures
     */
    public function testLeavesOutAndLogsALinkWhoseRouteCannotBeGenerated(Throwable $routingException): void
    {
        // Arrange
        $innerData = ['data' => ['id' => 'uuid-1', 'type' => 'discounts']];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->stringContains(static::EXPORT_LINK_NAME),
            $this->callback(static fn (array $context): bool => isset($context['exception'], $context['routeParameters'])),
        );
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willThrowException($routingException);
        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn($innerData);
        $normalizer = new ResourceLinksNormalizer($urlGenerator, PropertyAccess::createPropertyAccessor(), $logger);
        $normalizer->setNormalizer($innerNormalizer);
        $object = new stdClass();
        $object->uuid = 'uuid-1';

        // Act
        $result = $normalizer->normalize($object, static::FORMAT_JSON_API, [
            'operation' => (new Get(name: '_api_/discounts/{uuid}_get'))->withExtraProperties([
                'links' => [static::EXPORT_LINK_NAME => ['route' => static::EXPORT_ROUTE, 'parameters' => ['uuid' => 'uuid']]],
            ]),
        ]);

        // Assert
        $this->assertSame($innerData, $result);
    }

    /**
     * @dataProvider provideUnreadableProperties
     *
     * @param array<string, mixed> $link
     */
    public function testLeavesOutAndLogsALinkWhosePropertyIsNotReadable(array $link, string $expectedPropertyPath): void
    {
        // Arrange
        $innerData = ['data' => ['id' => 'uuid-1', 'type' => 'discounts']];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->logicalAnd($this->stringContains(static::EXPORT_LINK_NAME), $this->stringContains($expectedPropertyPath)),
            $this->callback(static fn (array $context): bool => ($context['propertyPath'] ?? null) === $expectedPropertyPath),
        );
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');
        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn($innerData);
        $normalizer = new ResourceLinksNormalizer($urlGenerator, PropertyAccess::createPropertyAccessor(), $logger);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize((object)['uuid' => 'uuid-1', 'discountType' => 'voucher'], static::FORMAT_JSON_API, [
            'operation' => (new Get(name: '_api_/discounts/{uuid}_get'))->withExtraProperties([
                'links' => [static::EXPORT_LINK_NAME => $link],
            ]),
        ]);

        // Assert
        $this->assertSame($innerData, $result);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public function provideUnreadableProperties(): array
    {
        return [
            'given a mistyped condition property, when normalizing, then the link is left out and logged' => [
                ['route' => static::EXPORT_ROUTE, 'condition' => ['property' => 'discountTyp', 'equals' => 'voucher']],
                'discountTyp',
            ],
            'given a mistyped parameter property, when normalizing, then the link is left out and logged' => [
                ['route' => static::EXPORT_ROUTE, 'parameters' => ['uuid' => 'uid']],
                'uid',
            ],
        ];
    }

    /**
     * @return array<string, array{\Throwable}>
     */
    public function provideRoutingFailures(): array
    {
        return [
            'given a route that does not exist, when normalizing, then the link is left out and logged' => [new RouteNotFoundException('no such route')],
            'given parameters the route does not accept, when normalizing, then the link is left out and logged' => [new MissingMandatoryParametersException('exportDiscountVoucherCodes', ['uuid'])],
        ];
    }
}
