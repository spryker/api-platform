<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The generated source of truth for a coverage scope: the operations a test must cover (servable),
 * the item-GETs that only mint IRIs and so cannot be covered (non-servable), the ones that do the
 * same by explicit declaration (internal), the validation constraints a test must cover, the
 * operations whose schema declares no responses at all (a schema defect the gate reports), the
 * statuses each operation's schema declares and the response attributes each success operation must
 * carry.
 */
readonly class TruthSet
{
    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $servableOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $nonServableOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $validationConstraints
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $undeclaredResponseOperations
     * @param array<string, array<int>> $declaredResponses dispatchKey => sorted unique declared statuses
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $internalOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $responseAttributes
     */
    public function __construct(
        public array $servableOperations,
        public array $nonServableOperations,
        public array $validationConstraints,
        public array $undeclaredResponseOperations = [],
        public array $declaredResponses = [],
        public array $internalOperations = [],
        public array $responseAttributes = [],
    ) {
    }

    /**
     * Every operation the schema defines anywhere, whatever a test can do with it. Existence checks
     * read this so a claim on an unreachable operation is reported as unreachable rather than stale.
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    public function allOperations(): array
    {
        return array_merge($this->servableOperations, $this->nonServableOperations, $this->internalOperations);
    }

    /**
     * Two schema files can back one dispatch key, so declared statuses union rather than overwrite.
     *
     * @param array<string, array<int>> $declaredResponses
     * @param array<string, array<int>> $additionalDeclaredResponses
     *
     * @return array<string, array<int>>
     */
    public static function mergeDeclaredResponses(array $declaredResponses, array $additionalDeclaredResponses): array
    {
        foreach ($additionalDeclaredResponses as $dispatchKey => $statuses) {
            $merged = array_values(array_unique(array_merge($declaredResponses[$dispatchKey] ?? [], $statuses)));
            sort($merged);
            $declaredResponses[$dispatchKey] = $merged;
        }

        return $declaredResponses;
    }
}
