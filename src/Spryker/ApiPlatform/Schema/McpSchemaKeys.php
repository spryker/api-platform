<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema;

interface McpSchemaKeys
{
    public const string NAME = 'name';

    public const string TITLE = 'title';

    public const string DESCRIPTION = 'description';

    public const string STRUCTURED_CONTENT = 'structuredContent';

    public const string ANNOTATIONS = 'annotations';

    /**
     * @var array<string>
     */
    public const array STRING_KEYS = [self::NAME, self::TITLE, self::DESCRIPTION];

    /**
     * @var array<string>
     */
    public const array TOOL_KEYS = [...self::STRING_KEYS, self::STRUCTURED_CONTENT, self::ANNOTATIONS];

    /**
     * @var array<string>
     */
    public const array OPERATION_TYPES = ['Get', 'GetCollection'];
}
