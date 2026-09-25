<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageConsoleRenderer;
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
 * @group ContractCoverageConsoleRendererTest
 * Add your own group annotations below this line
 */
class ContractCoverageConsoleRendererTest extends Unit
{
    protected const string DECLARING_TEST = 'PyzTest\\Glue\\Agents\\StorefrontApi\\Integration'
        . '\\AgentCustomerSearchStorefrontApiIntegrationTest'
        . '::testGivenAPersistedCustomerWhenGetAgentCustomerSearchByEmailThenTheCustomerIsReturned';

    public function testGivenEverythingCoveredWhenRenderingThenItReportsPassWithoutListingAnyEntry(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withSelectedResources('wishlists')
            ->withGeneratedResourceCount(116)
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withCoveredValidations(new ValidationConstraint('wishlists', 'name', 'NotBlank', 'POST', '/wishlists'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringContainsString('Scope: 1 of 116 generated resources enforced', $report);
        $this->assertStringContainsString('<info>Contract coverage gate: PASS</info>', $report);
        $this->assertStringNotContainsString('GET /wishlists', $report);
        $this->assertStringNotContainsString('[Uncovered operations]', $report);
    }

    public function testGivenUncoveredOperationsWhenRenderingThenOnlyTheGapIsListedAndTheGateFails(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withUncoveredOperations(
                new ApiOperation('GET', '/orders'),
                new ApiOperation('GET', '/orders/{orderReference}'),
            )
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringContainsString('[Uncovered operations] (2)', $report);
        $this->assertStringContainsString('  - GET /orders', $report);
        $this->assertStringNotContainsString('[Covered operations]', $report);
        $this->assertStringContainsString('Contract coverage gate: FAIL (2 uncovered operation(s))', $report);
    }

    public function testGivenAMixOfSectionsWhenRenderingThenTheCountersReportEachOne(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(...array_fill(0, 13, new ApiOperation('GET', '/wishlists')))
            ->withUncoveredOperations(...array_fill(0, 3, new ApiOperation('GET', '/orders')))
            ->withNonServableOperations(new ApiOperation('GET', '/wishlists/{wishlistUuid}/wishlist-items/{uuid}'))
            ->withCoveredValidations(...array_fill(0, 5, new ValidationConstraint('wishlists', 'name', 'NotBlank', 'POST', '/wishlists')))
            ->withCoveredResponseAttributes(...array_fill(0, 12, new ResponseAttribute('GET /wishlists', 'name')))
            ->withUncoveredResponseAttributes(...array_fill(0, 4, new ResponseAttribute('GET /orders', 'orderReference')))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringContainsString('Operations          13/16 covered · 3 uncovered · 0 stale · 1 non-servable · 0 internal', $report);
        $this->assertStringContainsString('Validation rules      5/5 covered · 0 uncovered · 0 stale', $report);
        $this->assertStringContainsString('Response attributes 12/16 covered · 4 uncovered', $report);
    }

    public function testGivenStaleClaimsWhenRenderingThenTheyAreListedUnderTheirOwnSection(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withStaleOperations(new ApiOperation('GET', '/gone'))
            ->withStaleValidations(new ValidationConstraint('wishlists', 'gone', 'NotBlank', 'POST', '/wishlists'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringContainsString('[Stale operation claims] (1)', $report);
        $this->assertStringContainsString('  - GET /gone', $report);
        $this->assertStringContainsString('[Stale validation claims] (1)', $report);
    }

    public function testGivenCollapsedEntriesWhenRenderingThenTheVerboseHintIsShown(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringContainsString('Run with -v to list every covered entry.', $report);
    }

    public function testGivenNothingCollapsedWhenRenderingThenTheVerboseHintIsOmitted(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withUncoveredOperations(new ApiOperation('GET', '/orders'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringNotContainsString('Run with -v', $report);
    }

    public function testGivenVerboseModeWhenRenderingThenCoveredEntriesAreRestored(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withCoveredValidations(new ValidationConstraint('wishlists', 'name', 'NotBlank', 'POST', '/wishlists'))
            ->withCoveredResponseAttributes(new ResponseAttribute('GET /wishlists', 'name'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result, true));

        // Assert
        $this->assertStringContainsString('[Covered operations] (1)', $report);
        $this->assertStringContainsString('  - GET /wishlists', $report);
        $this->assertStringContainsString('[Covered validation rules] (1)', $report);
        $this->assertStringContainsString('[Covered response attributes] (1)', $report);
        $this->assertStringContainsString('  - GET /wishlists  name', $report);
        $this->assertStringNotContainsString('Run with -v', $report);
    }

    public function testGivenANonServableOperationWhenRenderingWithoutVerboseThenItIsStillReported(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withNonServableOperations(new ApiOperation('GET', '/wishlists/{wishlistUuid}/wishlist-items/{uuid}'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result, false));

        // Assert
        $this->assertStringContainsString('[Non-servable operations (no provider — review the resource)] (1)', $report);
        $this->assertStringContainsString('  - GET /wishlists/{wishlistUuid}/wishlist-items/{uuid}', $report);
    }

    public function testGivenAnInternalOperationWhenRenderingWithoutVerboseThenItIsCountedButNotListed(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withInternalOperations(new ApiOperation('GET', '/wishlists/{wishlistUuid}/wishlist-items/{uuid}'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result, false));

        // Assert — an internal operation is a declared answer, not a finding, so the default report
        // says how many there are and nothing more.
        $this->assertStringContainsString('1 internal', $report);
        $this->assertStringNotContainsString('Non-servable operations', $report);
        $this->assertStringNotContainsString('[Internal operations', $report);
    }

    public function testGivenAnInternalOperationWhenRenderingVerboselyThenItIsListedInItsOwnSection(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withInternalOperations(new ApiOperation('GET', '/wishlists/{wishlistUuid}/wishlist-items/{uuid}'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result, true));

        // Assert
        $this->assertStringContainsString('[Internal operations (IRI anchors, not reachable and not published)] (1)', $report);
        $this->assertStringContainsString('  - GET /wishlists/{wishlistUuid}/wishlist-items/{uuid}', $report);
    }

    public function testGivenUncoveredResponseAttributesWhenRenderingThenEachNamesTheTestsThatDeclareItsOperation(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withUncoveredResponseAttributes(
                new ResponseAttribute('GET /agent-customer-search', 'customers[].email'),
                new ResponseAttribute('GET /agent-customer-search', 'customers[].firstName'),
            )
            ->withOperationDeclarers([
                'GET /agent-customer-search' => [static::DECLARING_TEST],
            ])
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert — the tests that already declare the operation are where the marker belongs, so
        // the report points the sweep straight at them.
        $this->assertStringContainsString('[Uncovered response attributes] (2)', $report);
        $this->assertStringContainsString('  - GET /agent-customer-search  customers[].email', $report);
        $this->assertStringContainsString('      covered by: ' . static::DECLARING_TEST, $report);
        $this->assertStringContainsString('  - GET /agent-customer-search  customers[].firstName', $report);
        $this->assertStringContainsString('2 uncovered response attribute(s)', $report);
    }

    public function testGivenAnUncoveredResponseAttributeWhoseOperationNoTestDeclaresWhenRenderingThenThePlaceholderIsShown(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withUncoveredResponseAttributes(new ResponseAttribute('GET /orders', 'orderReference'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringContainsString('      covered by: (no test declares this operation yet)', $report);
    }

    public function testGivenAnUncoveredResponseAttributeWhoseOperationHasAnEmptyDeclarerListWhenRenderingThenThePlaceholderIsShown(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withUncoveredResponseAttributes(new ResponseAttribute('GET /orders', 'orderReference'))
            ->withOperationDeclarers(['GET /orders' => []])
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert — a known-but-empty declarer list says the same thing as no entry at all, and the
        // markdown renderer reads it that way too.
        $this->assertStringContainsString('      covered by: (no test declares this operation yet)', $report);
    }

    public function testGivenASchemaDefectWhenRenderingThenTheOperationAndItsSchemaFilesAreNamed(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withSelectedResources('customer-password')
            ->withSchemaDefects(new SchemaDefect(
                new ApiOperation('PATCH', '/customer-password/{customerReference}'),
                'customer-password',
                ['src/Spryker/CustomersRestApi/resources/api/storefront/customer-password.resource.yml'],
            ))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringContainsString(
            'SCHEMA DEFECT  customer-password  PATCH /customer-password/{customerReference}',
            $report,
        );
        $this->assertStringContainsString('openapiContext.responses', $report);
        $this->assertStringContainsString(
            '    - src/Spryker/CustomersRestApi/resources/api/storefront/customer-password.resource.yml',
            $report,
        );
        $this->assertStringContainsString('vendor/bin/glue api:generate', $report);
        $this->assertStringContainsString('1 operation(s) without schema-declared responses', $report);
    }

    public function testGivenNoSchemaDefectWhenRenderingThenNoDefectBlockIsPrinted(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->build();

        // Act
        $report = implode("\n", (new ContractCoverageConsoleRenderer())->render($result));

        // Assert
        $this->assertStringNotContainsString('SCHEMA DEFECT', $report);
    }
}
