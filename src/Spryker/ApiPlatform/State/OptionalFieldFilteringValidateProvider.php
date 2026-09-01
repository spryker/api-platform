<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Drops validation violations whose constraint was emitted from an `Optional` block (or an
 * `allowMissingFields` collection) in the validation YAML AND whose property was not present in the
 * submitted request body.
 *
 * Preserves legacy Glue REST semantics for conditionally-present fields, at any nesting depth:
 * - field absent from request body → no error (legacy behavior preserved)
 * - field present with null/empty value → validation fires normally (NotBlank → 422)
 * - bare-NotBlank required fields absent → "must not be blank" fires normally (no change)
 *
 * The body is the only place absence and explicit null still differ — Symfony validates an
 * uninitialized typed property exactly like one defaulted to null.
 *
 * Optional-sourced constraints are tagged at generation time with `payload: ['source' => 'optional']`
 * by `ConstraintFormatter::generateConstraintAttributeWithOptionalPayload()`. This decorator inspects
 * that payload on each violation's constraint to decide whether the violation may be dropped.
 *
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
class OptionalFieldFilteringValidateProvider implements ProviderInterface
{
    protected const string OPTIONAL_PAYLOAD_KEY = 'source';

    protected const string OPTIONAL_PAYLOAD_VALUE = 'optional';

    /**
     * @param \ApiPlatform\State\ProviderInterface<object> $decorated
     */
    public function __construct(
        protected readonly ProviderInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws \ApiPlatform\Validator\Exception\ValidationException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        try {
            return $this->decorated->provide($operation, $uriVariables, $context);
        } catch (ValidationException $exception) {
            $request = $context['request'] ?? null;

            if (!$request instanceof Request) {
                throw $exception;
            }

            $submittedAttributes = $this->resolveSubmittedAttributes($request);

            if ($submittedAttributes === null) {
                throw $exception;
            }

            $remainingViolations = [];

            foreach ($exception->getConstraintViolationList() as $violation) {
                if ($this->shouldDropViolation($violation, $submittedAttributes)) {
                    continue;
                }

                $remainingViolations[] = $violation;
            }

            if ($remainingViolations === []) {
                // All violations were Optional-sourced for fields not in the request body — re-run
                // the inner provider with validation disabled to get the deserialized body so the
                // processor can run normally.
                return $this->decorated->provide($operation->withValidate(false), $uriVariables, $context);
            }

            throw new ValidationException(new ConstraintViolationList($remainingViolations));
        }
    }

    /**
     * @param array<string, mixed> $submittedAttributes
     */
    protected function shouldDropViolation(ConstraintViolationInterface $violation, array $submittedAttributes): bool
    {
        if ($this->isSubmitted($this->resolveMarkedPropertyPath($violation), $submittedAttributes)) {
            return false;
        }

        $constraint = $violation->getConstraint();

        if (!$constraint instanceof Constraint) {
            return false;
        }

        $payload = $constraint->payload;

        if (!is_array($payload)) {
            return false;
        }

        return ($payload[static::OPTIONAL_PAYLOAD_KEY] ?? null) === static::OPTIONAL_PAYLOAD_VALUE;
    }

    /**
     * The Optional payload rides on the constraint, so absence must be judged at the path that
     * constraint was applied to — which is not always the path the violation reports.
     *
     * A `Collection` reports a missing declared field one segment BELOW itself
     * (`productConfigurationInstance[isComplete]`) while the Optional marker sits on the Collection
     * governing `productConfigurationInstance`. That leaf is absent by definition — it is what the
     * violation is about — so judging absence there would drop every required-presence violation the
     * Collection exists to raise, and the request would pass with the field silently missing.
     *
     * An empty result means the Collection sat at the attributes root, which is always submitted.
     */
    protected function resolveMarkedPropertyPath(ConstraintViolationInterface $violation): string
    {
        $propertyPath = $violation->getPropertyPath();

        if ($violation->getCode() !== Collection::MISSING_FIELD_ERROR) {
            return $propertyPath;
        }

        $segments = $this->splitPropertyPath($propertyPath);
        array_pop($segments);

        return implode('.', $segments);
    }

    /**
     * Walks a violation property path (`shipments[1].shippingAddress`) through the decoded body.
     *
     * @param array<string, mixed> $submittedAttributes
     */
    protected function isSubmitted(string $propertyPath, array $submittedAttributes): bool
    {
        $cursor = $submittedAttributes;

        foreach ($this->splitPropertyPath($propertyPath) as $segment) {
            // array_key_exists, not isset: an explicitly submitted null is present, and must keep
            // its violation so `"field": null` stays a 422 as it was under the legacy Collection.
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }

            $cursor = $cursor[$segment];
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function splitPropertyPath(string $propertyPath): array
    {
        $normalized = str_replace(['[', ']'], ['.', ''], $propertyPath);

        return array_values(array_filter(explode('.', $normalized), static fn (string $segment): bool => $segment !== ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveSubmittedAttributes(Request $request): ?array
    {
        $body = json_decode((string)$request->getContent(), true);

        if (!is_array($body) || !isset($body['data']['attributes']) || !is_array($body['data']['attributes'])) {
            return null;
        }

        return $body['data']['attributes'];
    }
}
