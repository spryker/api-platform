<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

use Spryker\ApiPlatform\Exception\LossyIntegerConversionException;
use Spryker\ApiPlatform\Validation\Trait\ValidationMessageTranslationTrait;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Turns a denormalization failure into the legacy Glue `<property> => <message>` validation detail.
 */
class DenormalizationErrorMatcher
{
    use ValidationMessageTranslationTrait;

    protected const string MESSAGE_TEMPLATE_TYPE = 'This value should be of type {{ type }}.';

    protected const string TYPE_NAME_INTEGER = 'integer';

    protected const string TYPE_NAME_NUMERIC = 'numeric';

    /**
     * Matches PropertyAccessor's type-mismatch message and captures the expected type and property
     * path: `Expected argument of type "<type>", "<given>" given at property path "<path>"`.
     */
    protected const string REGEX_PROPERTY_ACCESS_TYPE_ERROR = '/Expected argument of type "(\??[\w\\\\]+)", "[^"]+" given at property path "([\w\.\[\]]+)"/';

    /**
     * Matches an API Platform denormalization message and captures the offending attribute name:
     * `denormalize attribute "<name>" ... Expected argument of type`.
     */
    protected const string REGEX_DENORMALIZE_ATTRIBUTE = '/denormalize attribute "(\w+)".*Expected argument of type/';

    public function __construct(protected TranslatorInterface $translator)
    {
    }

    /**
     * PropertyAccessor throws a raw InvalidArgumentException when JSON:API ItemNormalizer tries to
     * assign a non-numeric string to a typed `?int`/`?float` property, and a failed nested
     * value-object denormalization surfaces as a TypeError whose previous PropertyAccess exception
     * carries the matchable message — so the whole exception chain is walked.
     */
    public function match(Throwable $exception): ?string
    {
        $detail = $this->matchLossyIntegerConversion($exception);

        for ($throwable = $exception; $detail === null && $throwable !== null; $throwable = $throwable->getPrevious()) {
            $detail = $this->matchPropertyTypeError($throwable->getMessage());
        }

        return $detail;
    }

    /**
     * Transforms raw API Platform denormalization error messages into the Spryker
     * validation format: "propertyName => This value should be of type numeric."
     *
     * For example, the raw message:
     *   Failed to denormalize attribute "quantity" value for class "...": Expected argument of type "?int", "string" given ...
     * Becomes:
     *   quantity => This value should be of type numeric.
     */
    public function transformDenormalizationMessage(string $message): string
    {
        if (!preg_match(static::REGEX_DENORMALIZE_ATTRIBUTE, $message, $matches)) {
            return $message;
        }

        $propertyName = $matches[1];

        return sprintf('%s => %s', $propertyName, $this->translateTypeMessage(static::TYPE_NAME_NUMERIC));
    }

    /**
     * A fractional number sent for an `int` property, rejected by
     * {@see \Spryker\ApiPlatform\PropertyAccess\LosslessIntegerPropertyAccessor}. The error names
     * the attribute without a parent prefix, the same shape a nested leaf type error has.
     */
    protected function matchLossyIntegerConversion(Throwable $exception): ?string
    {
        for ($throwable = $exception; $throwable !== null; $throwable = $throwable->getPrevious()) {
            if ($throwable instanceof LossyIntegerConversionException) {
                return sprintf(
                    '%s => %s',
                    $this->normalizePropertyPath($throwable->propertyPath),
                    $this->translateTypeMessage(static::TYPE_NAME_INTEGER),
                );
            }
        }

        return null;
    }

    /**
     * Matches the PropertyAccessor message
     * `Expected argument of type "<type>", "<given>" given at property path "<path>".`
     * and returns the legacy-formatted detail (or null when not a match).
     *
     * Numeric target types (`int`, `float`, optional variants) map to `should be of type numeric.`;
     * other targets fall back to `should be of type <type>.` mirroring legacy Glue behaviour.
     */
    protected function matchPropertyTypeError(string $message): ?string
    {
        if (!preg_match(static::REGEX_PROPERTY_ACCESS_TYPE_ERROR, $message, $matches)) {
            return null;
        }

        $expectedType = ltrim($matches[1], '?');
        $propertyPath = $this->normalizePropertyPath($matches[2]);
        $reportedType = match (true) {
            in_array($expectedType, ['int', 'integer', 'float', 'double'], true) => static::TYPE_NAME_NUMERIC,
            // A generated nested value object (e.g. `?PaymentSelection`) reaching this branch means
            // one of its sub-fields failed type denormalization; under `disable_type_enforcement`
            // the inner detail is lost and the whole object surfaces here. Report it as an object
            // type error (422) instead of leaking the generated FQCN to the API consumer.
            str_contains($expectedType, '\\') => 'object',
            default => $expectedType,
        };

        return sprintf('%s => %s', $propertyPath, $this->translateTypeMessage($reportedType));
    }

    /**
     * Converts Symfony's bracket property-path notation `parent[child][0]` to Spryker's dot notation `parent.child.0`.
     */
    protected function normalizePropertyPath(string $propertyPath): string
    {
        return rtrim(str_replace(['[', ']'], ['.', ''], $propertyPath), '.');
    }

    protected function translateTypeMessage(string $typeName): string
    {
        return $this->translateValidationMessage(static::MESSAGE_TEMPLATE_TYPE, ['{{ type }}' => $typeName]);
    }

    protected function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }
}
