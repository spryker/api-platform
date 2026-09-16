<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\AcceptLanguageTransfer;
use Generated\Shared\Transfer\LocaleTransfer;
use Spryker\ApiPlatform\EventSubscriber\BackendAcceptLanguageLocaleSubscriber;
use Spryker\Service\Locale\Dependency\External\LocaleToLanguageNegotiatorAdapter;
use Spryker\Service\Locale\LocaleServiceInterface;
use Spryker\Service\Locale\Mapper\AcceptLanguageMapper;
use Spryker\Service\Locale\Negotiator\AcceptLanguageNegotiator;
use Spryker\Service\Locale\Negotiator\AcceptLanguageNegotiatorInterface;
use Spryker\Zed\Locale\Business\LocaleFacadeInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group BackendAcceptLanguageLocaleSubscriberTest
 * Add your own group annotations below this line
 */
class BackendAcceptLanguageLocaleSubscriberTest extends Unit
{
    protected const string LOCALE_ATTRIBUTE = '_locale';

    protected const string LOCALE_TRANSFER_ATTRIBUTE = 'LocaleTransfer';

    protected const string LOCALE_NAME_EN_US = 'en_US';

    protected const string LOCALE_NAME_DE_DE = 'de_DE';

    /**
     * @var array<string>
     */
    protected const array SUPPORTED_LOCALE_CODES = [self::LOCALE_NAME_EN_US, self::LOCALE_NAME_DE_DE];

    protected ApiUnitTester $tester;

    /**
     * @dataProvider provideAcceptLanguageHeaders
     */
    public function testOnKernelRequestResolvesTheLocaleFromTheAcceptLanguageHeader(
        ?string $acceptLanguageHeader,
        string $expectedLocaleName,
    ): void {
        // Arrange
        $event = $this->createRequestEvent($acceptLanguageHeader);

        // Act
        $this->createSubscriber()->onKernelRequest($event);

        // Assert
        $this->assertSame($expectedLocaleName, $event->getRequest()->attributes->get(static::LOCALE_ATTRIBUTE));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public function provideAcceptLanguageHeaders(): iterable
    {
        yield 'German header resolves to the configured German locale' => ['de-DE,de;q=0.9', static::LOCALE_NAME_DE_DE];
        yield 'bare language tag resolves to the configured locale of that language' => ['de', static::LOCALE_NAME_DE_DE];
        yield 'English header resolves to the configured English locale' => ['en-US,en;q=0.9', static::LOCALE_NAME_EN_US];
        yield 'header preferring an unconfigured language falls back to English' => ['fr-FR,fr;q=0.9', static::LOCALE_NAME_EN_US];
        yield 'missing header falls back to English' => [null, static::LOCALE_NAME_EN_US];
        yield 'empty header falls back to English' => ['', static::LOCALE_NAME_EN_US];
    }

    /**
     * `LocaleAwareTrait` reads the transfer, `TranslatingErrorProvider` reads `_locale`, and the
     * Symfony translator reads what `setLocale()` set — all three have to agree.
     */
    public function testOnKernelRequestPublishesTheResolvedLocaleEverywhereItIsReadFrom(): void
    {
        // Arrange
        $event = $this->createRequestEvent('de-DE,de;q=0.9');

        // Act
        $this->createSubscriber()->onKernelRequest($event);

        // Assert
        $request = $event->getRequest();
        $localeTransfer = $request->attributes->get(static::LOCALE_TRANSFER_ATTRIBUTE);

        $this->assertInstanceOf(LocaleTransfer::class, $localeTransfer);
        $this->assertSame(static::LOCALE_NAME_DE_DE, $localeTransfer->getLocaleName());
        $this->assertSame(static::LOCALE_NAME_DE_DE, $request->attributes->get(static::LOCALE_ATTRIBUTE));
        $this->assertSame(static::LOCALE_NAME_DE_DE, $request->getLocale());
    }

    /**
     * A Backend API request has no store, so the candidate list is the system-configured one. An
     * installation that configures neither English nor anything else must still yield a usable
     * locale rather than an empty string.
     */
    public function testOnKernelRequestFallsBackToTheFirstConfiguredLocaleWhenEnglishIsNotConfigured(): void
    {
        // Arrange
        $event = $this->createRequestEvent('fr-FR,fr;q=0.9');

        // Act
        $this->createSubscriber(['de_DE', 'pl_PL'])->onKernelRequest($event);

        // Assert
        $this->assertSame(static::LOCALE_NAME_DE_DE, $event->getRequest()->attributes->get(static::LOCALE_ATTRIBUTE));
    }

    public function testOnKernelRequestFallsBackToEnglishWhenNoLocaleIsConfiguredAtAll(): void
    {
        // Arrange
        $event = $this->createRequestEvent('de-DE,de;q=0.9');

        // Act
        $this->createSubscriber([])->onKernelRequest($event);

        // Assert
        $this->assertSame(static::LOCALE_NAME_EN_US, $event->getRequest()->attributes->get(static::LOCALE_ATTRIBUTE));
    }

    /**
     * @param array<string>|null $supportedLocaleCodes
     */
    protected function createSubscriber(?array $supportedLocaleCodes = null): BackendAcceptLanguageLocaleSubscriber
    {
        $localeFacadeMock = $this->createMock(LocaleFacadeInterface::class);
        $localeFacadeMock->method('getSupportedLocaleCodes')
            ->willReturn($supportedLocaleCodes ?? static::SUPPORTED_LOCALE_CODES);

        return new BackendAcceptLanguageLocaleSubscriber($localeFacadeMock, $this->createLocaleService());
    }

    protected function createRequestEvent(?string $acceptLanguageHeader): RequestEvent
    {
        $request = new Request();

        if ($acceptLanguageHeader !== null) {
            $request->headers->set('Accept-Language', $acceptLanguageHeader);
        }

        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    /**
     * The real negotiator behind the service interface: `LocaleService` itself extends a kernel base
     * class that needs a full container, which a unit test has no business bootstrapping just to
     * exercise header negotiation.
     */
    protected function createLocaleService(): LocaleServiceInterface
    {
        $acceptLanguageNegotiator = new AcceptLanguageNegotiator(new LocaleToLanguageNegotiatorAdapter(), new AcceptLanguageMapper());

        return new class ($acceptLanguageNegotiator) implements LocaleServiceInterface {
            public function __construct(protected AcceptLanguageNegotiatorInterface $acceptLanguageNegotiator)
            {
            }

            public function getAcceptLanguage(string $acceptLanguageHeader, array $priorities, bool $strict = false): ?AcceptLanguageTransfer
            {
                return $this->acceptLanguageNegotiator->getAcceptLanguage($acceptLanguageHeader, $priorities, $strict);
            }
        };
    }
}
