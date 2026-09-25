<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;

/**
 * The collector resolves a test file's class by reading its token stream. Reading the source text
 * instead lets any prose that happens to contain the word `class` followed by another word stand in
 * for the declaration, and the gate then dies reflecting a class nobody wrote.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group DeclaredClassNameResolverTest
 * Add your own group annotations below this line
 */
class DeclaredClassNameResolverTest extends Unit
{
    public function testGivenADocblockMentioningAClassWhenResolvingThenTheDeclarationWins(): void
    {
        // Arrange — the docblock says "class through", which is what a text match would return.
        $source = <<<'PHP'
        <?php

        namespace Some\Test\Namespace;

        /**
         * Rules reach the generated value-object class through the cascade on the parent property.
         */
        class TheRealTestClass
        {
        }
        PHP;

        // Act
        $shortName = ContractCoverageFactory::createDeclaredClassNameResolver()->resolve($source);

        // Assert
        $this->assertSame('TheRealTestClass', $shortName);
    }

    public function testGivenALeadingClassConstantFetchWhenResolvingThenTheDeclarationWins(): void
    {
        // Arrange — `::class` tokenises as T_CLASS too.
        $source = <<<'PHP'
        <?php

        namespace Some\Test\Namespace;

        use Other\Thing;

        class TheRealTestClass
        {
            public const string SUBJECT = Thing::class;
        }
        PHP;

        // Act
        $shortName = ContractCoverageFactory::createDeclaredClassNameResolver()->resolve($source);

        // Assert
        $this->assertSame('TheRealTestClass', $shortName);
    }

    public function testGivenASingleLineCommentMentioningAClassWhenResolvingThenTheDeclarationWins(): void
    {
        // Arrange
        $source = <<<'PHP'
        <?php

        namespace Some\Test\Namespace;

        // every class below is loaded by hand
        class TheRealTestClass
        {
        }
        PHP;

        // Act
        $shortName = ContractCoverageFactory::createDeclaredClassNameResolver()->resolve($source);

        // Assert
        $this->assertSame('TheRealTestClass', $shortName);
    }

    public function testGivenAFileWithoutAClassDeclarationWhenResolvingThenItIsSkipped(): void
    {
        // Arrange
        $source = <<<'PHP'
        <?php

        namespace Some\Test\Namespace;

        interface NotAClass
        {
        }
        PHP;

        // Act
        $shortName = ContractCoverageFactory::createDeclaredClassNameResolver()->resolve($source);

        // Assert
        $this->assertNull($shortName);
    }
}
