<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator\Template;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group Template
 * @group PhpTemplateRendererTest
 * Add your own group annotations below this line
 */
class PhpTemplateRendererTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenTemplateDataWhenRenderingThenReturnsValidPhp(): void
    {
        // Arrange
        $templateData = $this->createMinimalTemplateData();
        $renderer = $this->tester->getContainer()->get(PhpTemplateRenderer::class);

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('<?php', $result);
    }

    public function testGivenClassNameWhenRenderingThenIncludesClassName(): void
    {
        // Arrange
        $templateData = $this->createMinimalTemplateData();
        $renderer = $this->tester->getContainer()->get(PhpTemplateRenderer::class);

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('class CustomerResource', $result);
    }

    public function testGivenNamespaceWhenRenderingThenIncludesNamespace(): void
    {
        // Arrange
        $templateData = $this->createMinimalTemplateData();
        $renderer = $this->tester->getContainer()->get(PhpTemplateRenderer::class);

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('namespace Generated\Api;', $result);
    }

    public function testGivenPropertiesWhenRenderingThenIncludesProperties(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithProperties();
        $renderer = $this->tester->getContainer()->get(PhpTemplateRenderer::class);

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('public ?int $id = null;', $result);
    }

    public function testGivenPropertiesWhenRenderingThenIncludesGetters(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithProperties();
        $renderer = $this->tester->getContainer()->get(PhpTemplateRenderer::class);

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('public function getId()', $result);
    }

    public function testGivenPropertiesWhenRenderingThenIncludesSetters(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithProperties();
        $renderer = $this->tester->getContainer()->get(PhpTemplateRenderer::class);

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('public function setId(?int $id)', $result);
    }

    public function testGivenResourceAttributeWhenRenderingThenIncludesAttribute(): void
    {
        // Arrange
        $templateData = $this->createMinimalTemplateData();
        $renderer = $this->tester->getContainer()->get(PhpTemplateRenderer::class);

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('#[ApiResource]', $result);
    }

    public function testGivenMultiplePropertiesWhenRenderingThenGetterAndSetterAreGroupedPerProperty(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithMultipleProperties();
        $renderer = $this->tester->getContainer()->get(PhpTemplateRenderer::class);

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $getIdPosition = strpos($result, 'public function getId()');
        $setIdPosition = strpos($result, 'public function setId(');
        $getNamePosition = strpos($result, 'public function getName()');
        $setNamePosition = strpos($result, 'public function setName(');

        $this->assertNotFalse($getIdPosition);
        $this->assertNotFalse($setIdPosition);
        $this->assertNotFalse($getNamePosition);
        $this->assertNotFalse($setNamePosition);
        $this->assertLessThan($getIdPosition, $setIdPosition, 'setId should come before getId');
        $this->assertLessThan($setNamePosition, $getIdPosition, 'getId should come before setName');
        $this->assertLessThan($getNamePosition, $setNamePosition, 'setName should come before getName');
    }

    public function testGivenCollectionPropertyWhenRenderingThenMapsElementsInBothDirections(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithCollectionProperty(nullable: false);

        // Act
        $code = (new PhpTemplateRenderer())->render($templateData);

        // Assert — the full toArray()/fromArray() assignment blocks are pinned (not just the inner
        // arrow functions), so a dropped array_map() wrapper or guard would fail this test; a raw
        // array assigned by a provider that bypasses the serializer still passes through untouched.
        $this->assertStringContainsString(
            <<<'PHP'
            'prices' => array_map(
                static fn (mixed $item): mixed => $item instanceof \Generated\Api\Backend\Products\ProductsPricesBackendObject ? $item->toArray() : $item,
                $this->prices,
            ),
PHP,
            $code,
        );
        $this->assertStringContainsString(
            <<<'PHP'
        $instance->prices = is_array($data['prices'] ?? null)
            ? array_map(
                static fn (mixed $item): mixed => is_array($item) ? \Generated\Api\Backend\Products\ProductsPricesBackendObject::fromArray($item) : $item,
                $data['prices'],
            )
            : [];
PHP,
            $code,
        );
        $this->assertGeneratesValidPhp($code);
    }

    public function testGivenNullableCollectionPropertyWhenRenderingThenNullGuardWrapsElementMapping(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithCollectionProperty(nullable: true);

        // Act
        $code = (new PhpTemplateRenderer())->render($templateData);

        // Assert — the nullable form short-circuits to null instead of calling array_map(null, ...),
        // which would otherwise be a TypeError; fromArray()'s is_array() guard is nullable-agnostic
        // and only the trailing default changes.
        $this->assertStringContainsString(
            <<<'PHP'
            'prices' => $this->prices === null ? null : array_map(
                static fn (mixed $item): mixed => $item instanceof \Generated\Api\Backend\Products\ProductsPricesBackendObject ? $item->toArray() : $item,
                $this->prices,
            ),
PHP,
            $code,
        );
        $this->assertStringContainsString(
            <<<'PHP'
        $instance->prices = is_array($data['prices'] ?? null)
            ? array_map(
                static fn (mixed $item): mixed => is_array($item) ? \Generated\Api\Backend\Products\ProductsPricesBackendObject::fromArray($item) : $item,
                $data['prices'],
            )
            : null;
PHP,
            $code,
        );
        $this->assertGeneratesValidPhp($code);
    }

    /**
     * @return array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, codeBucket: string|null, properties: array<mixed>, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}
     */
    protected function createTemplateDataWithCollectionProperty(bool $nullable): array
    {
        return [
            'className' => 'ProductsBackendResource',
            'namespace' => 'Generated\Api\Backend',
            'uses' => [],
            'resourceAttribute' => '',
            'codeBucket' => null,
            'properties' => [
                [
                    'name' => 'prices',
                    'type' => 'array',
                    'phpType' => 'array',
                    'itemClass' => '\Generated\Api\Backend\Products\ProductsPricesBackendObject',
                    'attributes' => '',
                    'description' => '',
                    'phpDoc' => '',
                    'default' => null,
                    'hasDefault' => false,
                    'serializedName' => null,
                    'serializedPath' => null,
                    'nullable' => $nullable,
                ],
            ],
            'metadata' => ['timestamp' => '2026-01-01 00:00:00', 'sourceFiles' => [], 'validationSourceFiles' => []],
        ];
    }

    /**
     * Lints generated source with the PHP CLI itself — the failure mode this renderer is most
     * exposed to is a broken string template that still happens to contain the substrings under
     * test, which only a real parse can catch.
     */
    protected function assertGeneratesValidPhp(string $code): void
    {
        $path = sys_get_temp_dir() . '/api-platform-template-renderer-' . uniqid() . '.php';
        file_put_contents($path, $code);

        exec(sprintf('php -l %s 2>&1', escapeshellarg($path)), $output, $exitCode);
        unlink($path);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    /**
     * @return array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, properties: array<mixed>, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}
     */
    protected function createMinimalTemplateData(): array
    {
        return [
            'className' => 'CustomerResource',
            'namespace' => 'Generated\Api',
            'uses' => [],
            'resourceAttribute' => '#[ApiResource]',
            'properties' => [],
            'metadata' => [
                'timestamp' => '2024-01-01',
                'sourceFiles' => ['test.yaml'],
                'validationSourceFiles' => [],
            ],
        ];
    }

    /**
     * @return array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, properties: array<array{name: string, type: string, phpType: string, attributes: string, description: string}>, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}
     */
    protected function createTemplateDataWithProperties(): array
    {
        return [
            'className' => 'CustomerResource',
            'namespace' => 'Generated\Api',
            'uses' => [],
            'resourceAttribute' => '#[ApiResource]',
            'properties' => [
                [
                    'name' => 'id',
                    'type' => 'integer',
                    'phpType' => 'int',
                    'attributes' => '',
                    'description' => 'ID',
                    'phpDoc' => '',
                ],
            ],
            'metadata' => [
                'timestamp' => '2024-01-01',
                'sourceFiles' => ['test.yaml'],
                'validationSourceFiles' => [],
            ],
        ];
    }

    /**
     * @return array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, properties: array<array{name: string, type: string, phpType: string, attributes: string, description: string}>, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}
     */
    protected function createTemplateDataWithMultipleProperties(): array
    {
        return [
            'className' => 'CustomerResource',
            'namespace' => 'Generated\Api',
            'uses' => [],
            'resourceAttribute' => '#[ApiResource]',
            'properties' => [
                [
                    'name' => 'id',
                    'type' => 'integer',
                    'phpType' => 'int',
                    'attributes' => '',
                    'description' => 'ID',
                    'phpDoc' => '',
                ],
                [
                    'name' => 'name',
                    'type' => 'string',
                    'phpType' => 'string',
                    'attributes' => '',
                    'description' => 'Name',
                    'phpDoc' => '',
                ],
            ],
            'metadata' => [
                'timestamp' => '2024-01-01',
                'sourceFiles' => ['test.yaml'],
                'validationSourceFiles' => [],
            ],
        ];
    }
}
