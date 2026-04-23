<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ApiResource\Error;
use ApiPlatform\State\ProviderInterface;
use Spryker\Client\GlossaryStorage\GlossaryStorageClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates the error state provider to translate glossary-keyed error titles
 * and details into the request locale. Compound messages joined by "; " are
 * split, translated individually, and re-joined.
 *
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
class TranslatingErrorProvider implements ProviderInterface
{
    protected const string LOCALE_ATTRIBUTE = '_locale';

    protected const string MESSAGE_SEPARATOR = '; ';

    /**
     * @param \ApiPlatform\State\ProviderInterface<object> $decorated
     */
    public function __construct(
        protected ProviderInterface $decorated,
        protected GlossaryStorageClientInterface $glossaryStorageClient,
        protected RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $result = $this->decorated->provide($operation, $uriVariables, $context);

        if (!($result instanceof Error)) {
            return $result;
        }

        $locale = $this->resolveLocale();

        if ($locale === null) {
            return $result;
        }

        $this->translateField($result->getTitle(), $locale, $result->setTitle(...));
        $this->translateField($result->getDetail(), $locale, $result->setDetail(...));

        return $result;
    }

    /**
     * @param callable(?string): void $setter
     *
     * @return void
     */
    protected function translateField(?string $value, string $locale, callable $setter): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $setter($this->translateMessage($value, $locale));
    }

    protected function resolveLocale(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        return $request->attributes->get(static::LOCALE_ATTRIBUTE);
    }

    protected function translateMessage(string $message, string $locale): string
    {
        if (!str_contains($message, static::MESSAGE_SEPARATOR)) {
            return $this->glossaryStorageClient->translate($message, $locale);
        }

        /** @var non-empty-string $separator */
        $separator = static::MESSAGE_SEPARATOR;
        $parts = explode($separator, $message);
        $translatedParts = [];

        foreach ($parts as $part) {
            $translatedParts[] = $this->glossaryStorageClient->translate($part, $locale);
        }

        return implode(static::MESSAGE_SEPARATOR, $translatedParts);
    }
}
