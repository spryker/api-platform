<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation\Constraint;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Requires the property to have been sent as a JSON boolean.
 *
 * A JSON boolean, or one of the strings `"true"` and `"false"`. Everything else is rejected —
 * `"yes"`, `"abc"`, `1`, `0`, `2` and other casings such as `"True"`.
 *
 * `Assert\Type(type: 'bool')` cannot express this. The backend runs with
 * `denormalizationContext(['disable_type_enforcement' => true])` so that the string forms keep
 * working, which casts every one of the values above to a real bool before any constraint runs, so
 * a `Type` constraint on a `?bool` property can never fail. This constraint is validated against
 * the raw request body instead, so it sees the value as the client sent it.
 *
 * Declaring it also corrects the conversion: PHP casts the non-empty string `"false"` to `true`, so
 * a client asking to deactivate would otherwise activate. The accepted strings are re-applied to
 * the property from {@see \Spryker\ApiPlatform\State\StrictBooleanCanonicalizingDeserializeProvider}
 * before validation runs.
 *
 * Declare it per property in the resource's validation schema:
 *
 * ```yaml
 * post:
 *     isActive:
 *         - Spryker\ApiPlatform\Validation\Constraint\StrictBoolean:
 *               message: 'validation.type.bool'
 * ```
 *
 * @uses \Spryker\ApiPlatform\Validation\Constraint\StrictBooleanValidator
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class StrictBoolean extends Constraint
{
    public const string NOT_BOOLEAN_ERROR = 'a3f6d1e2-7b48-4c95-9d0a-1f2e3b4c5d6a';

    /**
     * The string spellings a client may use for a boolean, mapped to the value each one means.
     * Single source of truth for both the validator and the canonicalizing provider.
     *
     * @var array<string, bool>
     */
    public const array ACCEPTED_STRINGS = [
        'true' => true,
        'false' => false,
    ];

    /**
     * @var array<string, string>
     */
    protected const array ERROR_NAMES = [
        self::NOT_BOOLEAN_ERROR => 'NOT_BOOLEAN_ERROR',
    ];

    public string $message = 'This value should be a boolean, or the string "true" or "false".';

    /**
     * @param array<string, mixed>|null $options
     * @param array<string>|null $groups
     */
    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options ?? [], $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
