<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Command;

use Spryker\ApiPlatform\Command\ApiGenerateCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Integration
 * @group Command
 * @group ApiGenerateCommandTest
 * Add your own group annotations below this line
 */
class ApiGenerateCommandTest extends ApiIntegrationTestCase
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

    public function testGivenLowercaseApiTypeWhenGeneratingThenSucceeds(): void
    {
        // Arrange
        $this->tester->createSchemaFile('backend', 'Customers', $this->getCustomerSchema());

        // Act
        $exitCode = $this->executeCommand(['api-type' => 'backend']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode, sprintf('Command failed with output: %s', $this->getCommandOutput()));
        $this->tester->assertGeneratedClassExists('backend', 'Customers');
        $this->tester->assertGeneratedNamespace('backend', 'Customers', 'Generated\Api\Backend');
    }

    public function testGivenUcfirstApiTypeWhenGeneratingThenSucceeds(): void
    {
        // Arrange
        $this->tester->createSchemaFile('backend', 'Customers', $this->getCustomerSchema());

        // Act
        $exitCode = $this->executeCommand(['api-type' => 'backend']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->tester->assertGeneratedClassExists('backend', 'Customers');
    }

    public function testGivenUppercaseApiTypeWhenGeneratingThenSucceeds(): void
    {
        // Arrange
        $this->tester->createSchemaFile('backend', 'Customers', $this->getCustomerSchema());

        // Act
        $exitCode = $this->executeCommand(['api-type' => 'backend']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->tester->assertGeneratedClassExists('backend', 'Customers');
    }

    public function testGivenMixedCaseApiTypeWhenGeneratingThenSucceeds(): void
    {
        // Arrange
        $this->tester->createSchemaFile('backend', 'Customers', $this->getCustomerSchema());

        // Act
        $exitCode = $this->executeCommand(['api-type' => 'backend']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->tester->assertGeneratedClassExists('backend', 'Customers');
    }

    public function testGivenNullApiTypeWhenGeneratingThenGeneratesAllTypes(): void
    {
        // Arrange
        $this->tester->createSchemaFile('backend', 'Customers', $this->getCustomerSchema());
        $this->tester->createSchemaFile('storefront', 'Orders', $this->getOrderSchema());

        // Act
        $exitCode = $this->executeCommand(['api-type' => null]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->tester->assertGeneratedClassExists('backend', 'Customers');
        $this->tester->assertGeneratedClassExists('Storefront', 'Orders');
    }

    public function testGivenValidInputWhenGeneratingThenCreatesCorrectDirectoryStructure(): void
    {
        // Arrange
        $this->tester->createSchemaFile('backend', 'Products', $this->getProductSchema());

        // Act
        $exitCode = $this->executeCommand(['api-type' => 'backend']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->tester->assertGeneratedDirectoryStructure('backend', [
            'ProductsBackendResource.php',
        ]);
    }

    public function testGivenValidInputWhenGeneratingThenCreatesClassesWithCorrectNamespace(): void
    {
        // Arrange
        $this->tester->createSchemaFile('backend', 'Products', $this->getProductSchema());

        // Act
        $exitCode = $this->executeCommand(['api-type' => 'backend']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);

        $content = $this->tester->getGeneratedClassContent('backend', 'Products');
        $this->assertStringContainsString('namespace Generated\Api\Backend;', $content);

        $this->tester->assertGeneratedClassExists('backend', 'Products');
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
                'id' => ['type' => 'integer', 'identifier' => true],
                'orderNumber' => ['type' => 'string'],
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
                'id' => ['type' => 'integer', 'identifier' => true],
                'sku' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
            'required' => ['id', 'sku', 'name'],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function executeCommand(array $arguments): int
    {
        $command = $this->getService(ApiGenerateCommand::class);

        $commandTester = $this->tester->getConsoleTester($command);

        $commandArgs = [];

        if (isset($arguments['api-type'])) {
            $commandArgs['api-type'] = $arguments['api-type'];
        }

        $exitCode = $commandTester->execute($commandArgs, ['interactive' => false]);

        $this->lastOutput = $commandTester->getDisplay();

        return $exitCode;
    }

    protected function getCommandOutput(): string
    {
        return $this->lastOutput ?? '';
    }

    protected function cleanupSchemasAndGeneratedFiles(): void
    {
        $this->cleanupGeneratedFiles();
        $this->tester->cleanupSchemaFiles();
    }
}
