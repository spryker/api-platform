<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Test;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiIncludes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequestAttributes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequiredResponseAttributes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation;
use Spryker\ApiPlatform\Contract\Attribute\Rule;
use Spryker\ApiPlatform\Contract\Coverage\AnnotationCollector;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageBaseline;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;
use Spryker\ApiPlatform\Contract\Coverage\IncludedRelationshipRecorder;
use Spryker\ApiPlatform\Contract\Coverage\RecordedExchange;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeRecorder;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use SprykerTest\ApiPlatform\Test\StorefrontApiTestCase;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Test-only probe: a StorefrontApiTestCase whose baseline, enforcement and schema truth are supplied,
 * so the runtime checks run without a kernel. The `probe*` methods carry the declarations the checks
 * read.
 *
 * The file name intentionally omits the "Test" suffix so Codeception does not collect it as a test
 * class; it is instantiated directly by AbstractApiTestCaseBaselineTest.
 */
class ContractCoverageBaselineProbe extends StorefrontApiTestCase
{
    public ContractCoverageBaseline $baseline;

    public ContractCoverageEnforcement $enforcement;

    /**
     * @var array<string>
     */
    public array $responseAttributePaths = [];

    /**
     * @var array<string, array<int>>
     */
    public array $declaredResponses = [];

    /**
     * @var array<string, array<int, array<string>>>
     */
    public array $declaredErrorCodes = [];

    /**
     * @var array<string, array<string>>
     */
    public array $requestAttributePaths = [];

    protected string $probedMethodName = '';

    public function armFor(string $methodName): void
    {
        $this->probedMethodName = $methodName;
        $this->declaredOperations = AnnotationCollector::operationsForMethod(static::class, $methodName);
        $this->operationRecorder = ContractCoverageFactory::createOperationCoverageRecorder();
        $this->responseAttributeRecorder = new ResponseAttributeRecorder();
        $this->includedRelationshipRecorder = new IncludedRelationshipRecorder();
    }

    public function recordAssertedRelationship(string $relationshipName): void
    {
        $this->includedRelationshipRecorder?->record($relationshipName);
    }

    public function recordAssertedValue(string $path, mixed $value): void
    {
        $this->responseAttributeRecorder?->recordValue($path, $value);
    }

    public function recordExchange(RecordedExchange $exchange): void
    {
        $this->operationRecorder?->recordExchange($exchange);
    }

    /**
     * The failure message of the response-attribute check, or null when it passes.
     */
    public function verifyResponseAttributes(): ?string
    {
        return $this->failureOf(fn () => $this->failOnUnassertedResponseAttributes());
    }

    /**
     * The failure message of the validation-evidence check, or null when it passes.
     */
    public function verifyValidations(bool $isEnforced): ?string
    {
        return $this->failureOf(fn () => $this->failOnUnverifiedValidations($isEnforced));
    }

    /**
     * The first failure of the checks a passing test method runs after its body, or null when all pass.
     */
    public function verifyPostConditions(): ?string
    {
        return $this->failureOf(fn () => $this->assertPostConditions());
    }

    protected function failureOf(callable $check): ?string
    {
        try {
            $check();
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return null;
    }

    #[CoversApiOperation('GET', '/orders/{orderReference}')]
    #[CoversApiRequiredResponseAttributes]
    public function probeOrderRead(): void
    {
    }

    #[CoversApiOperation('POST', '/customers/{customerReference}/addresses', status: Response::HTTP_UNPROCESSABLE_ENTITY)]
    #[CoversApiValidation('customer-addresses', 'iso2Code', Rule::LENGTH_MAX)]
    public function probeAddressValidation(): void
    {
    }

    #[CoversApiOperation('DELETE', '/carts/{cartUuid}/cart-codes/{code}', status: Response::HTTP_UNPROCESSABLE_ENTITY, code: '3301')]
    public function probeCartCodeRemovalError(): void
    {
    }

    #[CoversApiOperation('GET', '/carts/{cartUuid}')]
    #[CoversApiIncludes('vouchers')]
    public function probeCartReadWithVouchers(): void
    {
    }

    #[CoversApiOperation('PATCH', '/carts/{cartUuid}')]
    #[CoversApiRequestAttributes]
    public function probeCartUpdateOfEveryAttribute(): void
    {
    }

    #[CoversApiOperation('PATCH', '/carts/{cartUuid}')]
    #[CoversApiRequestAttributes('currency')]
    public function probeCartUpdateOfCurrency(): void
    {
    }

    protected function contractCoverageBaseline(): ContractCoverageBaseline
    {
        return $this->baseline;
    }

    protected function contractCoverageEnforcement(): ContractCoverageEnforcement
    {
        return $this->enforcement;
    }

    /**
     * @param array<string> $dispatchKeys
     *
     * @return array<string>
     */
    protected function schemaResponseAttributePaths(array $dispatchKeys): array
    {
        return $this->responseAttributePaths;
    }

    /**
     * @return array<string, array<int>>
     */
    protected function schemaDeclaredResponses(): array
    {
        return $this->declaredResponses;
    }

    /**
     * @return array<string, array<int, array<string>>>
     */
    protected function schemaDeclaredErrorCodes(): array
    {
        return $this->declaredErrorCodes;
    }

    /**
     * @return array<string, array<string>>
     */
    protected function schemaRequestAttributePaths(): array
    {
        return $this->requestAttributePaths;
    }

    protected function runningTestMethodName(): string
    {
        return $this->probedMethodName;
    }
}
