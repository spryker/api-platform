<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Envelope;

use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Collects the envelope violations of every response one test method produced, so the test can fail
 * once with all of them rather than on whichever request happened to be asserted.
 */
class JsonApiEnvelopeRecorder
{
    /**
     * @var array<string, true>
     */
    protected array $violations = [];

    /**
     * `$identifierDeclaringResourceShortNames` carries no default on purpose: an empty list reads
     * as "no resource declares an identifier", which would switch the identifier check off across
     * the board and still pass. A caller that forgets it should not compile.
     *
     * @param array<string> $schemaResourceShortNames
     * @param array<string> $identifierDeclaringResourceShortNames
     */
    public function __construct(
        protected array $schemaResourceShortNames,
        protected array $identifierDeclaringResourceShortNames,
        protected JsonApiEnvelopeVerifier $verifier,
    ) {
    }

    public function record(ApiOperation $apiOperation, Response $response, string $expectedResourceShortName): void
    {
        $violations = $this->verifier->verify(
            $apiOperation,
            $response,
            $expectedResourceShortName,
            $this->schemaResourceShortNames,
            $this->identifierDeclaringResourceShortNames,
        );

        foreach ($violations as $violation) {
            $this->violations[$violation] = true;
        }
    }

    /**
     * @return array<string>
     */
    public function violations(): array
    {
        return array_keys($this->violations);
    }
}
