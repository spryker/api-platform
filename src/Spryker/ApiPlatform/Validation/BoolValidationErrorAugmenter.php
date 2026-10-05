<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spryker\ApiPlatform\Validation\Trait\SynthesizedViolationTrait;
use Spryker\ApiPlatform\Validation\Trait\ValidationMessageTranslationTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds the errors the validator misses for required nullable bool properties: absent, or sent as
 * an empty string or null.
 *
 * A pure transformer: it takes the already-decoded errors and returns the augmented set. All HTTP
 * request/response handling stays in the caller.
 */
class BoolValidationErrorAugmenter
{
    use SynthesizedViolationTrait;
    use ValidationMessageTranslationTrait;

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const string MESSAGE_TEMPLATE_FIELD_MISSING = 'This field is missing.';

    protected const string MESSAGE_TEMPLATE_SHOULD_BE_TRUE = 'This value should be true.';

    public function __construct(
        protected TranslatorInterface $translator,
        protected ValidationConstraintReader $constraintReader = new ValidationConstraintReader(),
    ) {
    }

    /**
     * @param array<string, mixed> $rawAttributes
     * @param array<int, array<string, mixed>> $errors
     * @param array<string> $groups The active validation groups; empty means all.
     *
     * @return array<int, array<string, mixed>>
     */
    public function augment(string $resourceClass, array $rawAttributes, array $errors, array $groups = []): array
    {
        if (!class_exists($resourceClass)) {
            return $errors;
        }

        $existingDetails = [];

        foreach ($errors as $error) {
            $existingDetails[$error['detail'] ?? ''] = true;
        }

        $reflectionClass = new ReflectionClass($resourceClass);

        foreach ($reflectionClass->getProperties() as $property) {
            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType || $type->getName() !== 'bool' || !$type->allowsNull()) {
                continue;
            }

            if (!$this->isRequiredApiProperty($property)) {
                continue;
            }

            $fieldName = $property->getName();

            if (!array_key_exists($fieldName, $rawAttributes)) {
                $detail = sprintf('%s => %s', $fieldName, $this->translateValidationMessage(static::MESSAGE_TEMPLATE_FIELD_MISSING));

                if (!isset($existingDetails[$detail])) {
                    $errors[] = $this->withSynthesizedViolations(
                        ['detail' => $detail, 'code' => static::ERROR_CODE_VALIDATION, 'status' => Response::HTTP_UNPROCESSABLE_ENTITY],
                        $fieldName,
                        $this->declaredConstraintsViolatedBy($resourceClass, $fieldName, null, $groups),
                    );
                    $existingDetails[$detail] = true;
                }

                continue;
            }

            if ($rawAttributes[$fieldName] === '' || $rawAttributes[$fieldName] === null) {
                $detail = sprintf('%s => %s', $fieldName, $this->translateValidationMessage(static::MESSAGE_TEMPLATE_SHOULD_BE_TRUE));

                if (!isset($existingDetails[$detail])) {
                    $errors[] = $this->withSynthesizedViolations(
                        ['detail' => $detail, 'code' => static::ERROR_CODE_VALIDATION, 'status' => Response::HTTP_UNPROCESSABLE_ENTITY],
                        $fieldName,
                        $this->declaredConstraintsViolatedBy($resourceClass, $fieldName, $rawAttributes[$fieldName], $groups),
                    );
                    $existingDetails[$detail] = true;
                }
            }
        }

        return $errors;
    }

    /**
     * The presence constraints the property declares in the active groups that the submitted value
     * fails; an absent field is judged as null. The synthesized error stands for exactly these, and
     * for nothing the property does not declare.
     *
     * @param array<string> $groups
     *
     * @return array<string>
     */
    protected function declaredConstraintsViolatedBy(string $resourceClass, string $fieldName, ?string $submittedValue, array $groups): array
    {
        $violated = [];

        foreach ($this->constraintReader->getConstraintsForGroups($resourceClass, $fieldName, $groups) as $constraint) {
            $isViolated = match (true) {
                $constraint instanceof NotNull => $submittedValue === null,
                $constraint instanceof NotBlank => $submittedValue !== null || !$constraint->allowNull,
                $constraint instanceof IsTrue => $submittedValue !== null,
                default => false,
            };

            if ($isViolated) {
                $violated[] = $this->constraintShortName($constraint);
            }
        }

        return $violated;
    }

    protected function isRequiredApiProperty(ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes() as $attribute) {
            if ($attribute->getName() !== 'ApiPlatform\Metadata\ApiProperty') {
                continue;
            }

            $args = $attribute->getArguments();

            return isset($args['required']) && $args['required'] === true;
        }

        return false;
    }

    protected function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }
}
