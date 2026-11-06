<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator\Rules;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\McpToolSchemaResolver;
use Spryker\ApiPlatform\Schema\Validator\Rules\McpValidationRule;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validator
 * @group Rules
 * @group McpValidationRuleTest
 * Add your own group annotations below this line
 */
class McpValidationRuleTest extends Unit
{
    protected const string SOURCE_FILE = 'health-check.resource.yml';

    protected ApiUnitTester $tester;

    /**
     * @dataProvider provideMcpDeclarations
     *
     * @param array<string, array<string, mixed>> $operations
     * @param array<string> $expectedMessageFragments
     */
    public function testGivenMcpDeclarationWhenValidatingThenReturnsTheExpectedErrors(
        array $operations,
        int $expectedErrorCount,
        array $expectedMessageFragments
    ): void {
        // Arrange
        $schema = ['sourceFile' => static::SOURCE_FILE, 'shortName' => 'health-check', 'operations' => $operations];

        // Act
        $errors = (new McpValidationRule(new McpToolSchemaResolver()))->validate($schema);

        // Assert
        $this->assertCount($expectedErrorCount, $errors);

        foreach ($expectedMessageFragments as $expectedMessageFragment) {
            $this->assertStringContainsString($expectedMessageFragment, implode("\n", $errors));
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function provideMcpDeclarations(): array
    {
        return [
            'no mcp key' => [['GetCollection' => ['type' => 'GetCollection']], 0, []],
            'short form' => [['GetCollection' => ['type' => 'GetCollection', 'mcp' => true]], 0, []],
            'disabled' => [['Post' => ['type' => 'Post', 'mcp' => false]], 0, []],
            'valid tool' => [
                [
                    'GetCollection' => [
                        'type' => 'GetCollection',
                        'mcp' => [
                            'name' => 'health-check',
                            'title' => 'Health check',
                            'description' => 'Reports the health.',
                            'structuredContent' => true,
                            'annotations' => ['readOnlyHint' => true, 'title' => 'Health'],
                        ],
                    ],
                ],
                0,
                [],
            ],
            'write operation' => [['Post' => ['type' => 'Post', 'mcp' => true]], 1, ['Operation "Post" cannot be an MCP tool', 'Get, GetCollection']],
            'list instead of map' => [['Get' => ['type' => 'Get', 'mcp' => ['health-check']]], 1, ['mcp must be true, false or a map']],
            'invalid tool name' => [['Get' => ['type' => 'Get', 'mcp' => ['name' => 'health check!']]], 1, ['MCP tool name "health check!"']],
            'invalid values' => [
                [
                    'Get' => [
                        'type' => 'Get',
                        'mcp' => [
                            'title' => 42,
                            'structuredContent' => 'no',
                            'annotations' => 'readOnly',
                        ],
                    ],
                ],
                3,
                ['title must be a string', 'structuredContent must be a boolean', 'annotations must be a map'],
            ],
            'unknown key' => [['Get' => ['type' => 'Get', 'mcp' => ['security' => "is_granted('ROLE_USER')"]]], 1, ['unknown key "security"']],
            'invalid annotations' => [
                [
                    'Get' => [
                        'type' => 'Get',
                        'mcp' => ['annotations' => ['readOnlyHint' => 'yes', 'title' => 42, 'cacheHint' => true]],
                    ],
                ],
                3,
                ['annotation readOnlyHint must be a boolean', 'annotation title must be a string', 'unknown annotation "cacheHint"'],
            ],
            'same tool name on two operations' => [
                [
                    'Get' => ['type' => 'Get', 'mcp' => ['name' => 'health-check']],
                    'GetCollection' => ['type' => 'GetCollection', 'mcp' => ['name' => 'health-check']],
                ],
                1,
                ['MCP tool name "health-check" is declared by more than one operation (Get, GetCollection)'],
            ],
        ];
    }
}
