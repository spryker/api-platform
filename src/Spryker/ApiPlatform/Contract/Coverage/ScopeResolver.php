<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Splits the discovered resources into the enforced truth (the must-cover set this run selected)
 * and the existence truth (every resource, which the stale checks measure claims against).
 */
class ScopeResolver
{
    /**
     * @param array<string, \Spryker\ApiPlatform\Contract\Coverage\TruthSet> $truthByResource
     * @param array<string> $enforcedResources
     */
    public function resolve(array $truthByResource, array $enforcedResources): ScopeResolution
    {
        $enforcedTruths = [];
        $allTruths = [];

        foreach ($truthByResource as $resource => $truth) {
            $allTruths[] = $truth;

            if (in_array($resource, $enforcedResources, true)) {
                $enforcedTruths[] = $truth;
            }
        }

        return new ScopeResolution($this->merge($enforcedTruths), $this->merge($allTruths));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\TruthSet> $truthSets
     */
    protected function merge(array $truthSets): TruthSet
    {
        $servableOperations = [];
        $nonServableOperations = [];
        $internalOperations = [];
        $validationConstraints = [];
        $undeclaredResponseOperations = [];
        $declaredResponses = [];
        $responseAttributes = [];

        foreach ($truthSets as $truthSet) {
            $servableOperations = array_merge($servableOperations, $truthSet->servableOperations);
            $nonServableOperations = array_merge($nonServableOperations, $truthSet->nonServableOperations);
            $internalOperations = array_merge($internalOperations, $truthSet->internalOperations);
            $validationConstraints = array_merge($validationConstraints, $truthSet->validationConstraints);
            $undeclaredResponseOperations = array_merge($undeclaredResponseOperations, $truthSet->undeclaredResponseOperations);
            $responseAttributes = array_merge($responseAttributes, $truthSet->responseAttributes);

            $declaredResponses = TruthSet::mergeDeclaredResponses($declaredResponses, $truthSet->declaredResponses);
        }

        return new TruthSet(
            $servableOperations,
            $nonServableOperations,
            $validationConstraints,
            $undeclaredResponseOperations,
            $declaredResponses,
            $internalOperations,
            $responseAttributes,
        );
    }
}
