<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Validation;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\EventSubscriber\JsonApiRequestValidatorSubscriber;
use Spryker\ApiPlatform\Validation\Constraint\StrictBoolean;
use Spryker\ApiPlatform\Validation\Constraint\StrictBooleanValidator;
use SprykerTest\ApiPlatform\Fixture\StrictBooleanFixture;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Validation
 * @group StrictBooleanValidatorTest
 * Add your own group annotations below this line
 */
class StrictBooleanValidatorTest extends Unit
{
    protected const string PROPERTY = 'isActive';

    protected const string RESOURCE_TYPE = 'strict-boolean-fixtures';

    protected const string RESOURCE_PATH = '/strict-boolean-fixtures';

    /**
     * @dataProvider provideRawValuesThatAreNotBooleans
     */
    public function testRejectsAValueThatWasNotSentAsABoolean(mixed $rawValue, bool $coercedValue): void
    {
        // Arrange
        $validator = $this->createValidator($this->buildJsonApiBody([static::PROPERTY => $rawValue]));
        $fixture = new StrictBooleanFixture();
        $fixture->isActive = $coercedValue;

        // Act
        $violations = $validator->validate($fixture);

        // Assert
        $this->assertCount(1, $violations, sprintf(
            'Expected %s to be rejected, got no violation.',
            json_encode($rawValue),
        ));
        $this->assertSame(StrictBooleanFixture::MESSAGE, $violations->get(0)->getMessageTemplate());
        $this->assertSame(static::PROPERTY, $violations->get(0)->getPropertyPath());
        $this->assertSame(StrictBoolean::NOT_BOOLEAN_ERROR, $violations->get(0)->getCode());
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public function provideRawValuesThatAreNotBooleans(): array
    {
        return [
            'the string "yes"' => ['yes', true],
            'a non-boolean string' => ['abc', true],
            'a capitalised "True"' => ['True', true],
            'an upper-case "FALSE"' => ['FALSE', true],
            'the integer 1' => [1, true],
            'the integer 0' => [0, false],
            'an integer that is not 0 or 1' => [2, true],
            'a float' => [1.0, true],
            'an empty string reaching the validator directly' => ['', false],
        ];
    }

    /**
     * @dataProvider provideAcceptedValues
     */
    public function testAcceptsAJsonBooleanAndTheTwoBooleanStrings(mixed $rawValue, bool $coercedValue): void
    {
        // Arrange
        $validator = $this->createValidator($this->buildJsonApiBody([static::PROPERTY => $rawValue]));
        $fixture = new StrictBooleanFixture();
        $fixture->isActive = $coercedValue;

        // Act
        $violations = $validator->validate($fixture);

        // Assert
        $this->assertCount(0, $violations, sprintf(
            'Expected %s to be accepted, got: %s',
            json_encode($rawValue),
            (string)$violations,
        ));
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public function provideAcceptedValues(): array
    {
        return [
            'the boolean true' => [true, true],
            'the boolean false' => [false, false],
            'the string "true"' => ['true', true],
            'the string "false"' => ['false', true],
        ];
    }

    public function testRejectsAPropertySanitizedFromAnEmptyString(): void
    {
        // Arrange
        $validator = $this->createValidator(
            $this->buildJsonApiBody([static::PROPERTY => null]),
            [static::PROPERTY],
        );

        // Act
        $violations = $validator->validate(new StrictBooleanFixture());

        // Assert
        $this->assertCount(1, $violations);
        $this->assertSame(StrictBooleanFixture::MESSAGE, $violations->get(0)->getMessageTemplate());
    }

    public function testAcceptsAnExplicitNullWhenNothingWasSanitized(): void
    {
        // Arrange
        $validator = $this->createValidator($this->buildJsonApiBody([static::PROPERTY => null]), ['name']);

        // Act
        $violations = $validator->validate(new StrictBooleanFixture());

        // Assert
        $this->assertCount(0, $violations);
    }

    public function testIgnoresAPropertyThatWasNotSubmitted(): void
    {
        // Arrange
        $validator = $this->createValidator($this->buildJsonApiBody(['name' => 'Acme']));

        // Act
        $violations = $validator->validate(new StrictBooleanFixture());

        // Assert
        $this->assertCount(0, $violations);
    }

    public function testIgnoresAnExplicitNullSoNullHandlingStaysWithNotNull(): void
    {
        // Arrange
        $validator = $this->createValidator($this->buildJsonApiBody([static::PROPERTY => null]));

        // Act
        $violations = $validator->validate(new StrictBooleanFixture());

        // Assert
        $this->assertCount(0, $violations);
    }

    public function testIgnoresARequestWhoseBodyIsNotAJsonApiDocument(): void
    {
        // Arrange
        $validator = $this->createValidator('{"isActive":"true"}');

        // Act
        $violations = $validator->validate(new StrictBooleanFixture());

        // Assert
        $this->assertCount(0, $violations);
    }

    public function testIgnoresARequestWithAnUnparseableBody(): void
    {
        // Arrange
        $validator = $this->createValidator('not json at all');

        // Act
        $violations = $validator->validate(new StrictBooleanFixture());

        // Assert
        $this->assertCount(0, $violations);
    }

    public function testIgnoresValidationRunOutsideARequest(): void
    {
        // Arrange
        $validator = $this->createValidator(null);

        // Act
        $violations = $validator->validate(new StrictBooleanFixture());

        // Assert
        $this->assertCount(0, $violations);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function buildJsonApiBody(array $attributes): string
    {
        return (string)json_encode(['data' => ['type' => static::RESOURCE_TYPE, 'attributes' => $attributes]]);
    }

    /**
     * @param array<string> $sanitizedEmptyStringFields
     */
    protected function createValidator(
        ?string $requestBody,
        array $sanitizedEmptyStringFields = []
    ): ValidatorInterface {
        $requestStack = new RequestStack();

        if ($requestBody !== null) {
            $request = Request::create(static::RESOURCE_PATH, Request::METHOD_POST, [], [], [], [], $requestBody);
            $request->attributes->set(
                JsonApiRequestValidatorSubscriber::ATTRIBUTE_SANITIZED_EMPTY_STRING_FIELDS,
                $sanitizedEmptyStringFields,
            );
            $requestStack->push($request);
        }

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                StrictBooleanValidator::class => new StrictBooleanValidator($requestStack),
            ]))
            ->getValidator();
    }
}
