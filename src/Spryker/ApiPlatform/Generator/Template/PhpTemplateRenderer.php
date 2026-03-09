<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Template;

/**
 * Renders complete PHP resource class files from template data.
 *
 * Generates fully-formed PHP files including file header, namespace, use statements,
 * class declaration with attributes, properties, getters/setters, and serialization methods.
 *
 * Input template data structure:
 * ```
 * [
 *     'className' => 'CustomersStorefrontResource',
 *     'namespace' => 'Generated\Api\Storefront',
 *     'uses' => ['ApiPlatform\Metadata\ApiResource', ...],
 *     'resourceAttribute' => '#[ApiResource(operations: [...])]',
 *     'properties' => [
 *         ['name' => 'email', 'phpType' => 'string', 'attributes' => '#[ApiProperty(...)]'],
 *     ],
 *     'codeBucket' => null,
 *     'metadata' => ['timestamp' => '2026-01-21 10:00:00', 'sourceFiles' => [...]],
 * ]
 * ```
 *
 * Generated output structure contains:
 * - File header with copyright, generation timestamp, and source file references
 * - Namespace declaration (Generated\Api\{ApiType})
 * - Use statements for API Platform, Symfony validation, and custom classes
 * - Class declaration with ApiResource attribute
 * - Public properties with ApiProperty and validation attributes
 * - Getters and setters for all properties
 * - toArray() method for serialization
 * - fromArray() static factory method for deserialization
 *
 * Special handling:
 * - Array properties get empty array default instead of null
 * - CodeBucket constant generated when codeBucket is present
 */
class PhpTemplateRenderer
{
    /**
     * @param array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, properties: array<array{name: string, type: string, phpType: string, attributes: string, description: string, phpDoc: string}>, codeBucket: ?string, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}|array $templateData
     *
     * @return string
     */
    public function render(array $templateData): string
    {
        $output = $this->renderFileHeader($templateData['metadata']);
        $output .= $this->renderNamespace($templateData['namespace']);
        $output .= $this->renderUseStatements($templateData['uses']);
        $output .= $this->renderClassDeclaration($templateData['className'], $templateData['resourceAttribute']);
        $output .= $this->renderCodeBucketConstant($templateData['codeBucket'] ?? null);

        $properties = $this->renderProperties($templateData['properties']);

        if ($properties !== '') {
            $output .= "\n" . $properties;
        }

        $output .= $this->renderGettersAndSetters($templateData['properties']);
        $output .= $this->renderToArray($templateData['properties']);
        $output .= $this->renderFromArray($templateData['className'], $templateData['properties']);
        $output .= "\n}\n";

        return $output;
    }

    /**
     * @param array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}|array $metadata
     */
    protected function renderFileHeader(array $metadata): string
    {
        $sourceFiles = implode("\n * - ", $metadata['sourceFiles']);

        $validationSection = '';

        if ($metadata['validationSourceFiles'] !== []) {
            $validationFiles = implode("\n * - ", $metadata['validationSourceFiles']);
            $validationSection = <<<TEXT

 *
 * Validation schema files:
 * - {$validationFiles}
TEXT;
        }

        return <<<PHP
<?php

/**
 * @copyright (c) Spryker Systems GmbH copyright protected
 *
 * @generated {$metadata['timestamp']}
 *
 * Source schema files:
 * - {$sourceFiles}{$validationSection}
 *
 * Documentation: https://api-platform.com/docs/core/
 *
 * !!! THIS FILE IS AUTO-GENERATED, EVERY CHANGE WILL BE LOST WITH THE NEXT RUN OF THE RESOURCE GENERATOR (glue api:generate)
 * !!! DO NOT CHANGE ANYTHING IN THIS FILE; Edit the respective source schema files mentioned above instead.
 */

declare(strict_types=1);
PHP;
    }

    protected function renderNamespace(string $namespace): string
    {
        return "\nnamespace {$namespace};";
    }

    /**
     * @param array<string> $uses
     */
    protected function renderUseStatements(array $uses): string
    {
        if ($uses === []) {
            return '';
        }

        $statements = array_map(
            static fn (string $use): string => "use {$use};",
            $uses,
        );

        return "\n" . implode("\n", $statements);
    }

