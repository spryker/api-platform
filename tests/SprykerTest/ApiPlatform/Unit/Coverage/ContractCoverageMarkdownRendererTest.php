<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageMarkdownRenderer;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute;
use Spryker\ApiPlatform\Contract\Coverage\SchemaDefect;
use Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ContractCoverageResultBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ContractCoverageMarkdownRendererTest
 * Add your own group annotations below this line
 */
class ContractCoverageMarkdownRendererTest extends Unit
{
    public function testGivenEverythingCoveredWhenRenderingThenItReportsPassWithTheCounterTableOnly(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withSelectedResources('wishlists', 'wishlist-items')
            ->withGeneratedResourceCount(116)
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->build();

        // Act
        $markdown = (new ContractCoverageMarkdownRenderer())->render($result);

        // Assert
        $this->assertStringContainsString('## API contract coverage', $markdown);
        $this->assertStringContainsString('✅ **PASS**', $markdown);
        $this->assertStringContainsString('| Operations | 1 / 1 | 0 | 0 |', $markdown);
        $this->assertStringContainsString(
            'Enforced scope: 2 of 116 generated resources — `wishlists`, `wishlist-items`',
            $markdown,
        );
        $this->assertStringNotContainsString('###', $markdown);
    }

    public function testGivenUncoveredOperationsWhenRenderingThenTheyGetTheirOwnHeadingAndBulletList(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(...array_fill(0, 13, new ApiOperation('GET', '/wishlists')))
            ->withUncoveredOperations(
                new ApiOperation('GET', '/orders'),
                new ApiOperation('GET', '/orders/{orderReference}'),
                new ApiOperation('GET', '/orders/{orderReference}', 404),
            )
            ->build();

        // Act
        $markdown = (new ContractCoverageMarkdownRenderer())->render($result);

        // Assert
        $this->assertStringContainsString('❌ **FAIL** — 3 uncovered operation(s)', $markdown);
        $this->assertStringContainsString('| Operations | 13 / 16 | 3 | 0 |', $markdown);
        $this->assertStringContainsString('### Uncovered operations (3)', $markdown);
        $this->assertStringContainsString('- `GET /orders`', $markdown);
        $this->assertStringContainsString('- `GET /orders/{orderReference} 404`', $markdown);
    }

    public function testGivenCoveredEntriesWhenRenderingThenTheyAreNeverListed(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withNonServableOperations(new ApiOperation('GET', '/wishlists/{wishlistUuid}/wishlist-items/{uuid}'))
            ->withUncoveredOperations(new ApiOperation('GET', '/orders'))
            ->build();

        // Act
        $markdown = (new ContractCoverageMarkdownRenderer())->render($result);

        // Assert
        $this->assertStringNotContainsString('### Covered operations', $markdown);
        $this->assertStringContainsString('### Non-servable operations (no provider — review the resource) (1)', $markdown);
        $this->assertStringContainsString('### Uncovered operations (1)', $markdown);
    }

    public function testGivenStaleClaimsWhenRenderingThenEachGetsItsOwnHeading(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withStaleOperations(new ApiOperation('GET', '/gone'))
            ->withStaleValidations(new ValidationConstraint('wishlists', 'gone', 'NotBlank', 'POST', '/wishlists'))
            ->build();

        // Act
        $markdown = (new ContractCoverageMarkdownRenderer())->render($result);

        // Assert
        $this->assertStringContainsString('### Stale operation claims (1)', $markdown);
        $this->assertStringContainsString('### Stale validation claims (1)', $markdown);
        $this->assertStringContainsString('- `GET /gone`', $markdown);
    }

    public function testGivenNoEnforcedResourcesWhenRenderingThenTheScopeLineSaysNone(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withSelectedResources()
            ->withGeneratedResourceCount(116)
            ->build();

        // Act
        $markdown = (new ContractCoverageMarkdownRenderer())->render($result);

        // Assert
        $this->assertStringContainsString('Enforced scope: 0 of 116 generated resources — none', $markdown);
    }

    public function testGivenAnyResultWhenRenderingThenTheOutputEndsWithASingleNewline(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()->build();

        // Act
        $markdown = (new ContractCoverageMarkdownRenderer())->render($result);

        // Assert
        $this->assertStringEndsWith("\n", $markdown);
        $this->assertStringEndsNotWith("\n\n", $markdown);
    }

    public function testGivenUncoveredResponseAttributesWhenRenderingThenTheyGetATableNamingTheDeclaringTests(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withUncoveredResponseAttributes(
                new ResponseAttribute('GET /agent-customer-search', 'customers[].email'),
                new ResponseAttribute('GET /orders', 'orderReference'),
            )
            ->withOperationDeclarers([
                'GET /agent-customer-search' => ['AgentCustomerSearchStorefrontApiIntegrationTest::testGivenACustomerWhenSearchingThenItIsReturned'],
            ])
            ->build();

        // Act
        $markdown = (new ContractCoverageMarkdownRenderer())->render($result);

        // Assert — a bullet list of `operation  attribute` keys is unreadable on the summary page,
        // so the dimension renders as a table instead of through the generic section loop.
        $this->assertStringContainsString('### Uncovered response attributes (2)', $markdown);
        $this->assertStringContainsString('| Operation | Attribute | Covered by |', $markdown);
        $this->assertStringContainsString(
            '| `GET /agent-customer-search` | `customers[].email` | '
            . '`AgentCustomerSearchStorefrontApiIntegrationTest::testGivenACustomerWhenSearchingThenItIsReturned` |',
            $markdown,
        );
        $this->assertStringContainsString('| `GET /orders` | `orderReference` | (no test declares this operation yet) |', $markdown);
        $this->assertStringNotContainsString('- `GET /orders  orderReference`', $markdown);
        $this->assertStringContainsString('2 uncovered response attribute(s)', $markdown);
    }

    public function testGivenASchemaDefectWhenRenderingThenItGetsItsOwnSectionNamingTheSchemaFile(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withSchemaDefects(new SchemaDefect(
                new ApiOperation('PATCH', '/customer-password/{customerReference}'),
                'customer-password',
                ['src/Spryker/CustomersRestApi/resources/api/storefront/customer-password.resource.yml'],
            ))
            ->build();

        // Act
        $markdown = (new ContractCoverageMarkdownRenderer())->render($result);

        // Assert
        $this->assertStringContainsString('### Schema defects (1)', $markdown);
        $this->assertStringContainsString('`customer-password` `PATCH /customer-password/{customerReference}`', $markdown);
        $this->assertStringContainsString(
            '`src/Spryker/CustomersRestApi/resources/api/storefront/customer-password.resource.yml`',
            $markdown,
        );
        $this->assertStringContainsString('1 operation(s) without schema-declared responses', $markdown);
    }
}
