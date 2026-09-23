<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation\Trait;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates a constraint message TEMPLATE, for messages synthesized outside the validator.
 *
 * @see \Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber::onKernelRequestSetValidationLocale()
 */
trait ValidationMessageTranslationTrait
{
    protected const string VALIDATORS_DOMAIN = 'validators';

    abstract protected function getTranslator(): TranslatorInterface;

    /**
     * @param array<string, string> $parameters
     */
    protected function translateValidationMessage(string $messageTemplate, array $parameters = []): string
    {
        return $this->getTranslator()->trans($messageTemplate, $parameters, static::VALIDATORS_DOMAIN);
    }
}
