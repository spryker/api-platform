<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use Spryker\ApiPlatform\Translation\ApiCsvFileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers every module's `data/translation/Api/<locale>.csv` with the translator, in the
 * `validators` domain.
 *
 * Only messages a module declares itself belong in those files. Anything that reuses a Symfony
 * constraint's default text is already translated by `symfony/validator`'s own
 * `validators.<locale>.xlf` in every locale it ships — so overriding such a message in a
 * `*.validation.yml`, or repeating it here, would replace a maintained translation with one we have
 * to maintain.
 *
 * The files are addressed by convention rather than through `framework.translator.paths`, because
 * Symfony's own discovery keys on a `<domain>.<locale>.<format>` filename, and a module that ships
 * translations should not also have to be named in project configuration to be found.
 */
class ApiTranslationResourcePass implements CompilerPassInterface
{
    protected const string TRANSLATOR_SERVICE_ID = 'translator.default';

    protected const string SOURCE_DIRECTORIES_PARAMETER = 'spryker_api_platform.source_directories';

    protected const string TRANSLATION_DIRECTORY = 'data/translation/Api';

    protected const string LOCALE_FILE_PATTERN = '[a-z][a-z]_[A-Z][A-Z].csv';

    protected const string VALIDATORS_DOMAIN = 'validators';

    protected const string ADD_RESOURCE_METHOD = 'addResource';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(static::TRANSLATOR_SERVICE_ID)) {
            return;
        }

        if (!$container->hasParameter(static::SOURCE_DIRECTORIES_PARAMETER)) {
            return;
        }

        $sourceDirectories = $container->getParameter(static::SOURCE_DIRECTORIES_PARAMETER);

        if (!is_array($sourceDirectories)) {
            return;
        }

        $translatorDefinition = $container->getDefinition(static::TRANSLATOR_SERVICE_ID);

        foreach ($this->findTranslationFiles($sourceDirectories) as $locale => $files) {
            foreach ($files as $file) {
                $container->addResource(new FileResource($file));
                $translatorDefinition->addMethodCall(
                    static::ADD_RESOURCE_METHOD,
                    [ApiCsvFileLoader::FORMAT, $file, $locale, static::VALIDATORS_DOMAIN],
                );
            }
        }
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<string, array<string>>
     */
    protected function findTranslationFiles(array $sourceDirectories): array
    {
        $filesByLocale = [];

        foreach ($sourceDirectories as $sourceDirectory) {
            $pattern = sprintf(
                '%s/*/%s/%s',
                rtrim((string)$sourceDirectory, '/'),
                static::TRANSLATION_DIRECTORY,
                static::LOCALE_FILE_PATTERN,
            );

            foreach (glob($pattern) ?: [] as $file) {
                $absolutePath = realpath($file);

                if ($absolutePath === false) {
                    continue;
                }

                $filesByLocale[basename($file, '.csv')][] = $absolutePath;
            }
        }

        return $filesByLocale;
    }
}
