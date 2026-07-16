<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Utility;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

/**
 * Pluralizes a class-name segment for object-collection properties: a property holding a list of
 * typed objects generates a value-object class named after the plural of the field (e.g. a
 * `customer` collection on a cart resource generates `CartsCustomersStorefrontObject`).
 */
class ResourceNameInflector
{
    protected static ?Inflector $inflector = null;

    /**
     * Returns the PascalCase plural of a single field segment. The Doctrine inflector is idempotent,
     * so an already-plural input is preserved (`customers` stays `customers`, `customer` becomes
     * `customers`, `company` becomes `companies`, `address` becomes `addresses`).
     */
    public static function pluralizeSegment(string $segment): string
    {
        return ucfirst(static::getInflector()->pluralize($segment));
    }

    protected static function getInflector(): Inflector
    {
        if (static::$inflector === null) {
            static::$inflector = InflectorFactory::create()->build();
        }

        return static::$inflector;
    }
}
