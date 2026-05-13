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
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Drops validation violations whose constraint was emitted from an `Optional` block in the
 * validation YAML AND whose property was not present in the submitted request body.
 *
 * Preserves legacy Glue REST semantics for fields wrapped in `Optional`:
 * - field absent from request body → no error (legacy behavior preserved)
 * - field present with null/empty value → validation fires normally (NotBlank → 422)
 * - bare-NotBlank required fields absent → "must not be blank" fires normally (no change)
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

            $submittedFields = $this->resolveSubmittedFields($request);

            if ($submittedFields === null) {
                throw $exception;
            }

            $remainingViolations = [];

            foreach ($exception->getConstraintViolationList() as $violation) {
                if ($this->shouldDropViolation($violation, $submittedFields)) {
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
     * @param array<string> $submittedFields
     */
    protected function shouldDropViolation(ConstraintViolationInterface $violation, array $submittedFields): bool
    {
        $propertyPath = $violation->getPropertyPath();

        // Only top-level properties can be checked against the submitted body map.
        // Nested paths (e.g. `items[0].sku`) keep their original violation.
        if (str_contains($propertyPath, '.') || str_contains($propertyPath, '[')) {
            return false;
        }

        if (in_array($propertyPath, $submittedFields, true)) {
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
     * @return array<string>|null
     */
    protected function resolveSubmittedFields(Request $request): ?array
    {
        $body = json_decode((string)$request->getContent(), true);

        if (!is_array($body) || !isset($body['data']['attributes']) || !is_array($body['data']['attributes'])) {
            return null;
        }

        return array_map('strval', array_keys($body['data']['attributes']));
    }
}
