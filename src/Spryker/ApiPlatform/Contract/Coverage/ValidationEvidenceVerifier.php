<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Holds each `#[CoversApiValidation]` of a test to what the validator actually raised. A declaration
 * is verified only by a 422 on its operation that carries a structured violation of the declared
 * rule for the declared attribute's full path - one the validator raised, a denormalization type
 * error, or one the exception subscriber synthesised
 * ({@see \Spryker\ApiPlatform\Contract\Coverage\RecordedExchangeFactory}). The error detail text
 * is never read: it names no rule, and a synthesised nested error names only the leaf.
 *
 * A violation that names the attribute under another rule, or another attribute with the same
 * leaf, does not verify: those are the cases the check exists for.
 */
class ValidationEvidenceVerifier
{
    protected const int STATUS_UNPROCESSABLE_ENTITY = 422;

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $declaredValidations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RecordedExchange> $exchanges
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> The unverified declarations.
     */
    public function verify(array $declaredValidations, array $exchanges): array
    {
        return array_values(array_filter(
            $declaredValidations,
            fn (ValidationConstraint $validation): bool => !$this->isVerified($validation, $exchanges),
        ));
    }

    /**
     * What the failed requests on a declaration's operation reported, for the failure message.
     *
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RecordedExchange> $exchanges
     *
     * @return array<string> `attribute/rule` of each violation raised on the operation.
     */
    public function observedViolations(ValidationConstraint $validation, array $exchanges): array
    {
        $observed = [];

        foreach ($this->rejectionsOf($validation, $exchanges) as $exchange) {
            foreach ($exchange->constraintViolations as $violation) {
                $observed[] = $violation->attributePath . '/' . ($violation->rule ?? '?');
            }
        }

        return array_values(array_unique($observed));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RecordedExchange> $exchanges
     */
    protected function isVerified(ValidationConstraint $validation, array $exchanges): bool
    {
        foreach ($this->rejectionsOf($validation, $exchanges) as $exchange) {
            foreach ($exchange->constraintViolations as $violation) {
                if ($violation->rule === $validation->rule && $this->violationNamesAttribute($violation, $validation->attribute)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function violationNamesAttribute(RecordedConstraintViolation $violation, string $attribute): bool
    {
        if ($violation->attributePath === $attribute) {
            return true;
        }

        return $violation->propertyPath !== null && ValidationAttributePath::matches($violation->propertyPath, $attribute);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RecordedExchange> $exchanges
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\RecordedExchange>
     */
    protected function rejectionsOf(ValidationConstraint $validation, array $exchanges): array
    {
        $dispatchKey = strtoupper($validation->verb) . ' ' . $validation->uriTemplate;

        return array_values(array_filter(
            $exchanges,
            static fn (RecordedExchange $exchange): bool => $exchange->status === static::STATUS_UNPROCESSABLE_ENTITY
                && $exchange->operation->dispatchKey() === $dispatchKey,
        ));
    }
}
