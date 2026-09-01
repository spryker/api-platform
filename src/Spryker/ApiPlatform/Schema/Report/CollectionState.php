<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Report;

/**
 * How well the published contract describes the elements of a list-shaped resource property.
 *
 * The backing values are the wire format: they are written verbatim into the committed
 * `untyped-collections.json` artifact that {@see \Spryker\ApiPlatform\Command\ApiCollectionsReportCommand}
 * diffs byte-for-byte in `--check` mode, so renaming one is a drift-gate change, not a refactor.
 */
enum CollectionState: string
{
    /**
     * An authored `items` block as a sibling of `type: array`; publishes a referenceable component schema.
     */
    case TYPED = 'typed';

    /**
     * A shared canonical object referenced by `objectName`; publishes a referenceable component schema.
     */
    case CANONICAL = 'canonical';

    /**
     * The element shape is duplicated by hand in `openapiContext` rather than authored as `items`.
     */
    case HANDWRITTEN = 'handwritten';

    /**
     * Only an `openapiContext.example` of object elements exists; nothing referenceable is published.
     */
    case EXAMPLE_ONLY = 'example-only';

    /**
     * Nothing describes the elements at all.
     */
    case UNKNOWN = 'unknown';

    /**
     * An `includes:` entry's auto-generated placeholder, not an author-written collection — listed so an
     * `includes:` addition still shows up as drift, but not adoptable. See
     * {@see CollectionInventoryBuilder::isTopLevelRelationshipProperty()}.
     */
    case RELATIONSHIP = 'relationship';
}
