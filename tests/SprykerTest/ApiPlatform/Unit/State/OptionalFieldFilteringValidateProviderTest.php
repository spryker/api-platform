<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\State\OptionalFieldFilteringValidateProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group OptionalFieldFilteringValidateProviderTest
 * Add your own group annotations below this line
 */
class OptionalFieldFilteringValidateProviderTest extends Unit
{
    protected const string NOT_BLANK_MESSAGE = 'This value should not be blank.';

    /**
     * @dataProvider violationFilteringProvider
     *
     * @param array<string, mixed> $attributes
     * @param array<int, array{path: string, optional: bool}> $violations
     * @param array<int, string> $expectedRemainingPaths
     */
    public function testGivenSubmittedBodyWhenValidationFailsThenKeepsOnlyTheViolationsThatStillApply(
        array $attributes,
        array $violations,
        array $expectedRemainingPaths
    ): void {
        // Arrange
        $resource = (object)['id' => 1];
        $decorated = $this->createMock(ProviderInterface::class);
        $decorated->method('provide')->willReturnCallback(
            function (Post $operation) use ($violations, $resource): object {
                // The all-dropped branch re-invokes with validation switched off to get the body.
                if ($operation->canValidate() === false) {
                    return $resource;
                }

                throw new ValidationException($this->createViolationList($violations));
            },
        );

        $provider = new OptionalFieldFilteringValidateProvider($decorated);
        $context = ['request' => $this->createRequest(['data' => ['attributes' => $attributes]])];

        // Act & Assert
        if ($expectedRemainingPaths === []) {
            $this->assertSame($resource, $provider->provide(new Post(), [], $context));

            return;
        }

        try {
            $provider->provide(new Post(), [], $context);
            $this->fail('Expected a ValidationException for the violations that still apply.');
        } catch (ValidationException $exception) {
            $remainingPaths = [];

            foreach ($exception->getConstraintViolationList() as $violation) {
                $remainingPaths[] = $violation->getPropertyPath();
            }

            $this->assertSame($expectedRemainingPaths, $remainingPaths);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, array{path: string, optional: bool}>, array<int, string>}>
     */
    public function violationFilteringProvider(): array
    {
        return [
            'absent top-level optional field is dropped' => [
                ['idCart' => 'cart-uuid'],
                [['path' => 'shipment', 'optional' => true]],
                [],
            ],
            'absent nested optional field is dropped' => [
                ['shipments' => [['items' => ['group-key']]]],
                [['path' => 'shipments[0].shippingAddress', 'optional' => true]],
                [],
            ],
            // Only the element that omitted the key may lose its violation.
            'per-index precision' => [
                [
            'shipments' => [
                    ['items' => ['group-key-1'], 'shippingAddress' => ['id' => 'address-uuid']],
                    ['items' => ['group-key-2']],
                ]],
                [
                    ['path' => 'shipments[0].shippingAddress', 'optional' => true],
                    ['path' => 'shipments[1].shippingAddress', 'optional' => true],
                ],
                ['shipments[0].shippingAddress'],
            ],
            // Explicit null is submitted, so the legacy Collection 422 must survive.
            'explicit null is not dropped' => [
                ['shipments' => [['items' => ['group-key'], 'shippingAddress' => null]]],
                [['path' => 'shipments[0].shippingAddress', 'optional' => true]],
                ['shipments[0].shippingAddress'],
            ],
            'untagged violation is never dropped' => [
                ['shipments' => [['shippingAddress' => ['id' => 'address-uuid']]]],
                [['path' => 'shipments[0].items', 'optional' => false]],
                ['shipments[0].items'],
            ],
            'absent required field keeps its violation' => [
                ['shipments' => [[]]],
                [['path' => 'shipments[0].items', 'optional' => false]],
                ['shipments[0].items'],
            ],
            'mixed set keeps only the required failure' => [
                ['shipments' => [[]]],
                [
                    ['path' => 'shipments[0].items', 'optional' => false],
                    ['path' => 'shipments[0].shippingAddress', 'optional' => true],
                ],
                ['shipments[0].items'],
            ],
            'deeply nested absent optional field is dropped' => [
                ['shipments' => [['shippingAddress' => ['id' => 'address-uuid']]]],
                [['path' => 'shipments[0].shippingAddress.iso2Code', 'optional' => true]],
                [],
            ],
            // The Optional marker sits on the Collection, so it governs the CONTAINER, not the leaf.
            // A missing declared field of a SUBMITTED container is the Collection's required-presence
            // contract firing and must survive, even though the leaf itself is absent by definition.
            'missing field of a submitted optional container keeps its violation' => [
                ['productConfigurationInstance' => ['displayData' => '{}', 'configuration' => '{}']],
                [[
                    'path' => 'productConfigurationInstance[isComplete]',
                    'optional' => true,
                    'code' => Collection::MISSING_FIELD_ERROR,
                ]],
                ['productConfigurationInstance[isComplete]'],
            ],
            // Same violation, container never submitted — here the Optional marker does apply.
            'missing field of an absent optional container is dropped' => [
                ['idCart' => 'cart-uuid'],
                [[
                    'path' => 'productConfigurationInstance[isComplete]',
                    'optional' => true,
                    'code' => Collection::MISSING_FIELD_ERROR,
                ]],
                [],
            ],
        ];
    }

    public function testGivenNonJsonApiBodyWhenValidationFailsThenRethrowsUntouched(): void
    {
        // Arrange
        $violationList = $this->createViolationList([['path' => 'shipment', 'optional' => true]]);
        $decorated = $this->createMock(ProviderInterface::class);
        $decorated->method('provide')->willThrowException(new ValidationException($violationList));

        $provider = new OptionalFieldFilteringValidateProvider($decorated);
        $context = ['request' => $this->createRequest(['not' => 'a json api document'])];

        // Act & Assert
        try {
            $provider->provide(new Post(), [], $context);
            $this->fail('Expected the ValidationException to be rethrown.');
        } catch (ValidationException $exception) {
            $this->assertSame($violationList, $exception->getConstraintViolationList());
        }
    }

    public function testGivenNoRequestInContextWhenValidationFailsThenRethrowsUntouched(): void
    {
        // Arrange
        $violationList = $this->createViolationList([['path' => 'shipment', 'optional' => true]]);
        $decorated = $this->createMock(ProviderInterface::class);
        $decorated->method('provide')->willThrowException(new ValidationException($violationList));

        $provider = new OptionalFieldFilteringValidateProvider($decorated);

        // Act & Assert
        try {
            $provider->provide(new Post(), [], []);
            $this->fail('Expected the ValidationException to be rethrown.');
        } catch (ValidationException $exception) {
            $this->assertSame($violationList, $exception->getConstraintViolationList());
        }
    }

    /**
     * @param array<int, array{path: string, optional: bool, code?: string}> $violations
     */
    protected function createViolationList(array $violations): ConstraintViolationList
    {
        $constraintViolations = [];

        foreach ($violations as $violation) {
            $constraint = $this->createConstraint($violation['code'] ?? null);

            if ($violation['optional']) {
                $constraint->payload = ['source' => 'optional'];
            }

            $constraintViolations[] = new ConstraintViolation(
                static::NOT_BLANK_MESSAGE,
                static::NOT_BLANK_MESSAGE,
                [],
                null,
                $violation['path'],
                null,
                null,
                $violation['code'] ?? null,
                $constraint,
            );
        }

        return new ConstraintViolationList($constraintViolations);
    }

    /**
     * A missing-field violation is always raised by the `Collection` that declared the field, and it
     * is that Collection — not the leaf — which carries the Optional payload at runtime.
     */
    protected function createConstraint(?string $code): Constraint
    {
        if ($code !== Collection::MISSING_FIELD_ERROR) {
            return new NotBlank();
        }

        return new Collection(fields: [], allowExtraFields: true);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function createRequest(array $body): Request
    {
        return new Request([], [], [], [], [], [], (string)json_encode($body));
    }
}
