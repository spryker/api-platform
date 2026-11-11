<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\DenormalizationViolationFactoryInterface;
use BackedEnum;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;

/**
 * API Platform 5 turns a denormalization error into a 422 violation before the exception reaches
 * {@see \Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber}. For a nested value object
 * whose leaf cannot take the submitted value, that violation names the parent and the generated
 * class (`shipment => This value should be of type ?Generated\Api\...`). Such an error is left to the
 * subscriber, which reports it as an object type error that
 * {@see \Spryker\ApiPlatform\Validation\NestedObjectValidationErrorAugmenter} replaces with the
 * leaf's own type error. Every other error goes to API Platform's factory.
 */
class NestedObjectDenormalizationViolationFactory implements DenormalizationViolationFactoryInterface
{
    public function __construct(protected DenormalizationViolationFactoryInterface $decorated)
    {
    }

    public function handle(NotNormalizableValueException|PartialDenormalizationException $exception, Operation $operation): void
    {
        if ($exception instanceof NotNormalizableValueException && $this->expectsValueObject($exception)) {
            return;
        }

        $this->decorated->handle($exception, $operation);
    }

    protected function expectsValueObject(NotNormalizableValueException $exception): bool
    {
        foreach ($exception->getExpectedTypes() ?? [] as $expectedType) {
            $className = ltrim((string)$expectedType, '?');

            if (class_exists($className) && !is_subclass_of($className, BackedEnum::class)) {
                return true;
            }
        }

        return false;
    }
}
