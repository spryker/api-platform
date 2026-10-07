<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trims leading and trailing whitespace of the string values a client submitted in the request body.
 *
 * Runs straight after deserialization and before validation, so constraints see the trimmed value.
 * The request's `data.attributes` is walked side by side with the deserialized object, recursing into
 * nested objects and arrays, and only a string equal to the submitted one is trimmed. Values the
 * client did not send, such as the stored state a Patch is applied to, stay untouched.
 *
 * A property opts out with `allowWhitespace: true` in its resource schema, which renders as
 * `#[ApiProperty(extraProperties: ['allowWhitespace' => true])]` and covers its whole subtree. List items
 * stay plain arrays after deserialization, so a list opts out as a whole on the list property itself.
 *
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
class WhitespaceTrimmingDeserializeProvider implements ProviderInterface
{
    protected const string BODY_KEY_DATA = 'data';

    protected const string BODY_KEY_ATTRIBUTES = 'attributes';

    protected const string EXTRA_PROPERTY_ALLOW_WHITESPACE = 'allowWhitespace';

    /**
     * @var array<string, bool>
     */
    protected array $whitespaceAllowanceByProperty = [];

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

        $this->trimSubmittedProperties($data, $this->findSubmittedAttributes($request));

        return $data;
    }

    /**
     * @param array<array-key, mixed> $submittedAttributes
     */
    protected function trimSubmittedProperties(object $deserializedObject, array $submittedAttributes): void
    {
        $publicPropertyValues = get_object_vars($deserializedObject);

        foreach ($submittedAttributes as $propertyName => $submittedValue) {
            $propertyName = (string)$propertyName;

            if (!array_key_exists($propertyName, $publicPropertyValues) || $this->allowsWhitespace($deserializedObject, $propertyName)) {
                continue;
            }

            $trimmedValue = $this->trimSubmittedValue($publicPropertyValues[$propertyName], $submittedValue);

            if ($trimmedValue !== $publicPropertyValues[$propertyName]) {
                $deserializedObject->{$propertyName} = $trimmedValue;
            }
        }
    }

    protected function trimSubmittedValue(mixed $deserializedValue, mixed $submittedValue): mixed
    {
        if (is_string($deserializedValue) && $deserializedValue === $submittedValue) {
            return trim($deserializedValue);
        }

        if (is_object($deserializedValue) && is_array($submittedValue)) {
            $this->trimSubmittedProperties($deserializedValue, $submittedValue);

            return $deserializedValue;
        }

        if (is_array($deserializedValue) && is_array($submittedValue)) {
            return $this->trimSubmittedItems($deserializedValue, $submittedValue);
        }

        return $deserializedValue;
    }

    /**
     * @param array<array-key, mixed> $deserializedItems
     * @param array<array-key, mixed> $submittedItems
     *
     * @return array<array-key, mixed>
     */
    protected function trimSubmittedItems(array $deserializedItems, array $submittedItems): array
    {
        foreach ($submittedItems as $itemKey => $submittedItem) {
            if (!array_key_exists($itemKey, $deserializedItems)) {
                continue;
            }

            $deserializedItems[$itemKey] = $this->trimSubmittedValue($deserializedItems[$itemKey], $submittedItem);
        }

        return $deserializedItems;
    }

    protected function allowsWhitespace(object $deserializedObject, string $propertyName): bool
    {
        return $this->whitespaceAllowanceByProperty[$deserializedObject::class . '::' . $propertyName]
            ??= $this->readWhitespaceAllowance($deserializedObject, $propertyName);
    }

    protected function readWhitespaceAllowance(object $deserializedObject, string $propertyName): bool
    {
        if (!property_exists($deserializedObject::class, $propertyName)) {
            return false;
        }

        foreach ((new ReflectionProperty($deserializedObject, $propertyName))->getAttributes(ApiProperty::class) as $apiPropertyAttribute) {
            $extraProperties = $apiPropertyAttribute->newInstance()->getExtraProperties();

            if (($extraProperties[static::EXTRA_PROPERTY_ALLOW_WHITESPACE] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keys are array-key rather than string because json_decode turns a numeric attribute name into
     * an int, so a body such as `{"data":{"attributes":{"0":" a "}}}` yields an int key.
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
