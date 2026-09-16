<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Generated\Shared\Transfer\LocaleTransfer;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\Service\Locale\LocaleServiceInterface;
use Spryker\Zed\Locale\Business\LocaleFacadeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the request locale from the `Accept-Language` header for Backend API requests, so
 * downstream state classes, serializers and error translation operate in the caller's language.
 *
 * The backend counterpart of {@see AcceptLanguageLocaleSubscriber}, and deliberately not the same
 * class: the storefront negotiates against the CURRENT STORE's available locales, and a Backend API
 * request has no store context at all — it is an operator-facing API that spans every store. The
 * candidate list here is therefore the system-configured one,
 * {@see \Spryker\Zed\Locale\Business\LocaleFacadeInterface::getSupportedLocaleCodes()}, which reads
 * from configuration rather than from a store or the database, and no `StoreTransfer` is put on the
 * request.
 *
 * What this enables, none of which worked on the Backend API before:
 * - {@see \Spryker\ApiPlatform\State\Trait\LocaleAwareTrait} — `hasLocale()` / `getLocale()` /
 *   `findLocaleName()` in backend providers and processors, which until now could only ever return
 *   null or throw.
 * - {@see \Spryker\ApiPlatform\State\TranslatingErrorProvider} — it reads the `_locale` attribute
 *   this subscriber sets and skips translation entirely when it is absent.
 * - `$request->setLocale()` puts the Symfony translator in the same language, for anything that
 *   translates through it.
 */
#[\Spryker\ApiPlatform\Attribute\ApiType(types: ['backend'])]
class BackendAcceptLanguageLocaleSubscriber implements EventSubscriberInterface
{
    protected const string ACCEPT_LANGUAGE_HEADER = 'Accept-Language';

    protected const string FALLBACK_LOCALE_NAME = 'en_US';

    protected const string DEFAULT_LANGUAGE_CODE = 'en';

    public function __construct(
        protected LocaleFacadeInterface $localeFacade,
        protected LocaleServiceInterface $localeService,
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $localeName = $this->resolveLocaleName(
            $request->headers->get(static::ACCEPT_LANGUAGE_HEADER) ?? '',
        );

        $request->attributes->set(RequestAttribute::LOCALE, $localeName);
        $request->setLocale($localeName);

        $request->attributes->set(
            RequestAttribute::LOCALE_TRANSFER,
            (new LocaleTransfer())->setLocaleName($localeName),
        );
    }

    protected function resolveLocaleName(string $acceptLanguageHeader): string
    {
        $localeNamesByLanguageCode = $this->getLocaleNamesIndexedByLanguageCode();

        if ($acceptLanguageHeader === '' || $localeNamesByLanguageCode === []) {
            return $this->getDefaultLocaleName($localeNamesByLanguageCode);
        }

        $acceptLanguageTransfer = $this->localeService->getAcceptLanguage(
            $acceptLanguageHeader,
            array_keys($localeNamesByLanguageCode),
        );

        $languageCode = $acceptLanguageTransfer?->getType();

        if ($languageCode === null) {
            return $this->getDefaultLocaleName($localeNamesByLanguageCode);
        }

        return $localeNamesByLanguageCode[$languageCode] ?? $this->getDefaultLocaleName($localeNamesByLanguageCode);
    }

    /**
     * @return array<string, string>
     */
    protected function getLocaleNamesIndexedByLanguageCode(): array
    {
        $localeNamesByLanguageCode = [];

        foreach ($this->localeFacade->getSupportedLocaleCodes() as $localeName) {
            $localeNamesByLanguageCode[explode('_', $localeName)[0]] = $localeName;
        }

        return $localeNamesByLanguageCode;
    }

    /**
     * @param array<string, string> $localeNamesByLanguageCode
     */
    protected function getDefaultLocaleName(array $localeNamesByLanguageCode): string
    {
        return $localeNamesByLanguageCode[static::DEFAULT_LANGUAGE_CODE]
            ?? (array_values($localeNamesByLanguageCode)[0] ?? static::FALLBACK_LOCALE_NAME);
    }
}