    protected function renderClassDeclaration(string $className, string $resourceAttribute): string
    {
        return <<<PHP

{$resourceAttribute}
final class {$className}
{
PHP;
    }

    protected function renderCodeBucketConstant(?string $codeBucket): string
    {
        if ($codeBucket === null) {
            return '';
        }

        return "\n    public const string CODE_BUCKET = '{$codeBucket}';";
    }

    /**
     * @param array<array{name: string, type: string, phpType: string, attributes: string, description: string, phpDoc: string}> $properties
     */
    protected function renderProperties(array $properties): string
    {
        if ($properties === []) {
            return '';
        }

        $rendered = [];

        foreach ($properties as $property) {
            $propertyLines = [];

            if ($property['phpDoc'] !== '') {
                $propertyLines[] = '    /**';
                $propertyLines[] = "     * {$property['phpDoc']}";
                $propertyLines[] = '     */';
            }

            if ($property['attributes'] !== '') {
                $propertyLines[] = "    {$property['attributes']}";
            }

            // required arrays need to have an empty array as default, so the validation is triggered for it when not send via the API
            if ($property['phpType'] === 'array') {
                $propertyLines[] = "    public {$property['phpType']} \${$property['name']} = [];";
                $rendered[] = implode("\n", $propertyLines);

                continue;
            }

            $defaultValue = $this->formatPropertyDefault($property);
            $propertyLines[] = "    public ?{$property['phpType']} \${$property['name']} = {$defaultValue};";
            $rendered[] = implode("\n", $propertyLines);
        }

        return implode("\n\n", $rendered);
    }

    /**
     * @param array<string, mixed> $property
     */
    protected function formatPropertyDefault(array $property): string
    {
        if (!isset($property['hasDefault']) || $property['hasDefault'] !== true) {
            return 'null';
        }

        $default = $property['default'];

        if ($default === null) {
            return 'null';
        }

        if (is_bool($default)) {
            return $default ? 'true' : 'false';
        }

        if (is_int($default) || is_float($default)) {
            return (string)$default;
        }

        if (is_string($default)) {
            return sprintf("'%s'", addslashes($default));
        }

        return 'null';
    }

    /**
     * @param array<array{name: string, type: string, phpType: string, attributes: string, description: string, phpDoc: string}> $properties
     */
    protected function renderGettersAndSetters(array $properties): string
    {
        if ($properties === []) {
            return '';
        }

        $rendered = [];

        foreach ($properties as $property) {
            $setterName = 'set' . ucfirst($property['name']);
            $getterName = 'get' . ucfirst($property['name']);

            $rendered[] = <<<PHP
    public function {$setterName}(?{$property['phpType']} \${$property['name']}): self
    {
        \$this->{$property['name']} = \${$property['name']};

        return \$this;
    }

    public function {$getterName}(): ?{$property['phpType']}
    {
        return \$this->{$property['name']};
    }
PHP;
        }

        return "\n\n" . implode("\n\n", $rendered);
    }

    /**
     * @param array<array{name: string, type: string, phpType: string, attributes: string, description: string, phpDoc: string}> $properties
     */
    protected function renderToArray(array $properties): string
    {
        if ($properties === []) {
            return "\n\n    /**\n     * @return array<string, mixed>\n     */\n    public function toArray(): array\n    {\n        return [];\n    }";
        }

        $assignments = [];

        foreach ($properties as $property) {
            $assignments[] = "            '{$property['name']}' => \$this->{$property['name']},";
        }

        $assignmentsStr = implode("\n", $assignments);

        return <<<PHP

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
{$assignmentsStr}
        ];
    }
PHP;
    }

    /**
     * @param array<array{name: string, type: string, phpType: string, attributes: string, description: string, phpDoc: string}> $properties
     */
    protected function renderFromArray(string $className, array $properties): string
    {
        if ($properties === []) {
            return "\n\n    /**\n     * @param array<string, mixed> \$data\n     */\n    public static function fromArray(array \$data): self\n    {\n        return new self();\n    }";
        }

        $assignments = [];

        foreach ($properties as $property) {
            if ($property['phpType'] === 'array') {
                $assignments[] = "        \$instance->{$property['name']} = \$data['{$property['name']}'] ?? [];";

                continue;
            }

            $defaultValue = $this->formatPropertyDefault($property);
            $assignments[] = "        \$instance->{$property['name']} = \$data['{$property['name']}'] ?? {$defaultValue};";
        }

        $assignmentsStr = implode("\n", $assignments);

        return <<<PHP

    /**
     * @param array<string, mixed> \$data
     */
    public static function fromArray(array \$data): self
    {
        \$instance = new self();
{$assignmentsStr}

        return \$instance;
    }
PHP;
    }
}
