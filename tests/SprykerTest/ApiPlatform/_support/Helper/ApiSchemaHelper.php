<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper for managing API schema test fixtures and filesystem.
 *
 * Provides methods for:
 * - Creating temporary schema files for tests
 * - Verifying code generation output
 * - Checking generated class structure and naming conventions
 */
class ApiSchemaHelper extends Module
{
    protected ?string $tempDir = null;

    protected ?ApiPlatformConfig $testConfig = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [
        'project_root' => null,
        'generated_namespace' => 'Generated\Api',
    ];

    public function _before(TestInterface $test): void
    {
        parent::_before($test);

        $this->tempDir = null;
        $this->testConfig = null;
    }

    public function _after(TestInterface $test): void
    {
        parent::_after($test);

        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function createTemporaryDirectory(): string
    {
        $this->tempDir = sys_get_temp_dir() . '/api-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        return $this->tempDir;
    }

    public function getVirtualFilesystemPath(): string
    {
        if ($this->tempDir === null) {
            $this->createTemporaryDirectory();
        }

        return $this->tempDir;
    }

    /**
     * @param array<string, mixed>|string $schemaData
     */
    public function createSchemaFile(string $apiType, string $resourceName, array|string $schemaData, string $moduleName = 'TestModule'): string
    {
        $apiTypeLower = strtolower($apiType);
        $config = $this->getTestConfig();
        $sourceDir = $config->getSourceDirectories()[0];
        $directory = sprintf('%s/%s/resources/api/%s', $sourceDir, $moduleName, $apiTypeLower);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $schemaFileName = sprintf('%s.resource.yaml', $resourceName);
        $filePath = sprintf('%s/%s', $directory, $schemaFileName);

        if (is_array($schemaData)) {
            $content = $this->convertArrayToYaml($schemaData, $resourceName);
        } else {
            $content = $schemaData;
        }

        file_put_contents($filePath, $content);

        return $filePath;
    }

    /**
     * @param array<string, mixed> $schemaData
     */
    protected function convertArrayToYaml(array $schemaData, string $resourceName): string
    {
        $yaml = sprintf("resource:\n    name: %s\n", $resourceName);
        $yaml .= sprintf("    shortName: %s\n", $resourceName);
        $yaml .= "    description: \"Test resource\"\n\n";
        $yaml .= "    operations:\n";
        $yaml .= "        - type: Get\n";
        $yaml .= "        - type: GetCollection\n\n";
        $yaml .= "    properties:\n";

        foreach ($schemaData['properties'] ?? [] as $propertyName => $propertyConfig) {
            $yaml .= sprintf("        %s:\n", $propertyName);
            $yaml .= sprintf("            type: %s\n", $propertyConfig['type'] ?? 'string');

            if (isset($propertyConfig['identifier'])) {
                $yaml .= sprintf("            identifier: %s\n", $propertyConfig['identifier'] ? 'true' : 'false');
            }

            if (isset($propertyConfig['format'])) {
                $yaml .= sprintf("            format: %s\n", $propertyConfig['format']);
            }

            if (in_array($propertyName, $schemaData['required'] ?? [])) {
                $yaml .= "            required: true\n";
            }
        }

        return $yaml;
    }

    /**
     * @param array<array{apiType: string, moduleName: string, fileName: string, content: string}> $schemas
     */
    public function createSchemaFiles(array $schemas): void
    {
        foreach ($schemas as $schema) {
            $this->createSchemaFile(
                $schema['apiType'],
                $schema['moduleName'],
                $schema['fileName'],
                $schema['content'],
            );
        }
    }

    /**
     * @param array<string, mixed> $structure
     */
    public function createDirectoryStructure(array $structure): void
    {
        $rootPath = $this->getVirtualFilesystemPath();

        $this->createDirectories($rootPath, $structure);
    }

    /**
     * @param array<string, mixed> $structure
     */
    protected function createDirectories(string $basePath, array $structure): void
    {
        foreach ($structure as $name => $content) {
            $path = sprintf('%s/%s', $basePath, $name);

            if (is_array($content)) {
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }

                $this->createDirectories($path, $content);

                continue;
            }

            file_put_contents($path, $content);
        }
    }

    protected function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $path = sprintf('%s/%s', $directory, $file);

            if (is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }

    public function getFixturePath(string $fileName): string
    {
        return codecept_data_dir($fileName);
    }

    public function loadFixture(string $fileName): string
    {
        $path = $this->getFixturePath($fileName);

        if (!file_exists($path)) {
            throw new RuntimeException(sprintf('Fixture file not found: %s', $path));
        }

        return file_get_contents($path);
    }

    public function createValidYamlSchemaContent(string $resourceName, string $apiType): string
    {
        return sprintf(
            <<<YAML
resource:
    name: %s
    shortName: %s
    description: "Test resource"

    operations:
        - type: Get
        - type: GetCollection

    properties:
        id:
            type: integer
            identifier: true
            writable: false
            required: true
        name:
            type: string
            required: true
YAML,
            $resourceName,
            $resourceName,
        );
    }

