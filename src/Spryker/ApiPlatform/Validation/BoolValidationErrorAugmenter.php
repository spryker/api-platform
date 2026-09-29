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
use Spryker\ApiPlatform\Validation\Trait\ValidationMessageTranslationTrait;
use Symfony\Component\HttpFoundation\Response;
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
    use ValidationMessageTranslationTrait;

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const string MESSAGE_TEMPLATE_FIELD_MISSING = 'This field is missing.';

    protected const string MESSAGE_TEMPLATE_SHOULD_BE_TRUE = 'This value should be true.';

    public function __construct(protected TranslatorInterface $translator)
    {
    }

    /**
     * @param array<string, mixed> $rawAttributes
     * @param array<int, array<string, mixed>> $errors
     *
     * @return array<int, array<string, mixed>>
     */
    public function augment(string $resourceClass, array $rawAttributes, array $errors): array
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
                    $errors[] = ['detail' => $detail, 'code' => static::ERROR_CODE_VALIDATION, 'status' => Response::HTTP_UNPROCESSABLE_ENTITY];
                    $existingDetails[$detail] = true;
                }

                continue;
            }

            if ($rawAttributes[$fieldName] === '' || $rawAttributes[$fieldName] === null) {
                $detail = sprintf('%s => %s', $fieldName, $this->translateValidationMessage(static::MESSAGE_TEMPLATE_SHOULD_BE_TRUE));

                if (!isset($existingDetails[$detail])) {
                    $errors[] = ['detail' => $detail, 'code' => static::ERROR_CODE_VALIDATION, 'status' => Response::HTTP_UNPROCESSABLE_ENTITY];
                    $existingDetails[$detail] = true;
                }
            }
        }

        return $errors;
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
