<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Serializer;

use Spryker\Client\GlossaryStorage\GlossaryStorageClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Decorates the constraint violation normalizer to translate glossary-keyed
 * error messages into the request locale before returning them to the client.
 * Also adds Glue-compatible `code` and `status` fields to each error entry.
 */
class TranslatingConstraintViolationListNormalizer implements NormalizerInterface
{
    protected const string ERRORS_KEY = 'errors';

    protected const string DETAIL_KEY = 'detail';

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const int STATUS_UNPROCESSABLE_ENTITY = 422;

    protected const string NOT_BLANK_TEMPLATE = 'This value should not be blank.';

    protected const string FIELD_MISSING_MESSAGE = 'This field is missing.';

    protected const string VALIDATORS_DOMAIN = 'validators';

    protected const string ENGLISH_LOCALE = 'en';

    protected const string PLURAL_COUNT_KEY = '%count%';

    public function __construct(
        protected NormalizerInterface $decorated,
        protected GlossaryStorageClientInterface $glossaryStorageClient,
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

    protected function extractFieldName(array $error): ?string
    {
        if (!isset($error['source']['pointer'])) {
            return null;
        }

        $pointer = $error['source']['pointer'];
        $segments = explode('/', $pointer);

        return end($segments) ?: null;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    /**
     * @return array<string, bool>
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

        foreach ($object as $index => $violation) {
            if ($violation->getInvalidValue() === null && !in_array($violation->getPropertyPath(), $submittedFields, true)) {
                $messages[$index] = static::FIELD_MISSING_MESSAGE;

                continue;
            }

            $messages[$index] = $this->translateViolation($violation);
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

    /**
     * Interpolates the violation message template with parameters directly,
     * bypassing the Symfony translator to preserve the original constraint message.
     * Using the translator would route through the `validators` domain which
     * rewrites some messages (e.g. "This is not a valid UUID." → "This value is not a valid UUID.").
     */
    protected function translateViolation(ConstraintViolationInterface $violation): string
    {
        $template = $violation->getMessageTemplate();
        $parameters = $violation->getParameters();
        $plural = $violation->getPlural();

        // Resolve plural form for constraints with singular|plural alternatives (e.g. Length)
        if ($plural !== null && str_contains($template, '|')) {
            $alternatives = explode('|', $template);
            $template = ((int)$plural === 1) ? $alternatives[0] : ($alternatives[1] ?? $alternatives[0]);
        }

        return strtr($template, $parameters);
    }
}
