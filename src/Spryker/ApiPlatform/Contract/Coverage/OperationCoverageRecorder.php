<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Per-test buffer of what a booted request actually did. The kernel-request listener installed by
 * {@see \SprykerTest\ApiPlatform\Test\AbstractApiTestCase} pushes each matched `(verb, uriTemplate)`
 * here and the kernel-response listener adds the status it answered with; at test end the case asks
 * {@see self::verify()} which declarations the run failed to prove and which observed statuses the
 * resource schema never declared, failing the test on either.
 */
class OperationCoverageRecorder
{
    /**
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected array $recorded = [];

    /**
     * The statuses the run actually returned, from the kernel.response listener — the evidence a
     * declared status was really produced, and the only place an undeclared status can surface.
     *
     * @var array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    protected array $recordedResponses = [];

    public function __construct(protected OperationVerifier $operationVerifier)
    {
    }

    public function record(string $verb, string $uriTemplate): void
    {
        $this->recorded[] = new ApiOperation($verb, UriTemplateNormalizer::normalize($uriTemplate));
    }

    public function recordResponse(string $verb, string $uriTemplate, int $status): void
    {
        $this->recordedResponses[] = new ApiOperation($verb, UriTemplateNormalizer::normalize($uriTemplate), $status);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $declared
     * @param array<string, array<int>> $declaredResponses
     */
    public function verify(array $declared, array $declaredResponses = []): OperationVerificationResult
    {
        return $this->operationVerifier->verify($declared, $this->recorded, $this->recordedResponses, $declaredResponses);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $declared
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation>
     */
    public function findUnverified(array $declared): array
    {
        return $this->operationVerifier->findUnverified($declared, $this->recorded);
    }
}
