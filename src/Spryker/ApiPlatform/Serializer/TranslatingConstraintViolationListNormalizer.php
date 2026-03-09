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

/**
 * Decorates the constraint violation normalizer to translate glossary-keyed
 * error messages into the request locale before returning them to the client.
 */
class TranslatingConstraintViolationListNormalizer implements NormalizerInterface
{
    protected const string LOCALE_ATTRIBUTE = '_locale';

    protected const string ERRORS_KEY = 'errors';

    protected const string DETAIL_KEY = 'detail';

    public function __construct(
        protected NormalizerInterface $decorated,
        protected GlossaryStorageClientInterface $glossaryStorageClient,
        protected RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        /** @var array<string, mixed> $normalized */
        $normalized = $this->decorated->normalize($object, $format, $context);

        $locale = $this->resolveLocale();

        if ($locale === null) {
            return $normalized;
        }

        if (!isset($normalized[static::ERRORS_KEY]) || !is_array($normalized[static::ERRORS_KEY])) {
            return $normalized;
        }

        foreach ($normalized[static::ERRORS_KEY] as $index => $error) {
            if (!isset($error[static::DETAIL_KEY]) || !is_string($error[static::DETAIL_KEY])) {
                continue;
            }

            $normalized[static::ERRORS_KEY][$index][static::DETAIL_KEY] = $this->glossaryStorageClient->translate(
                $error[static::DETAIL_KEY],
                $locale,
            );
        }

        return $normalized;
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

    protected function resolveLocale(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        return $request->attributes->get(static::LOCALE_ATTRIBUTE);
    }
}
