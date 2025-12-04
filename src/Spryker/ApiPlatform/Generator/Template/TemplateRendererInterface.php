<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Template;

interface TemplateRendererInterface
{
    /**
     * @param array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, properties: array<array{name: string, type: string, phpType: string, attributes: string, description: string}>, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}|array $templateData
     *
     * @return string
     */
    public function render(array $templateData): string;
}
