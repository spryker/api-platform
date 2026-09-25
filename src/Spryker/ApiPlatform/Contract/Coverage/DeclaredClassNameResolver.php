<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Reads the name of the class a PHP source file declares from its token stream.
 */
class DeclaredClassNameResolver
{
    /**
     * Only a real `class` declaration counts: `::class` fetches and enum/interface/trait
     * declarations are skipped.
     */
    public function resolve(string $source): ?string
    {
        $tokens = token_get_all($source);

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            // `Foo::class` also tokenises as T_CLASS, with the object operator in front of it.
            $previous = $this->previousMeaningfulToken($tokens, $index);
            if (is_array($previous) && $previous[0] === T_DOUBLE_COLON) {
                continue;
            }

            $next = $this->nextMeaningfulToken($tokens, $index);
            if (is_array($next) && $next[0] === T_STRING) {
                return $next[1];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: int, 1: string, 2: int}|string|null
     */
    protected function previousMeaningfulToken(array $tokens, int $index): array|string|null
    {
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            if (!$this->isSkippableToken($tokens[$cursor])) {
                return $tokens[$cursor];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: int, 1: string, 2: int}|string|null
     */
    protected function nextMeaningfulToken(array $tokens, int $index): array|string|null
    {
        $total = count($tokens);
        for ($cursor = $index + 1; $cursor < $total; $cursor++) {
            if (!$this->isSkippableToken($tokens[$cursor])) {
                return $tokens[$cursor];
            }
        }

        return null;
    }

    /**
     * @param array{0: int, 1: string, 2: int}|string $token
     */
    protected function isSkippableToken(array|string $token): bool
    {
        return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }
}
