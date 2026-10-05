<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Replay;

/**
 * What a suite supplies to replay one generated example against its fixtures: real values for the
 * path variables, the headers (a token), attribute values that must reference a fixture (a real
 * `sku`, by dot path), or the reason the operation cannot be replayed at all.
 */
readonly class OpenApiExampleReplayContext
{
    /**
     * @param array<string, string> $uriVariables
     * @param array<string, string> $headers
     * @param array<string, mixed> $bodyAttributeOverrides Dot path below `data.attributes` => value.
     */
    public function __construct(
        public array $uriVariables = [],
        public array $headers = [],
        public array $bodyAttributeOverrides = [],
        public ?string $skipReason = null,
    ) {
    }

    public static function skip(string $reason): self
    {
        return new self(skipReason: $reason);
    }
}
