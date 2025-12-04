# IDE Integration Guide

This guide explains how to set up IDE support for Spryker API resource schema files, enabling autocomplete, validation, and inline documentation for YAML format.

## Table of Contents

- [Overview](#overview)
- [PHPStorm Setup](#phpstorm-setup)
  - [YAML Schema Configuration](#yaml-schema-configuration-phpstorm)
- [VSCode Setup](#vscode-setup)
  - [YAML Extension Setup](#yaml-extension-setup-vscode)
- [Schema Reference in Files](#schema-reference-in-files)
- [Troubleshooting](#troubleshooting)

## Overview

Spryker provides a JSON Schema file for API resource definitions:

- **JSON Schema** (`api-resource-schema-v1.json`) - For YAML file autocomplete and validation

This schema enables:
- **Autocomplete** - Property and value suggestions as you type
- **Validation** - Real-time error detection for invalid configurations
- **Documentation** - Inline help for all properties and attributes
- **Type Safety** - Ensures values match expected types

## PHPStorm Setup

### YAML Schema Configuration (PHPStorm)

PHPStorm supports JSON Schema for YAML files through built-in configuration.

#### Step 1: Open JSON Schema Configuration

1. Go to **File > Settings** (Windows/Linux) or **PHPStorm > Preferences** (macOS)
2. Navigate to **Languages & Frameworks > Schemas and DTDs > JSON Schema Mappings**
3. Click the **+** button to add a new mapping

#### Step 2: Add Schema Mapping

Configure the following:

- **Name:** `Spryker API Resource Schema`
- **Schema file or URL:**
  - **Local:** `file://$PROJECT_DIR$/src/SprykerSdk/Api/resources/schemas/api-resource-schema-v1.json`
  - **Hosted:** `https://static.spryker.com/api-resource-schema-v1.json` (when available)
- **Schema version:** `JSON Schema version 7`

#### Step 3: Add File Pattern

In the schema mapping dialog:

1. Click **+** under "File path pattern"
2. Add pattern: `**/resources/api/*/**.yaml`
3. Or add specific directories where API resource YAML files are located

#### Step 4: Verify Configuration

1. Open any API resource YAML file
2. Start typing `resource:` - you should see autocomplete suggestions
3. Type `Ctrl+Q` (Windows/Linux) or `F1` (macOS) on a property to see documentation

## VSCode Setup

### YAML Extension Setup (VSCode)

VSCode requires the Red Hat YAML extension for JSON Schema support.

#### Step 1: Install YAML Extension

1. Open VSCode
2. Go to Extensions (`Ctrl+Shift+X` or `Cmd+Shift+X`)
3. Search for **"YAML"** by Red Hat
4. Click **Install**

#### Step 2: Configure Schema Association

You have two options:

**Option A: Workspace Settings (Recommended)**

1. Create or open `.vscode/settings.json` in your project root
2. Add the following configuration:

```json
{
  "yaml.schemas": {
    "./src/SprykerSdk/Api/resources/schemas/api-resource-schema-v1.json": [
      "**/resources/api/*/**.yaml",
      "**/api-resources/*.yaml"
    ]
  }
}
```

**Option B: User Settings (Global)**

1. Go to **File > Preferences > Settings** (Windows/Linux) or **Code > Preferences > Settings** (macOS)
2. Search for "yaml.schemas"
3. Click **Edit in settings.json**
4. Add schema mapping as shown above

#### Step 3: Verify Configuration

1. Open any API resource YAML file
2. Start typing - you should see autocomplete suggestions
3. Hover over properties to see documentation
4. Invalid values will be underlined with error messages

## Schema Reference in Files

Add a schema reference at the top of your YAML file using a comment directive:

```yaml
# yaml-language-server: $schema=../../../schemas/api-resource-schema-v1.json

resource:
  name: Customer
  shortName: Customer
  description: "Customer resource for storefront API"

  operations:
    - type: Get
    - type: GetCollection

  properties:
    idCustomer:
      type: integer
      description: "The unique identifier"
      identifier: true
      writable: false
```

**Path Options:**

- **Relative Path:** `../../../schemas/api-resource-schema-v1.json` (recommended)
- **Absolute Path:** `file:///full/path/to/schema.json`
- **Hosted URL:** `https://static.spryker.com/api-resource-schema-v1.json` (when available)

## Troubleshooting

### Schema Not Recognized

**Problem:** IDE doesn't show autocomplete or validation

**Solutions:**

1. **Verify schema reference path**
   - Ensure the path in your YAML file is correct
   - Use relative paths from the file location
   - Check file exists at the specified path

2. **Restart IDE**
   - PHPStorm: **File > Invalidate Caches / Restart**
   - VSCode: **Developer: Reload Window** (`Ctrl+Shift+P` / `Cmd+Shift+P`)

3. **Check extension installation**
   - VSCode: Ensure YAML extension is installed and enabled
   - PHPStorm: Ensure JSON Schema support plugin is enabled

4. **Verify file association**
   - PHPStorm: Check **Languages & Frameworks > Schemas and DTDs**
   - VSCode: Check `.vscode/settings.json` for correct patterns

### Autocomplete Not Working

**Problem:** Typing doesn't trigger suggestions

**Solutions:**

1. **Manually trigger autocomplete**
   - PHPStorm: `Ctrl+Space` (Windows/Linux) or `Cmd+Space` (macOS)
   - VSCode: `Ctrl+Space` (Windows/Linux) or `Cmd+Space` (macOS)

2. **Check schema syntax**
   - Open schema file and verify it's valid JSON
   - Look for syntax errors in the IDE

3. **Verify file extension**
   - YAML files must end with `.yaml` or `.yml`

4. **Check IDE settings**
   - PHPStorm: **Editor > General > Code Completion** - ensure enabled
   - VSCode: Check extension settings for YAML

### Validation Errors

**Problem:** False positive validation errors

**Solutions:**

1. **Check schema version compatibility**
   - Ensure using correct schema version for your API resources
   - Update schema reference if using older version

2. **Verify property names**
   - Property names must match pattern: `^[a-zA-Z_][a-zA-Z0-9_]*$`
   - No special characters except underscore

3. **Check data types**
   - Use allowed types: `string`, `integer`, `boolean`, `array`, `object`, `mixed`
   - Type aliases: `int`, `bool`, `str`, `arr` (normalized automatically)

4. **Validate boolean attributes**
   - Use `true`/`false` (lowercase) in YAML

### Cache Issues

**Problem:** Changes to schema not reflected in IDE

**Solutions:**

1. **Clear IDE cache**
   - PHPStorm: **File > Invalidate Caches / Restart > Invalidate and Restart**
   - VSCode: Delete workspace cache: `.vscode/.cache` (if exists)

2. **Reload configuration**
   - PHPStorm: **File > Reload All from Disk**
   - VSCode: **Developer: Reload Window**

3. **Check file timestamps**
   - Ensure schema file was actually saved
   - Check file modification time

### Schema URL Not Accessible

**Problem:** Cannot load schema from hosted URL

**Solutions:**

1. **Use local path**
   - Switch from hosted URL to local file path
   - Update schema reference in YAML files

2. **Check network connection**
   - Verify internet connectivity
   - Check firewall/proxy settings

3. **Download schema locally**
   - Download schema file to project
   - Update references to use local path

4. **Configure offline mode**
   - PHPStorm: Enable offline mode in settings
   - VSCode: Download schema locally for offline support

## Additional Resources

- [JSON Schema Documentation](https://json-schema.org/)
- [PHPStorm JSON Schema Guide](https://www.jetbrains.com/help/phpstorm/json.html)
- [VSCode YAML Extension](https://marketplace.visualstudio.com/items?itemName=redhat.vscode-yaml)

## Support

For issues or questions:
- Check Spryker documentation
- Open issue in project repository
- Contact Spryker support team
