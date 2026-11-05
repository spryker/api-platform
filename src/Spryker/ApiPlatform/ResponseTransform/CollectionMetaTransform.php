<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\ResponseTransform;

use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\Request;

class CollectionMetaTransform
{
    protected const string DOCUMENT_SECTION_DATA = 'data';

    protected const string DOCUMENT_SECTION_META = 'meta';

    /**
     * @param array<string, mixed> $data
     */
    public function applyTo(array &$data, Request $request): bool
    {
        $collectionMeta = $request->attributes->get(RequestAttribute::COLLECTION_META);

        if (!is_array($collectionMeta) || $collectionMeta === [] || !$this->isCollectionDocument($data)) {
            return false;
        }

        $meta = $data[static::DOCUMENT_SECTION_META] ?? [];
        $data[static::DOCUMENT_SECTION_META] = array_merge(is_array($meta) ? $meta : [], $collectionMeta);

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function isCollectionDocument(array $data): bool
    {
        return isset($data[static::DOCUMENT_SECTION_DATA])
            && is_array($data[static::DOCUMENT_SECTION_DATA])
            && array_is_list($data[static::DOCUMENT_SECTION_DATA]);
    }
}
