<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Spryker\ApiPlatform\Validation\Constraint\StrictBoolean;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use Symfony\Component\HttpFoundation\Request;

/**
 * Re-applies the boolean a client actually spelled out, for properties that declare
 * {@see \Spryker\ApiPlatform\Validation\Constraint\StrictBoolean}.
 *
 * The backend deserializes with `disable_type_enforcement` so that stringified booleans keep
 * working, and that casts the request value to bool with PHP semantics. For
 * the non-empty string `"false"` those semantics give `true` — a client asking to deactivate would
 * activate instead. This decorator runs straight after deserialization and before validation, and
 * assigns the value each accepted spelling means.
 *
 * Only properties carrying the constraint are touched, and only top-level `data.attributes` members,
 * so no resource changes behaviour by being deserialized through this chain unless it opts in.
 *
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
class StrictBooleanCanonicalizingDeserializeProvider implements ProviderInterface
{
    protected const string BODY_KEY_DATA = 'data';

    protected const string BODY_KEY_ATTRIBUTES = 'attributes';

    /**
     * @param \ApiPlatform\State\ProviderInterface<object> $decorated
     */
    public function __construct(
        protected readonly ProviderInterface $decorated,
        protected readonly ValidationConstraintReader $validationConstraintReader,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        if (!is_object($data)) {
            return $data;
        }

        $request = $context['request'] ?? null;

        if (!$request instanceof Request) {
            return $data;
        }

        foreach ($this->findSubmittedAttributes($request) as $propertyName => $submittedValue) {
            $this->canonicalizeProperty($data, (string)$propertyName, $submittedValue);
        }

        return $data;
    }

    protected function canonicalizeProperty(object $data, string $propertyName, mixed $submittedValue): void
    {
        if (!is_string($submittedValue) || !array_key_exists($submittedValue, StrictBoolean::ACCEPTED_STRINGS)) {
            return;
        }

        if (!$this->hasStrictBooleanConstraint($data::class, $propertyName)) {
            return;
        }

        $data->{$propertyName} = StrictBoolean::ACCEPTED_STRINGS[$submittedValue];
    }

    protected function hasStrictBooleanConstraint(string $resourceClass, string $propertyName): bool
    {
        $constraints = $this->validationConstraintReader->getConstraintsForGroups($resourceClass, $propertyName, []);

        foreach ($constraints as $constraint) {
            if ($constraint instanceof StrictBoolean) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keys are array-key rather than string because json_decode turns a numeric attribute name into
     * an int, so a body such as `{"data":{"attributes":{"0":true}}}` yields an int key.
     *
     * @return array<array-key, mixed>
     */
    protected function findSubmittedAttributes(Request $request): array
    {
        $body = json_decode((string)$request->getContent(), true);

        if (!is_array($body)) {
            return [];
        }

        $attributes = $body[static::BODY_KEY_DATA][static::BODY_KEY_ATTRIBUTES] ?? null;

        return is_array($attributes) ? $attributes : [];
    }
}
