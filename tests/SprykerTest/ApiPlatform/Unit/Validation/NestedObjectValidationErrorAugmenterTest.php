<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Validation;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Validation\NestedObjectValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Validation
 * @group NestedObjectValidationErrorAugmenterTest
 * Add your own group annotations below this line
 */
class NestedObjectValidationErrorAugmenterTest extends Unit
{
    protected ApiUnitTester $tester;

    protected const string BOOL_LEAF_RESOURCE_CLASS = 'Generated\\Api\\Storefront\\BoolLeafResource';

    protected const string REQUIRED_LEAF_RESOURCE_CLASS = 'Generated\\Api\\Storefront\\RequiredLeafResource';

    protected const string SYNTHESIZING_RESOURCE_CLASS = 'Generated\\Api\\Storefront\\SynthesizingResource';

    protected const string BACKEND_BOOL_LEAF_RESOURCE_CLASS = 'Generated\\Api\\Backend\\BoolLeafResource';

    /**
     * The augmenter only cascades into nested properties typed under a generated `Generated\Api\*`
     * namespace, so both the value-object fixtures and the resource fixtures that reference them
     * must carry that FQCN. They are defined at runtime because the namespace is reserved for
     * generated code and has no source-tree home; keeping them out of the source tree also keeps the
     * resource-property types off static analysis, which would otherwise flag the runtime-only
     * classes as unknown.
     *
     * Backend fixtures mirror the Storefront ones so the both-ApiType cascade stays covered — a
     * Backend-only regression is otherwise invisible here and only surfaces in SOL-561.
     */
    protected function _before(): void
    {
        if (class_exists(static::BOOL_LEAF_RESOURCE_CLASS)) {
            return;
        }

        $storefrontFixtureCode = 'namespace Generated\\Api\\Storefront;'
            . ' use Symfony\\Component\\Validator\\Constraints as Assert;'
            . ' class BoolLeafValueObject { public ?bool $isComplete = null; }'
            . ' class RequiredLeafValueObject { #[Assert\\NotBlank] public ?string $salutation = null; }'
            . ' class SynthesizingValueObject { public const SYNTHESIZE_MISSING_FIELDS_WHEN_EMPTY = true; #[Assert\\NotBlank(allowNull: true)] public ?string $address1 = null; }'
            . ' class BoolLeafResource { public ?BoolLeafValueObject $productConfigurationInstance = null; }'
            . ' class RequiredLeafResource { public ?RequiredLeafValueObject $billingAddress = null; }'
            . ' class SynthesizingResource { public ?SynthesizingValueObject $shippingAddress = null; }';

        // A single eval cannot mix two unbraced namespace declarations, so the ApiTypes are separate.
        $backendFixtureCode = 'namespace Generated\\Api\\Backend;'
            . ' class BoolLeafValueObject { public ?bool $isComplete = null; }'
            . ' class BoolLeafResource { public ?BoolLeafValueObject $productConfigurationInstance = null; }';

        // phpcs:ignore Squiz.PHP.Eval.Discouraged
        eval($storefrontFixtureCode);
        // phpcs:ignore Squiz.PHP.Eval.Discouraged
        eval($backendFixtureCode);
    }

    public function testGivenNestedBoolLeafSubmittedAsNonBooleanWhenAugmentingThenAppendsBooleanTypeError(): void
    {
        // Arrange
        $augmenter = $this->createAugmenter();
        $rawAttributes = ['productConfigurationInstance' => ['isComplete' => 1]];

        // Act
        $result = $augmenter->augment(static::BOOL_LEAF_RESOURCE_CLASS, $rawAttributes, [], []);

        // Assert
        $this->assertTrue($result->modified);
        $this->assertFalse($result->forceUnprocessableEntity);
        $this->assertContains(
            'productConfigurationInstance.isComplete => This value should be of type boolean.',
            array_column($result->errors, 'detail'),
        );
    }

    public function testGivenNestedBoolLeafSubmittedAsBooleanWhenAugmentingThenNothingChanges(): void
    {
        // Arrange
        $augmenter = $this->createAugmenter();
        $rawAttributes = ['productConfigurationInstance' => ['isComplete' => true]];

        // Act
        $result = $augmenter->augment(static::BOOL_LEAF_RESOURCE_CLASS, $rawAttributes, [], []);

        // Assert
        $this->assertFalse($result->modified);
        $this->assertSame([], $result->errors);
    }

    public function testGivenBackendNestedBoolLeafSubmittedAsNonBooleanWhenAugmentingThenAppendsBooleanTypeError(): void
    {
        // Arrange
        $augmenter = $this->createAugmenter();
        $rawAttributes = ['productConfigurationInstance' => ['isComplete' => 1]];

        // Act
        $result = $augmenter->augment(static::BACKEND_BOOL_LEAF_RESOURCE_CLASS, $rawAttributes, [], []);

        // Assert
        $this->assertTrue($result->modified);
        $this->assertContains(
            'productConfigurationInstance.isComplete => This value should be of type boolean.',
            array_column($result->errors, 'detail'),
        );
    }

    public function testGivenFlaggedNestedRequiredLeafWhenAugmentingThenRelabelsToFieldMissing(): void
    {
        // Arrange
        $augmenter = $this->createAugmenter();
        $rawAttributes = ['billingAddress' => []];
        $errors = [
            ['detail' => 'billingAddress.salutation => This value should not be blank.', 'code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY],
        ];

        // Act
        $result = $augmenter->augment(static::REQUIRED_LEAF_RESOURCE_CLASS, $rawAttributes, [], $errors);

        // Assert
        $details = array_column($result->errors, 'detail');
        $this->assertTrue($result->modified);
        $this->assertContains('billingAddress.salutation => This field is missing.', $details);
        $this->assertNotContains('billingAddress.salutation => This value should not be blank.', $details);
    }

    public function testGivenEmptyRequiredObjectWhenAugmentingThenSynthesizesFieldMissingAndForces422(): void
    {
        // Arrange
        $augmenter = $this->createAugmenter();
        $rawAttributes = ['shippingAddress' => []];
        // A downstream domain error the request produced by wrongly proceeding past validation.
        $errors = [
            ['detail' => 'Address not found.', 'code' => '1301', 'status' => Response::HTTP_NOT_FOUND],
        ];

        // Act
        $result = $augmenter->augment(static::SYNTHESIZING_RESOURCE_CLASS, $rawAttributes, [], $errors);

        // Assert
        $details = array_column($result->errors, 'detail');
        $this->assertTrue($result->modified);
        $this->assertTrue($result->forceUnprocessableEntity);
        $this->assertContains('shippingAddress.address1 => This field is missing.', $details);
        // The empty-required-object case supersedes the downstream domain error.
        $this->assertNotContains('Address not found.', $details);
    }

    protected function createAugmenter(): NestedObjectValidationErrorAugmenter
    {
        return new NestedObjectValidationErrorAugmenter(new ValidationConstraintReader());
    }
}