    /**
     * Assert that a generated class exists for the given API type and resource.
     *
     * @param string $apiType The API type in ucfirst format (e.g., 'backend')
     * @param string $resourceName The resource name (e.g., 'Customers')
     *
     * @return void
     */
    public function assertGeneratedClassExists(string $apiType, string $resourceName): void
    {
        $classPath = $this->getGeneratedClassPath($apiType, $resourceName);

        Assert::assertFileExists(
            $classPath,
            sprintf(
                'Generated class for %s/%s not found at %s',
                $apiType,
                $resourceName,
                $classPath,
            ),
        );
    }

    /**
     * Assert that a generated class uses the correct namespace.
     *
     * @param string $apiType The API type in ucfirst format
     * @param string $resourceName The resource name
     * @param string $expectedNamespace The expected namespace pattern
     *
     * @return void
     */
    public function assertGeneratedNamespace(
        string $apiType,
        string $resourceName,
        string $expectedNamespace,
    ): void {
        $content = $this->getGeneratedClassContent($apiType, $resourceName);

        Assert::assertStringContainsString(
            sprintf('namespace %s;', $expectedNamespace),
            $content,
            sprintf(
                'Generated class does not use expected namespace "%s"',
                $expectedNamespace,
            ),
        );
    }

    /**
     * Assert that generation created the expected directory structure.
     *
     * @param string $apiType The API type in ucfirst format
     * @param array<string> $expectedFiles List of expected file paths relative to API type directory
     *
     * @return void
     */
    public function assertGeneratedDirectoryStructure(
        string $apiType,
        array $expectedFiles,
    ): void {
        $baseDirectory = $this->getApiTypeDirectory($apiType);

        foreach ($expectedFiles as $expectedFile) {
            $filePath = sprintf('%s/%s', $baseDirectory, $expectedFile);

            Assert::assertFileExists(
                $filePath,
                sprintf('Expected file not found: %s', $filePath),
            );
        }
    }

    public function getGeneratedClassContent(string $apiType, string $resourceName): string
    {
        $classPath = $this->getGeneratedClassPath($apiType, $resourceName);

        if (!file_exists($classPath)) {
            throw new RuntimeException(
                sprintf('Generated class not found: %s', $classPath),
            );
        }

        return file_get_contents($classPath);
    }

    public function assertClassUsesCorrectCasing(string $filePath, string $expectedApiType): void
    {
        $content = file_get_contents($filePath);

        Assert::assertMatchesRegularExpression(
            sprintf('/namespace.*\\\\%s\\\\/', preg_quote($expectedApiType, '/')),
            $content,
            sprintf('Namespace does not use ucfirst API type "%s"', $expectedApiType),
        );

        Assert::assertMatchesRegularExpression(
            sprintf('/Generated\/Api\/%s\//', preg_quote($expectedApiType, '/')),
            $filePath,
            sprintf('File path does not use ucfirst API type "%s"', $expectedApiType),
        );
    }

    protected function getGeneratedClassPath(string $apiType, string $resourceName): string
    {
        $config = $this->getTestConfig();
        $apiResourceDir = $config->getApiResourceDirectory($apiType);

        return sprintf(
            '%s/%s%sResource.php',
            $apiResourceDir,
            $resourceName,
            ApiTypeNormalizer::normalizeForGeneration($apiType),
        );
    }

    protected function getApiTypeDirectory(string $apiType): string
    {
        return $this->getTestConfig()
            ->getApiResourceDirectory($apiType);
    }

    public function cleanupSchemaFiles(): void
    {
        $config = $this->getTestConfig();
        $sourceDir = $config->getSourceDirectories()[0];
        $modulePath = sprintf('%s/TestModule', $sourceDir);

        if (!is_dir($modulePath)) {
            return;
        }

        $this->removeDirectory($modulePath);
    }

    public function getTestConfig(): ApiPlatformConfig
    {
        if ($this->testConfig === null) {
            $this->testConfig = (new ApiPlatformConfigBuilder())->build();
        }

        return $this->testConfig;
    }

    public function overrideConfigInContainer(): void
    {
        $container = $this->getSymfonyContainer();

        if (!$container instanceof ContainerInterface) {
            throw new RuntimeException('Symfony container not available. Ensure SymfonyHelper is enabled.');
        }

        $testConfig = $this->getTestConfig();

        $container->set(ApiPlatformConfig::class, $testConfig);
    }

    protected function getSymfonyContainer(): ?ContainerInterface
    {
        $symfonyModule = $this->getModule('\\SprykerTest\\Shared\\Testify\\Helper\\SymfonyHelper');

        if (!method_exists($symfonyModule, 'getContainer')) {
            return null;
        }

        return $symfonyModule->getContainer();
    }
}
