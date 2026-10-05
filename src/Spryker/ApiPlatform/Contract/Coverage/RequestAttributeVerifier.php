<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Holds a `#[CoversApiRequestAttributes]` test to what its successful requests sent. A path counts
 * only once a 2xx request of the claimed operation carried it with a value: an attribute sent only
 * in a rejected request proves the rejection, not that the API accepts it.
 */
class RequestAttributeVerifier
{
    public function __construct(protected RequestAttributePathExtractor $pathExtractor)
    {
    }

    /**
     * @param array<string, array<string>> $expectedByDispatchKey
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RecordedExchange> $exchanges
     *
     * @return array<string, array<string>> dispatch key => the paths no successful request sent
     */
    public function verify(array $expectedByDispatchKey, array $exchanges): array
    {
        $sent = [];
        foreach ($exchanges as $exchange) {
            if (!$exchange->isSuccessful()) {
                continue;
            }

            foreach ($this->pathExtractor->extract($exchange->requestAttributes) as $path) {
                $sent[$exchange->operation->dispatchKey()][$path] = true;
            }
        }

        $missing = [];
        foreach ($expectedByDispatchKey as $dispatchKey => $paths) {
            $unsent = array_values(array_filter($paths, static fn (string $path): bool => !isset($sent[$dispatchKey][$path])));
            if ($unsent !== []) {
                $missing[$dispatchKey] = $unsent;
            }
        }

        return $missing;
    }
}
