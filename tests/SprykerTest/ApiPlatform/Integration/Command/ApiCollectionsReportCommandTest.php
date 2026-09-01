<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Command;

use ReflectionClass;
use Spryker\ApiPlatform\Command\ApiCollectionsReportCommand;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Integration
 * @group Command
 * @group ApiCollectionsReportCommandTest
 * Add your own group annotations below this line
 */
class ApiCollectionsReportCommandTest extends ApiIntegrationTestCase
{
    protected function _before(): void
    {
        parent::_before();

        $this->tester->cleanupSchemaFiles();
    }

    protected function _after(): void
    {
        $this->tester->cleanupSchemaFiles();

        parent::_after();
    }

    public function testGivenUntypedListWhenRunningReportThenWritesJsonAndMarkdownArtifacts(): void
    {
        // Arrange
        $this->tester->createSchemaFile('Storefront', 'products', $this->getHandwrittenCategoriesSchema());
        $outputDir = $this->tester->createTemporaryDirectory();
        $command = $this->getService(ApiCollectionsReportCommand::class);

        // Act
        $exitCode = (new CommandTester($command))->execute(['--output-dir' => $outputDir]);

        // Assert
        $this->assertSame(0, $exitCode);
        $report = json_decode((string)file_get_contents($outputDir . '/untyped-collections.json'), true);
        $this->assertSame('handwritten', $report['rows'][0]['state']);
        $this->assertStringContainsString('categories', (string)file_get_contents($outputDir . '/untyped-collections.md'));
    }

    public function testGivenCommittedReportMatchingCurrentSchemasWhenCheckingThenSucceeds(): void
    {
        // Arrange — write the report, then re-run in --check mode against it.
        $this->tester->createSchemaFile('Storefront', 'products', $this->getUnknownCategoriesSchema());
        $outputDir = $this->tester->createTemporaryDirectory();
        $command = $this->getService(ApiCollectionsReportCommand::class);
        (new CommandTester($command))->execute(['--output-dir' => $outputDir]);

        // Act
        $exitCode = (new CommandTester($command))->execute(['--output-dir' => $outputDir, '--check' => true]);

        // Assert
        $this->assertSame(0, $exitCode);
    }

    /**
     * Configured api types narrower than what exists on disk: only "storefront" is configured, but a
     * resource with an adoptable list also exists under "backend". A regression to returning the
     * configured list early (instead of unioning it with the disk scan) would silently drop "backend"
     * from the inventory without any test failing to say so. The container's own ApiPlatformConfig
     * service is already initialized by this point (ApiIntegrationTestCase's _before() sets it once),
     * and Symfony's TestContainer refuses a second replacement of an already-initialized service — so
     * the narrowed config is wired into a fresh command instance directly, reusing the container's
     * already-resolved collaborators for everything else.
     */
    public function testGivenApiTypeAbsentFromConfiguredListWhenRunningReportThenDiskDiscoveredApiTypeStillAppears(): void
    {
        // Arrange
        $this->tester->createSchemaFile('Backend', 'stores', $this->getUnknownCategoriesSchema());
        $testConfig = $this->tester->getTestConfig();
        $narrowedConfig = new ApiPlatformConfig(
            sourceDirectories: $testConfig->getSourceDirectories(),
            cacheDir: $testConfig->getCacheDir(),
            generatedDir: $testConfig->getGeneratedResourcesDirectory(),
            apiTypes: ['storefront'],
            debug: true,
        );
        $wiredCommand = $this->getService(ApiCollectionsReportCommand::class);
        $collaborators = new ReflectionClass($wiredCommand);
        $command = new ApiCollectionsReportCommand(
            $collaborators->getProperty('schemaFinder')->getValue($wiredCommand),
            $collaborators->getProperty('loaders')->getValue($wiredCommand),
            $collaborators->getProperty('schemaParser')->getValue($wiredCommand),
            $collaborators->getProperty('schemaMerger')->getValue($wiredCommand),
            $narrowedConfig,
            $collaborators->getProperty('inventoryBuilder')->getValue($wiredCommand),
        );
        $outputDir = $this->tester->createTemporaryDirectory();

        // Act
        $exitCode = (new CommandTester($command))->execute(['--output-dir' => $outputDir]);

        // Assert
        $this->assertSame(0, $exitCode);
        $report = json_decode((string)file_get_contents($outputDir . '/untyped-collections.json'), true);
        $apiTypes = array_column($report['rows'], 'apiType');
        $this->assertContains('backend', $apiTypes);
    }

