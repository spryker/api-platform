<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Serializer;

use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Decorates the constraint violation normalizer to translate constraint messages into the request
 * locale before returning them to the client, and to add Glue-compatible `code` and `status` fields
 * to each error entry.
 *
 * Translation goes through the Symfony translator's `validators` domain, which is where the
 * framework ships the constraint messages in every locale it supports — so
 * `Accept-Language: de` answers "Dieser Wert sollte nicht leer sein." with no catalogue of our own to
 * maintain. The locale comes from the request attribute the locale subscribers resolve from
 * `Accept-Language`; a region-qualified locale falls back to its language catalogue (`de_DE` -> `de`)
 * inside the translator.
 *
 * The messages are translated from their TEMPLATE (`This value should not be blank.`), never from
 * the already-interpolated text, because the template is the catalogue key. Parameters are handed to
 * the translator rather than interpolated first, so that `%count%`-driven plural forms select the
 * right alternative in the target language.
 */
class TranslatingConstraintViolationListNormalizer implements NormalizerInterface
{
    protected const string ERRORS_KEY = 'errors';

    protected const string DETAIL_KEY = 'detail';

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const int STATUS_UNPROCESSABLE_ENTITY = 422;

    /**
     * Symfony's own `validators.<locale>.xlf` catalogues carry this one too, so a missing field
     * reports in the caller's language like every constraint message around it.
     */
    protected const string FIELD_MISSING_MESSAGE = 'This field is missing.';

    protected const string VALIDATORS_DOMAIN = 'validators';

    public function __construct(
        protected NormalizerInterface $decorated,
        protected RequestStack $requestStack,
        protected TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        // Extract original English messages per violation index
        $originalMessagesByIndex = $this->extractOriginalMessages($object);

        /** @var array<string, mixed> $normalized */
        $normalized = $this->decorated->normalize($object, $format, $context);

        if (!isset($normalized[static::ERRORS_KEY]) || !is_array($normalized[static::ERRORS_KEY])) {
            return $normalized;
        }

        foreach ($normalized[static::ERRORS_KEY] as $index => $error) {
            if (isset($originalMessagesByIndex[$index])) {
                $error[static::DETAIL_KEY] = $originalMessagesByIndex[$index];
            }

            $normalized[static::ERRORS_KEY][$index] = $this->enrichError($error);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $error
     *
     * @return array<string, mixed>
     */
    protected function enrichError(array $error): array
    {
        $error['code'] = static::ERROR_CODE_VALIDATION;
        $error['status'] = static::STATUS_UNPROCESSABLE_ENTITY;

        $detail = $error[static::DETAIL_KEY] ?? '';
        $fieldName = $this->extractFieldName($error);

        if ($fieldName !== null && is_string($detail)) {
            $error[static::DETAIL_KEY] = sprintf('%s => %s', $fieldName, $detail);
        }

        unset($error['source']);

        return $error;
    }

    /**
     * @param array<string, mixed> $error
     */
    protected function extractFieldName(array $error): ?string
    {
        if (!isset($error['source']['pointer'])) {
            return null;
        }

        $pointer = $error['source']['pointer'];
        $segments = explode('/', $pointer);

        return end($segments) ?: null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // @phpstan-ignore arguments.count (symfony/serializer 6.4 keeps $context undeclared on the interface; 7.4 declares it)
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }

    /**
     * Builds a per-index map of original English messages from the violation list.
     * Handles the distinction between null values (field missing) and empty strings (field blank).
     *
     * @return array<int, string>
     */
    protected function extractOriginalMessages(mixed $object): array
    {
        $messages = [];

        if (!$object instanceof ConstraintViolationListInterface) {
            return $messages;
        }

        $submittedFields = $this->resolveSubmittedFields();

        $locale = $this->resolveLocale();

        foreach ($object as $index => $violation) {
            if ($violation->getInvalidValue() === null && !in_array($violation->getPropertyPath(), $submittedFields, true)) {
                $messages[$index] = $this->translate(static::FIELD_MISSING_MESSAGE, [], $locale);

                continue;
            }

            $messages[$index] = $this->translate(
                $violation->getMessageTemplate(),
                $violation->getParameters(),
                $locale,
            );
        }

        return $messages;
    }

    /**
     * Resolves the list of field names explicitly submitted in the request body.
     * Used to distinguish between "field absent" (missing) and "field sent as null" (blank).
     *
     * @return array<string>
     */
    protected function resolveSubmittedFields(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return [];
        }

        $body = json_decode((string)$request->getContent(), true);

        if (!is_array($body) || !isset($body['data']['attributes'])) {
            return [];
        }

        /** @var array<string> */
        return array_keys($body['data']['attributes']);
    }

    protected function resolveLocale(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->attributes->get(RequestAttribute::LOCALE);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function translate(string $messageTemplate, array $parameters, ?string $locale): string
    {
        return $this->translator->trans($messageTemplate, $parameters, static::VALIDATORS_DOMAIN, $locale);
    }
}
