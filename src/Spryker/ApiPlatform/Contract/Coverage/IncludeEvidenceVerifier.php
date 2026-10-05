<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Holds a `#[CoversApiIncludes]` test to both halves of the claim: a successful request of the
 * claimed operation asked for the relationship with `?include=`, and the test asserted what came
 * back. Either half alone proves nothing about the include.
 */
class IncludeEvidenceVerifier
{
    /**
     * @param array<string, array<string>> $claims dispatch key => claimed relationship names
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RecordedExchange> $exchanges
     * @param array<string> $assertedRelationshipNames
     *
     * @return array<string> One line per unproven claim, naming the missing half.
     */
    public function verify(array $claims, array $exchanges, array $assertedRelationshipNames): array
    {
        $requested = [];
        foreach ($exchanges as $exchange) {
            if (!$exchange->isSuccessful()) {
                continue;
            }

            foreach ($exchange->includeRelationshipNames as $relationshipName) {
                $requested[$exchange->operation->dispatchKey()][$relationshipName] = true;
            }
        }

        $asserted = array_flip($assertedRelationshipNames);
        $unproven = [];

        foreach ($claims as $dispatchKey => $relationshipNames) {
            foreach ($relationshipNames as $relationshipName) {
                $missing = [];
                if (!isset($requested[$dispatchKey][$relationshipName])) {
                    $missing[] = sprintf('no successful %s request asked for ?include=%s', $dispatchKey, $relationshipName);
                }
                if (!isset($asserted[$relationshipName])) {
                    $missing[] = sprintf('assertIncludedRelationship() never asserted %s', $relationshipName);
                }
                if ($missing !== []) {
                    $unproven[] = sprintf('%s include %s: %s', $dispatchKey, $relationshipName, implode(', and ', $missing));
                }
            }
        }

        return $unproven;
    }
}
