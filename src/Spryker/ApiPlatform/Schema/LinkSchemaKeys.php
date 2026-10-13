<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema;

interface LinkSchemaKeys
{
    public const string LINKS = 'links';

    public const string LINK_NAME = 'name';

    public const string LINK_ROUTE = 'route';

    public const string LINK_DESCRIPTION = 'description';

    public const string LINK_PARAMETERS = 'parameters';

    public const string LINK_CONDITION = 'condition';

    public const string LINK_OPERATIONS = 'operations';

    public const string CONDITION_PROPERTY = 'property';

    public const string CONDITION_EQUALS = 'equals';
}
