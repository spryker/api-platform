<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use Spryker\ApiPlatform\Contract\Attribute\Scenario;

/**
 * The unit of operation coverage: an HTTP verb, a canonical OpenAPI uriTemplate and optionally the
 * declared error response the entry stands for, comparable by {@see ApiOperation::key()} across the
 * truth set, the annotations and the runtime recording. A null status is the operation's success
 * response.
 *
 * An error response can be narrowed further to one of the error codes its status declares, and to
 * the scenario it stands for. A scenario item of the truth carries no status: which status proves
 * it is the schema's business, so any declared error status of the operation can.
 */
readonly class ApiOperation implements CoverageItem
{
    protected const string KEY_FACET_CODE = ' code ';

    protected const string KEY_FACET_SCENARIO = ' scenario ';

    public function __construct(
        public string $verb,
        public string $uriTemplate,
        public ?int $status = null,
        public bool $addressesCollection = false,
        public ?string $code = null,
        public ?Scenario $scenario = null,
    ) {
    }

    public function key(): string
    {
        return $this->dispatchKey()
            . ($this->status !== null ? ' ' . $this->status : '')
            . ($this->code !== null ? static::KEY_FACET_CODE . $this->code : '')
            . ($this->scenario !== null ? static::KEY_FACET_SCENARIO . $this->scenario->value : '');
    }

    /**
     * The status-less identity — what the kernel-request recorder can observe. A declaration for an
     * error response is runtime-verified by the operation being dispatched; the response status
     * itself is the test body's assertion.
     */
    public function dispatchKey(): string
    {
        return strtoupper($this->verb) . ' ' . $this->uriTemplate;
    }

    /**
     * The keys a declaration counts for: its own, the status item it narrows - a test that proves
     * one code of a status has proven that status too - and the error-code and scenario items it
     * proves.
     *
     * @return array<string>
     */
    public function coverageKeys(): array
    {
        $keys = [$this->key(), $this->withoutFacets()->key()];

        if ($this->code !== null) {
            $keys[] = $this->errorCodeItem()->key();
        }

        if ($this->scenario !== null) {
            $keys[] = $this->scenarioItem()->key();
        }

        return array_values(array_unique($keys));
    }

    /**
     * The error-code item this entry proves, without the scenario it was proven in.
     */
    public function errorCodeItem(): self
    {
        return new self($this->verb, $this->uriTemplate, $this->status, $this->addressesCollection, $this->code);
    }

    /**
     * The scenario item this entry proves, which carries no status.
     */
    public function scenarioItem(): self
    {
        return new self($this->verb, $this->uriTemplate, scenario: $this->scenario);
    }

    /**
     * The operation or error response this entry narrows, which is what has to exist for it not to
     * be a stale operation claim.
     */
    public function withoutFacets(): self
    {
        return new self($this->verb, $this->uriTemplate, $this->status, $this->addressesCollection);
    }
}
