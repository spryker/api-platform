<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeTruthCollector;

/**
 * Opens up the collector's default nested-object predicate so a test can put class names to it
 * directly. No fixture resource can reach that branch through a property type: it only answers to
 * names under `Generated\Api\`, and the generated tree is not present in every checkout.
 */
class ResponseAttributesDefaultPredicateFixtureCollector extends ResponseAttributeTruthCollector
{
    /**
     * @param class-string $class
     */
    public function isGeneratedNestedObjectClass(string $class): bool
    {
        return parent::isGeneratedNestedObjectClass($class);
    }
}
