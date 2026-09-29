<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

use Spryker\ApiPlatform\Validation\Trait\ValidationMessageTranslationTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Splits single-error validation responses (where all violations are concatenated
 * with newlines in one detail string) into separate error objects with code 901
 * and "property => message" format, matching the old REST API behavior.
 */
class ValidationErrorFormatNormalizer
{
    use ValidationMessageTranslationTrait;

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const string MESSAGE_TEMPLATE_FIELD_MISSING = 'This field is missing.';

    /**
     * Matches a validation detail that begins with a `property: ` prefix, where the property may be a
     * plain name, a Symfony bracket path (`parent[child][0]`) or a dotted cascade path (`parent.child`).
     */
    protected const string REGEX_DETAIL_PROPERTY_PREFIX = '/^[\w\[\].]+: /';

    public function __construct(protected TranslatorInterface $translator)
    {
    }

    /**
     * Returns the split errors, or null when the errors are not a single concatenated validation detail.
     *
     * @param array<int, array<string, mixed>> $errors
     * @param array<string>|null $submittedFields
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function normalize(array $errors, ?array $submittedFields): ?array
    {
        // Check if errors need reformatting: single error with "property: message" format.
        // Property may be a nested path produced by Collection/All constraints, e.g.
        // `parent[child][0][leaf]` (bracket notation) or, when the nested property is a typed
        // value object validated via an `Assert\Valid` cascade, dot notation `parent.child` —
        // accept word characters, bracket-segment and dot notation.
        if (count($errors) !== 1) {
            return null;
        }

        $detail = $errors[0]['detail'] ?? '';

        if (!is_string($detail) || $detail === '' || !preg_match(static::REGEX_DETAIL_PROPERTY_PREFIX, $detail)) {
            return null;
        }

        $lines = array_filter(explode("\n", $detail), static fn (string $line): bool => $line !== '');
        $normalizedErrors = [];

        foreach ($lines as $line) {
            $colonPos = strpos($line, ': ');
            $fieldName = $colonPos !== false ? substr($line, 0, $colonPos) : null;
            $message = $colonPos !== false ? substr($line, $colonPos + 2) : $line;

            if ($fieldName !== null) {
                // Convert Symfony's bracket path notation `parent[child][0]` to Spryker's
                // dot notation `parent.child.0` to match legacy Glue REST error format.
                $fieldName = $this->normalizePropertyPath($fieldName);
            }

            // Detect missing fields: "not blank/null" errors for fields not submitted in the request.
            // Only top-level fields are considered submitted — nested paths bypass this check.
            if (
                $fieldName !== null
                && $submittedFields !== null
                && !str_contains($fieldName, '.')
                && !in_array($fieldName, $submittedFields, true)
            ) {
                $message = $this->translateValidationMessage(static::MESSAGE_TEMPLATE_FIELD_MISSING);
            }

            $formattedDetail = $fieldName !== null
                ? sprintf('%s => %s', $fieldName, $message)
                : $message;

            $normalizedErrors[] = [
                'code' => static::ERROR_CODE_VALIDATION,
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => $formattedDetail,
            ];
        }

        return $normalizedErrors;
    }

    /**
     * Converts Symfony Validator's bracket property-path notation to Spryker's dot notation.
     * Examples:
     *  - `parent` → `parent`
     *  - `parent[child]` → `parent.child`
     *  - `parent[items][0][sku]` → `parent.items.0.sku`
     */
    protected function normalizePropertyPath(string $propertyPath): string
    {
        return rtrim(str_replace(['[', ']'], ['.', ''], $propertyPath), '.');
    }

    protected function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }
}
