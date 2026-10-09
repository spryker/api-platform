<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use PhpParser\Node\Stmt\ClassMethod;

/**
 * One method body {@see ThrownStatusAnalyzer} reads, with what its names mean: `$this` and
 * `static` are the class the call started on, `self` and `parent` the class that declares the body.
 */
readonly class AnalyzedMethod
{
    /**
     * @param class-string $boundClassName
     * @param class-string $declaringClassName
     * @param array<string, array<int>> $statusesByParameter The statuses each integer parameter can carry, as the call site passed them.
     */
    public function __construct(
        public ClassMethod $node,
        public string $boundClassName,
        public string $declaringClassName,
        public array $statusesByParameter,
    ) {
    }

    public function key(): string
    {
        return $this->boundClassName . '::' . $this->node->name->toString();
    }
}
