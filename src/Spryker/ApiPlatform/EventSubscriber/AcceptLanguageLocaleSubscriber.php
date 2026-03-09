<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Spryker\Client\Store\StoreClientInterface;
use Spryker\Service\Locale\LocaleServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the request locale from the `Accept-Language` header against the current store's
 * available locales and sets it on the Symfony request, so downstream controllers and
 * serializers operate in the correct language context.
 */
#[\Spryker\ApiPlatform\Attribute\ApiType(types: ['storefront'])]
class AcceptLanguageLocaleSubscriber implements EventSubscriberInterface
{
    protected const string LOCALE_ATTRIBUTE = '_locale';

    protected const string ACCEPT_LANGUAGE_HEADER = 'Accept-Language';

    public function __construct(
        protected StoreClientInterface $storeClient,
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
        $acceptLanguageHeader = $request->headers->get(static::ACCEPT_LANGUAGE_HEADER) ?? '';

        $locale = $this->resolveLocale($acceptLanguageHeader);
        $request->attributes->set(static::LOCALE_ATTRIBUTE, $locale);
        $request->setLocale($locale);
    }

    protected function resolveLocale(string $acceptLanguageHeader): string
    {
        $storeTransfer = $this->storeClient->getCurrentStore();

        $availableLocales = $storeTransfer->getAvailableLocaleIsoCodes();
        $localesByLanguageCode = $this->getLocaleCodesIndexedByLanguageCode($availableLocales);

        if ($acceptLanguageHeader === '') {
            return $this->getDefaultLocale($localesByLanguageCode);
        }

        $acceptLanguageTransfer = $this->localeService->getAcceptLanguage(
            $acceptLanguageHeader,
            array_keys($localesByLanguageCode),
        );

        if (!$acceptLanguageTransfer || $acceptLanguageTransfer->getType() === null) {
            return $this->getDefaultLocale($localesByLanguageCode);
        }

        return $localesByLanguageCode[$acceptLanguageTransfer->getType()]
            ?? $this->getDefaultLocale($localesByLanguageCode);
    }

    /**
     * @param array<string> $localeCodes
     *
     * @return array<string, string>
     */
    protected function getLocaleCodesIndexedByLanguageCode(array $localeCodes): array
    {
        $indexedLocaleCodes = [];

        foreach ($localeCodes as $localeCode) {
            $languageCode = explode('_', $localeCode)[0];
            $indexedLocaleCodes[$languageCode] = $localeCode;
        }

        return $indexedLocaleCodes;
    }

    /**
     * @param array<string, string> $localesByLanguageCode
     */
    protected function getDefaultLocale(array $localesByLanguageCode): string
    {
        return array_values($localesByLanguageCode)[0] ?? 'en_US';
    }
}
