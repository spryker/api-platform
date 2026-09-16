<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Translation;

use Symfony\Component\Translation\Loader\CsvFileLoader;

/**
 * Reads a module's `data/translation/Api/<locale>.csv`.
 *
 * Symfony's own CSV loader defaults to a semicolon; Spryker's translation files are comma-separated,
 * the same shape the Back Office reads from `data/translation/Zed`, so a translator can move between
 * the two without learning a second format.
 */
class ApiCsvFileLoader extends CsvFileLoader
{
    public const string FORMAT = 'spryker_api_csv';

    protected const string DELIMITER = ',';

    public function __construct()
    {
        $this->setCsvControl(static::DELIMITER);
    }
}