    public function testGivenSchemaReachedThroughVendorPathSegmentWhenRunningReportThenExcludedFromInventory(): void
    {
        // Arrange — mirrors `vendor/spryker-eco` in the real `sourceDirectories` config: a schema file
        // reached through a `vendor/` path component must not contribute rows, even though "storefront"
        // is otherwise a fully configured, populated ApiType. Nesting the fixture under
        // `TestModule/vendor/fake-eco` (rather than adding a new top-level source directory) is enough
        // to reach both filesystem-walking guards: `SchemaFinder` matches any directory ending in
        // `resources/api/storefront` regardless of depth, so this file is found exactly as
        // `vendor/spryker-eco`'s real files are, and `cleanupSchemaFiles()`'s recursive removal of
        // `TestModule` cleans it up for free.
        $this->tester->createSchemaFile(
            'Storefront',
            'vendor-widgets',
            $this->getUnknownVendorWidgetsSchema(),
            'TestModule/vendor/fake-eco',
        );
        $outputDir = $this->tester->createTemporaryDirectory();
        $command = $this->getService(ApiCollectionsReportCommand::class);

        // Act
        $exitCode = (new CommandTester($command))->execute(['--output-dir' => $outputDir]);

        // Assert
        $this->assertSame(0, $exitCode);
        $report = json_decode((string)file_get_contents($outputDir . '/untyped-collections.json'), true);
        $this->assertNotContains('vendor-widgets', array_column($report['rows'], 'resource'));
    }

    public function testGivenNewUntypedListWhenCheckingThenFailsWithDiff(): void
    {
        // Arrange — a committed report that predates a newly added untyped list.
        $outputDir = $this->tester->createTemporaryDirectory();
        file_put_contents($outputDir . '/untyped-collections.json', json_encode(['rows' => []], JSON_PRETTY_PRINT) . "\n");
        $this->tester->createSchemaFile('Storefront', 'products', $this->getUnknownCategoriesSchema());
        $command = $this->getService(ApiCollectionsReportCommand::class);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--output-dir' => $outputDir, '--check' => true]);

        // Assert
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('categories', $commandTester->getDisplay());
    }

    public function testGivenListRemovedFromSchemasWhenCheckingThenFailsWithTheRemovedRow(): void
    {
        // Arrange — a committed report naming a row no schema on disk produces any more. The opposite
        // direction of the added-row gate, and the one an adoption commit trips when it forgets to
        // regenerate: the property became typed, so its untyped row should have disappeared.
        $outputDir = $this->tester->createTemporaryDirectory();
        $this->tester->createSchemaFile('Storefront', 'products', $this->getUnknownCategoriesSchema());
        $command = $this->getService(ApiCollectionsReportCommand::class);
        (new CommandTester($command))->execute(['--output-dir' => $outputDir]);

        // The list property is gone, but the resource itself stays on disk: dropping the schema file
        // altogether would leave `SchemaFinder` without a directory to scan, which is a different
        // failure than a row disappearing from the inventory.
        $this->tester->cleanupSchemaFiles();
        $this->tester->createSchemaFile('Storefront', 'products', $this->getListlessProductsSchema());
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--output-dir' => $outputDir, '--check' => true]);

        // Assert
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('- ', $commandTester->getDisplay());
        $this->assertStringContainsString('categories', $commandTester->getDisplay());
    }

