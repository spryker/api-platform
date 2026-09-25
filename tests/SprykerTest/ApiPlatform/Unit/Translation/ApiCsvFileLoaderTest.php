<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Translation;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Translation\ApiCsvFileLoader;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Translation
 * @group ApiCsvFileLoaderTest
 * Add your own group annotations below this line
 */
class ApiCsvFileLoaderTest extends Unit
{
    protected const string LOCALE = 'de_DE';

    protected const string DOMAIN = 'validators';

    /**
     * @var array<string>
     */
    protected array $temporaryFiles = [];

    protected function _after(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->temporaryFiles = [];
    }

    public function testReadsCommaSeparatedMessages(): void
    {
        // Arrange
        $file = $this->createCsvFile("\"This value should be of type bool.\",\"Dieser Wert sollte vom Typ bool sein.\"\n");

        // Act
        $catalogue = (new ApiCsvFileLoader())->load($file, static::LOCALE, static::DOMAIN);

        // Assert
        $this->assertSame(
            'Dieser Wert sollte vom Typ bool sein.',
            $catalogue->get('This value should be of type bool.', static::DOMAIN),
        );
    }

    public function testTreatsASemicolonAsTextRatherThanASeparator(): void
    {
        // Arrange
        $file = $this->createCsvFile("\"a;b\",\"c;d\"\n");

        // Act
        $catalogue = (new ApiCsvFileLoader())->load($file, static::LOCALE, static::DOMAIN);

        // Assert
        $this->assertSame('c;d', $catalogue->get('a;b', static::DOMAIN));
    }

    public function testReadsAQuotedMessageContainingACommaAndQuotes(): void
    {
        // Arrange
        $source = 'This value should be a boolean, or the string "true" or "false".';
        $target = 'Dieser Wert muss ein boolescher Wert sein oder die Zeichenkette "true" oder "false".';
        $file = $this->createCsvFile(sprintf(
            "\"%s\",\"%s\"\n",
            str_replace('"', '""', $source),
            str_replace('"', '""', $target),
        ));

        // Act
        $catalogue = (new ApiCsvFileLoader())->load($file, static::LOCALE, static::DOMAIN);

        // Assert
        $this->assertSame($target, $catalogue->get($source, static::DOMAIN));
    }

    public function testLoadsIntoTheRequestedLocaleAndDomain(): void
    {
        // Arrange
        $file = $this->createCsvFile("\"Store name is required\",\"Der Store-Name ist erforderlich\"\n");

        // Act
        $catalogue = (new ApiCsvFileLoader())->load($file, static::LOCALE, static::DOMAIN);

        // Assert
        $this->assertSame(static::LOCALE, $catalogue->getLocale());
        $this->assertArrayHasKey(static::DOMAIN, $catalogue->all());
    }

    protected function createCsvFile(string $contents): string
    {
        $file = (string)tempnam(sys_get_temp_dir(), 'api-translation-') . '.csv';
        file_put_contents($file, $contents);
        $this->temporaryFiles[] = $file;

        return $file;
    }
}
