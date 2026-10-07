<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

use ApiPlatform\Metadata\ApiProperty;

class WhitespaceTrimmingFixture
{
    public ?string $name = null;

    public ?string $description = null;

    #[ApiProperty(extraProperties: ['allowWhitespace' => true])]
    public ?string $password = null;

    public ?int $quantity = null;

    public ?WhitespaceTrimmingNestedFixture $address = null;

    /**
     * @var array<mixed>|null
     */
    public ?array $tags = null;

    /**
     * @var array<mixed>|null
     */
    #[ApiProperty(extraProperties: ['allowWhitespace' => true])]
    public ?array $codes = null;

    /**
     * @var array<mixed>|null
     */
    public ?array $addresses = null;

    protected ?string $internalNote = null;

    public function getInternalNote(): ?string
    {
        return $this->internalNote;
    }

    public function setInternalNote(?string $internalNote): void
    {
        $this->internalNote = $internalNote;
    }
}