    public function testGivenPropertyThatBecameTypedWhenCheckingThenFailsWithBothDirections(): void
    {
        // Arrange — the same property in both reports, its state flipped from `unknown` to `typed`.
        // A gate keyed on presence alone would pass this: the row neither appeared nor vanished.
        $outputDir = $this->tester->createTemporaryDirectory();
        $this->tester->createSchemaFile('Storefront', 'products', $this->getUnknownCategoriesSchema());
        $command = $this->getService(ApiCollectionsReportCommand::class);
        (new CommandTester($command))->execute(['--output-dir' => $outputDir]);

        $this->tester->cleanupSchemaFiles();
        $this->tester->createSchemaFile('Storefront', 'products', $this->getTypedCategoriesSchema());
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--output-dir' => $outputDir, '--check' => true]);

        // Assert
        $display = $commandTester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('+ ', $display);
        $this->assertStringContainsString('- ', $display);
        $this->assertStringContainsString('typed', $display);
        $this->assertStringContainsString('unknown', $display);
    }

    public function testGivenStaleCommittedMarkdownWhenCheckingThenFails(): void
    {
        // Arrange — rows agree, markdown does not. Without a markdown gate the run reports success and
        // the half of the report humans actually read stays stale.
        $outputDir = $this->tester->createTemporaryDirectory();
        $this->tester->createSchemaFile('Storefront', 'products', $this->getUnknownCategoriesSchema());
        $command = $this->getService(ApiCollectionsReportCommand::class);
        (new CommandTester($command))->execute(['--output-dir' => $outputDir]);
        file_put_contents($outputDir . '/untyped-collections.md', "# hand-edited\n");
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--output-dir' => $outputDir, '--check' => true]);

        // Assert
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('untyped-collections.md', $commandTester->getDisplay());
    }

    public function testGivenMissingCommittedMarkdownWhenCheckingThenFails(): void
    {
        // Arrange
        $outputDir = $this->tester->createTemporaryDirectory();
        $this->tester->createSchemaFile('Storefront', 'products', $this->getUnknownCategoriesSchema());
        $command = $this->getService(ApiCollectionsReportCommand::class);
        (new CommandTester($command))->execute(['--output-dir' => $outputDir]);
        unlink($outputDir . '/untyped-collections.md');
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--output-dir' => $outputDir, '--check' => true]);

        // Assert
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('untyped-collections.md', $commandTester->getDisplay());
    }

    /**
     * `categories` describes its elements in `openapiContext.items` only — no sibling `items` — so it
     * must classify as `handwritten`.
     */
    protected function getHandwrittenCategoriesSchema(): string
    {
        return <<<'YAML'
        resource:
            name: Products
            shortName: products
            properties:
                categories:
                    type: array
                    openapiContext:
                        items:
                            type: object
                            properties:
                                categoryKey:
                                    type: string
        YAML;
    }

    /**
     * `categories` has no `items` and no usable example, so it must classify as `unknown`.
     */
    protected function getUnknownCategoriesSchema(): string
    {
        return <<<'YAML'
        resource:
            name: Products
            shortName: products
            properties:
                categories:
                    type: array
        YAML;
    }

    /**
     * The same resource with no list-shaped property at all, so it contributes no rows while still
     * giving `SchemaFinder` a populated directory to scan.
     */
    protected function getListlessProductsSchema(): string
    {
        return <<<'YAML'
        resource:
            name: Products
            shortName: products
            properties:
                sku:
                    type: string
        YAML;
    }

    /**
     * The adopted counterpart of {@see getUnknownCategoriesSchema()}: same resource and property, now
     * carrying a sibling `items` block, so the property classifies as `typed` instead of `unknown`.
     */
    protected function getTypedCategoriesSchema(): string
    {
        return <<<'YAML'
        resource:
            name: Products
            shortName: products
            properties:
                categories:
                    type: array
                    items:
                        type: object
                        properties:
                            categoryKey:
                                type: string
        YAML;
    }

    /**
     * A distinct resource name from the other fixtures in this file, so a leak through the `vendor/`
     * exclusion would be unambiguous in the assertion rather than coincidentally matching another test's
     * rows. `widgets` has no `items` and no usable example, so it would classify as `unknown` if the
     * exclusion failed to keep it out of the inventory entirely.
     */
    protected function getUnknownVendorWidgetsSchema(): string
    {
        return <<<'YAML'
        resource:
            name: VendorWidgets
            shortName: vendor-widgets
            properties:
                widgets:
                    type: array
        YAML;
    }
}
