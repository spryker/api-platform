<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Reduces the uriTemplate spellings API Platform produces to one canonical form so the truth set,
 * the annotations and the runtime recording compare equal. The format suffix is spelled
 * `{._format}` on operation metadata but `.{_format}` on the generated route; both are stripped,
 * along with any trailing slash.
 */
class UriTemplateNormalizer
{
    protected const string FORMAT_SUFFIX_METADATA_PATTERN = '/\{\._format\}$/';

    protected const string FORMAT_SUFFIX_ROUTE_PATTERN = '/\.\{_format\}$/';

    protected const string PATH_ROOT = '/';

    public static function normalize(string $uriTemplate): string
    {
        $uriTemplate = (string)preg_replace(static::FORMAT_SUFFIX_METADATA_PATTERN, '', $uriTemplate);
        $uriTemplate = (string)preg_replace(static::FORMAT_SUFFIX_ROUTE_PATTERN, '', $uriTemplate);

        if ($uriTemplate !== static::PATH_ROOT && str_ends_with($uriTemplate, static::PATH_ROOT)) {
            $uriTemplate = rtrim($uriTemplate, static::PATH_ROOT);
        }

        return $uriTemplate;
    }
}
