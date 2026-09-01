<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

/**
 * Stands in for a generated nested value object: carries fromArray()/toArray() and no
 * #[ApiResource].
 */
final class CollectionFixtureItem
{
    public ?string $sku = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->sku = $data['sku'] ?? null;

        return $instance;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['sku' => $this->sku];
    }
}
