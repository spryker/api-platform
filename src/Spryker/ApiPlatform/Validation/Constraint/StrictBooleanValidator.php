<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation\Constraint;

use Spryker\ApiPlatform\EventSubscriber\JsonApiRequestValidatorSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates {@see \Spryker\ApiPlatform\Validation\Constraint\StrictBoolean} against the value as the
 * client sent it, read straight from the request body, rather than against the property — which the
 * deserializer has already coerced to a real bool by the time validation runs.
 *
 * Only top-level `data.attributes` members are inspected. A property nested inside an object is
 * coerced by the same deserializer but is not addressable here, so declaring the constraint on one
 * has no effect.
 */
class StrictBooleanValidator extends ConstraintValidator
{
    protected const string BODY_KEY_DATA = 'data';

    protected const string BODY_KEY_ATTRIBUTES = 'attributes';

    public function __construct(protected RequestStack $requestStack)
    {
    }

    /**
     * @throws \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrictBoolean) {
            throw new UnexpectedTypeException($constraint, StrictBoolean::class);
        }

        $propertyName = $this->context->getPropertyName();
        $request = $this->requestStack->getCurrentRequest();

        if ($propertyName === null || !$request instanceof Request) {
            return;
        }

        if ($this->wasSubmittedAsEmptyString($request, $propertyName)) {
            $this->addViolation($constraint, '');

            return;
        }

        $submittedValue = $this->findSubmittedValue($request, $propertyName);

        if ($submittedValue === null || is_bool($submittedValue)) {
            return;
        }

        if (is_string($submittedValue) && array_key_exists($submittedValue, StrictBoolean::ACCEPTED_STRINGS)) {
            return;
        }

        $this->addViolation($constraint, $submittedValue);
    }

    protected function wasSubmittedAsEmptyString(Request $request, string $propertyName): bool
    {
        $sanitizedEmptyStringFields = $request->attributes->get(
            JsonApiRequestValidatorSubscriber::ATTRIBUTE_SANITIZED_EMPTY_STRING_FIELDS,
            [],
        );

        return is_array($sanitizedEmptyStringFields) && in_array($propertyName, $sanitizedEmptyStringFields, true);
    }

    protected function addViolation(StrictBoolean $constraint, mixed $submittedValue): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($submittedValue))
            ->setCode(StrictBoolean::NOT_BOOLEAN_ERROR)
            ->addViolation();
    }

    protected function findSubmittedValue(Request $request, string $propertyName): mixed
    {
        $body = json_decode((string)$request->getContent(), true);

        if (!is_array($body)) {
            return null;
        }

        $attributes = $body[static::BODY_KEY_DATA][static::BODY_KEY_ATTRIBUTES] ?? null;

        if (!is_array($attributes)) {
            return null;
        }

        return $attributes[$propertyName] ?? null;
    }
}
