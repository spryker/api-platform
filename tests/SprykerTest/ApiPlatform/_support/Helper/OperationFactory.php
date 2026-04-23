<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;

class OperationFactory
{
    public static function createPost(string $class = '', ?string $uriTemplate = null): Post
    {
        return new Post(class: $class, uriTemplate: $uriTemplate);
    }

    public static function createGet(string $class = '', ?string $uriTemplate = null): Get
    {
        return new Get(class: $class, uriTemplate: $uriTemplate);
    }

    public static function createPatch(string $class = '', ?string $uriTemplate = null): Patch
    {
        return new Patch(class: $class, uriTemplate: $uriTemplate);
    }

    public static function createDelete(string $class = '', ?string $uriTemplate = null): Delete
    {
        return new Delete(class: $class, uriTemplate: $uriTemplate);
    }

    public static function createGetCollection(string $class = '', ?string $uriTemplate = null): GetCollection
    {
        return new GetCollection(class: $class, uriTemplate: $uriTemplate);
    }
}
