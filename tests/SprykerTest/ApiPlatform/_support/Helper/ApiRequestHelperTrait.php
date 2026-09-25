<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Module;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives a Codeception helper the same request seam a test reaches through the actor, so a request
 * builder that used to live in a test trait moves into a helper with its body unchanged.
 *
 * {@see ApiRequestHelper} has to be enabled on the same suite.
 */
trait ApiRequestHelperTrait
{
    /**
     * @param array<string, string> $headers
     */
    protected function handleApiRequest(string $method, string $uri, ?string $content = null, array $headers = []): Response
    {
        return $this->getApiRequestHelper()->handleApiRequest($method, $uri, $content, $headers);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function encodeJsonApiBody(string $type, array $attributes, ?string $id = null): string
    {
        return $this->getApiRequestHelper()->encodeJsonApiBody($type, $attributes, $id);
    }

    protected function addDefaultRequestHeader(string $name, string $value): void
    {
        $this->getApiRequestHelper()->addDefaultRequestHeader($name, $value);
    }

    protected function getApiRequestHelper(): ApiRequestHelper
    {
        /** @var \SprykerTest\ApiPlatform\Helper\ApiRequestHelper $apiRequestHelper */
        $apiRequestHelper = $this->getModule('\\' . ApiRequestHelper::class);

        return $apiRequestHelper;
    }

    abstract protected function getModule(string $name): Module;
}
