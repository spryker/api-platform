<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Command;

use Spryker\ApiPlatform\Command\ApiDebugCommand;
use Spryker\ApiPlatform\Command\ApiGenerateCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Integration
 * @group Command
 * @group ApiDebugCommandTest
 * Add your own group annotations below this line
 */
class ApiDebugCommandTest extends ApiIntegrationTestCase
{
    protected ?string $lastOutput = null;

    protected function _before(): void
    {
        parent::_before();

        $this->lastOutput = null;
        $this->cleanupSchemasAndGeneratedFiles();
    }

    protected function _after(): void
    {
        $this->cleanupSchemasAndGeneratedFiles();

        parent::_after();
    }

    public function testGivenLowercaseApiTypeWhenDebuggingThenListsResources(): void
    {
        // Arrange
        $this->tester->createSchemaFile('storefront', 'Customers', $this->getCustomerSchema());
        $this->generateApiResources('storefront');

        // Act
        $exitCode = $this->executeCommand(['--api-type' => 'storefront', '--list' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->getCommandOutput();
        $this->assertStringContainsString('Customers', $output);
    }

    public function testGivenUcfirstApiTypeWhenDebuggingThenListsResources(): void
    {
        // Arrange
        $this->tester->createSchemaFile('storefront', 'Customers', $this->getCustomerSchema());
        $this->generateApiResources('Storefront');

        // Act
        $exitCode = $this->executeCommand(['--api-type' => 'Storefront', '--list' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->getCommandOutput();
        $this->assertStringContainsString('Customers', $output);
    }

    public function testGivenResourceNameAndApiTypeWhenDebuggingThenShowsMergedSchema(): void
    {
        // Arrange
        $this->tester->createSchemaFile('storefront', 'Customers', $this->getCustomerSchema());
        $this->generateApiResources('storefront');

        // Act
        $exitCode = $this->executeCommand(['resourceName' => 'Customers', '--api-type' => 'storefront']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->getCommandOutput();
        $this->assertStringContainsString('Customers', $output);
    }

    public function testGivenListOptionWhenDebuggingThenListsAllResources(): void
    {
        // Arrange
        $this->tester->createSchemaFile('storefront', 'Customers', $this->getCustomerSchema());
        $this->tester->createSchemaFile('storefront', 'Products', $this->getProductSchema());
        $this->generateApiResources('storefront');

        // Act
        $exitCode = $this->executeCommand(['--api-type' => 'storefront', '--list' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->getCommandOutput();
        $this->assertStringContainsString('Customers', $output);
        $this->assertStringContainsString('Products', $output);
    }

    public function testGivenSchemaOptionWhenDebuggingThenShowsSchemaContent(): void
    {
        // Arrange
        $schema = $this->getCustomerSchema();
        $this->tester->createSchemaFile('backend', 'Customers', $schema);
        $this->generateApiResources('backend');

        // Act
        $exitCode = $this->executeCommand([
            'resourceName' => 'Customers',
            '--api-type' => 'backend',
            '--show-merged' => true,
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->getCommandOutput();
        $this->assertStringContainsString('properties', $output);
    }

    public function testGivenMultipleResourcesWhenDebuggingThenShowsAllResourcesForApiType(): void
    {
        // Arrange
        $this->tester->createSchemaFile('storefront', 'Customers', $this->getCustomerSchema());
        $this->tester->createSchemaFile('storefront', 'Orders', $this->getOrderSchema());
        $this->tester->createSchemaFile('backend', 'Products', $this->getProductSchema());
        $this->generateApiResources('storefront');
        $this->generateApiResources('backend');

        // Act
        $exitCode = $this->executeCommand(['--api-type' => 'storefront', '--list' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->getCommandOutput();
        $this->assertStringContainsString('Customers', $output);
        $this->assertStringContainsString('Orders', $output);
        $this->assertStringNotContainsString('Products', $output);
    }

    public function testGivenFormatOptionWhenDebuggingThenOutputsInSpecifiedFormat(): void
    {
        // Arrange
        $this->tester->createSchemaFile('storefront', 'Customers', $this->getCustomerSchema());
        $this->generateApiResources('storefront');

        // Act
        $exitCode = $this->executeCommand([
            'resourceName' => 'Customers',
            '--api-type' => 'storefront',
            '--show-merged' => true,
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->getCommandOutput();
        $this->assertStringContainsString('properties', $output);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getCustomerSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'identifier' => true],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
            ],
            'required' => ['id', 'name'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOrderSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'orderNumber' => ['type' => 'string'],
                'total' => ['type' => 'number'],
            ],
            'required' => ['id', 'orderNumber'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getProductSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'sku' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'price' => ['type' => 'number'],
            ],
            'required' => ['id', 'sku'],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function executeCommand(array $arguments): int
    {
        $command = $this->getService(ApiDebugCommand::class);

        $commandTester = $this->tester->getConsoleTester($command);

        $commandArgs = [];

        if (isset($arguments['resourceName'])) {
            $commandArgs['resource'] = $arguments['resourceName'];
        }

        if (isset($arguments['--api-type'])) {
            $commandArgs['--api-type'] = $arguments['--api-type'];
        }

        if (isset($arguments['--list']) && $arguments['--list']) {
            $commandArgs['--list'] = true;
        }

        if (isset($arguments['--show-merged']) && $arguments['--show-merged']) {
            $commandArgs['--show-merged'] = true;
        }

        $exitCode = $commandTester->execute($commandArgs, ['interactive' => false]);

        $this->lastOutput = $commandTester->getDisplay();

        return $exitCode;
    }

    protected function getCommandOutput(): string
    {
        return $this->lastOutput ?? '';
    }

    protected function generateApiResources(string $apiType): void
    {
        $command = $this->getService(ApiGenerateCommand::class);

        $commandTester = $this->tester->getConsoleTester($command);

        $commandTester->execute(
            ['api-type' => $apiType],
            ['interactive' => false],
        );
    }

    protected function cleanupSchemasAndGeneratedFiles(): void
    {
        $this->cleanupGeneratedFiles();
        $this->tester->cleanupSchemaFiles();
    }
}
