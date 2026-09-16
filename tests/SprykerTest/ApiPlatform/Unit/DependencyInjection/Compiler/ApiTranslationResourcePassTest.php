<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection\Compiler;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\DependencyInjection\Compiler\ApiTranslationResourcePass;
use Spryker\ApiPlatform\Translation\ApiCsvFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Translation\Translator;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group Compiler
 * @group ApiTranslationResourcePassTest
 * Add your own group annotations below this line
 */
class ApiTranslationResourcePassTest extends Unit
{
    protected const string TRANSLATOR_SERVICE_ID = 'translator.default';

    protected const string SOURCE_DIRECTORIES_PARAMETER = 'spryker_api_platform.source_directories';

    protected const string VALIDATORS_DOMAIN = 'validators';

    protected ?string $sourceDirectory = null;

    protected function _after(): void
    {
        if ($this->sourceDirectory !== null) {
            exec(sprintf('rm -rf %s', escapeshellarg($this->sourceDirectory)));
            $this->sourceDirectory = null;
        }
    }

    public function testRegistersEveryModuleTranslationFileWithTheTranslator(): void
    {
        // Arrange
        $sourceDirectory = $this->createModuleTranslations(['Alpha' => ['en_US', 'de_DE'], 'Beta' => ['de_DE']]);
        $container = $this->createContainer([$sourceDirectory]);

        // Act
        (new ApiTranslationResourcePass())->process($container);

        // Assert
        $calls = $container->getDefinition(static::TRANSLATOR_SERVICE_ID)->getMethodCalls();
        $this->assertCount(3, $calls);

        foreach ($calls as [$method, $arguments]) {
            $this->assertSame('addResource', $method);
            $this->assertSame(ApiCsvFileLoader::FORMAT, $arguments[0]);
            $this->assertSame(static::VALIDATORS_DOMAIN, $arguments[3]);
        }
    }

    public function testRegistersEachFileUnderTheLocaleItsNameDeclares(): void
    {
        // Arrange
        $sourceDirectory = $this->createModuleTranslations(['Alpha' => ['en_US', 'de_DE']]);
        $container = $this->createContainer([$sourceDirectory]);

        // Act
        (new ApiTranslationResourcePass())->process($container);

        // Assert
        $localesByFile = [];

        foreach ($container->getDefinition(static::TRANSLATOR_SERVICE_ID)->getMethodCalls() as [, $arguments]) {
            $localesByFile[basename($arguments[1])] = $arguments[2];
        }

        ksort($localesByFile);
        $this->assertSame(['de_DE.csv' => 'de_DE', 'en_US.csv' => 'en_US'], $localesByFile);
    }

    public function testRegisteredPathsAreAbsolute(): void
    {
        // Arrange
        $sourceDirectory = $this->createModuleTranslations(['Alpha' => ['de_DE']]);
        $container = $this->createContainer([$sourceDirectory]);

        // Act
        (new ApiTranslationResourcePass())->process($container);

        // Assert
        [, $arguments] = $container->getDefinition(static::TRANSLATOR_SERVICE_ID)->getMethodCalls()[0];
        $this->assertStringStartsWith('/', $arguments[1]);
        $this->assertFileExists($arguments[1]);
    }

    /**
     * A file that does not name a locale the way the Back Office files do must not be picked up, so
     * a stray CSV in the directory cannot land in the catalogue under a nonsense locale.
     */
    public function testIgnoresFilesThatDoNotNameALocale(): void
    {
        // Arrange
        $sourceDirectory = $this->createModuleTranslations(['Alpha' => ['de_DE']]);
        file_put_contents($sourceDirectory . '/Alpha/data/translation/Api/readme.csv', "\"a\",\"b\"\n");
        file_put_contents($sourceDirectory . '/Alpha/data/translation/Api/de.csv', "\"a\",\"b\"\n");
        $container = $this->createContainer([$sourceDirectory]);

        // Act
        (new ApiTranslationResourcePass())->process($container);

        // Assert
        $this->assertCount(1, $container->getDefinition(static::TRANSLATOR_SERVICE_ID)->getMethodCalls());
    }

    public function testDoesNothingWithoutATranslator(): void
    {
        // Arrange
        $sourceDirectory = $this->createModuleTranslations(['Alpha' => ['de_DE']]);
        $container = new ContainerBuilder();
        $container->setParameter(static::SOURCE_DIRECTORIES_PARAMETER, [$sourceDirectory]);

        // Act
        (new ApiTranslationResourcePass())->process($container);

        // Assert
        $this->assertFalse($container->hasDefinition(static::TRANSLATOR_SERVICE_ID));
    }

    public function testDoesNothingWithoutTheSourceDirectoriesParameter(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setDefinition(static::TRANSLATOR_SERVICE_ID, new Definition(Translator::class));

        // Act
        (new ApiTranslationResourcePass())->process($container);

        // Assert
        $this->assertSame([], $container->getDefinition(static::TRANSLATOR_SERVICE_ID)->getMethodCalls());
    }

    /**
     * @param array<string> $sourceDirectories
     */
    protected function createContainer(array $sourceDirectories): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(static::TRANSLATOR_SERVICE_ID, new Definition(Translator::class));
        $container->setParameter(static::SOURCE_DIRECTORIES_PARAMETER, $sourceDirectories);

        return $container;
    }

    /**
     * @param array<string, array<string>> $localesByModule
     */
    protected function createModuleTranslations(array $localesByModule): string
    {
        $this->sourceDirectory = sprintf('%s/api-translation-pass-%s', sys_get_temp_dir(), uniqid());

        foreach ($localesByModule as $module => $locales) {
            $directory = sprintf('%s/%s/data/translation/Api', $this->sourceDirectory, $module);
            mkdir($directory, 0777, true);

            foreach ($locales as $locale) {
                file_put_contents(sprintf('%s/%s.csv', $directory, $locale), "\"Store name is required\",\"x\"\n");
            }
        }

        return $this->sourceDirectory;
    }
}
