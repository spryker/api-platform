# Quick Start: IDE Integration

Get started with IDE autocomplete and validation for Spryker API resource files in under 5 minutes.

## For YAML Files

### 1. Add Schema Reference

Add this line at the top of your YAML file:

```yaml
# yaml-language-server: $schema=../../../schemas/api-resource-schema-v1.json
```

Adjust the path based on your file location relative to the schema.

### 2. Configure Your IDE

**PHPStorm:**
- Go to **Settings > Languages & Frameworks > Schemas and DTDs > JSON Schema Mappings**
- Add new mapping pointing to `src/Spryker/ApiPlatform/resources/schemas/api-resource-schema-v1.json`
- Add the file pattern: `**/resources/api/*/**.yaml`

**VSCode:**
- Install "YAML" extension by Red Hat
- Add to `.vscode/settings.json`:
```json
{
  "yaml.schemas": {
    "./src/SprykerSdk/Api/resources/schemas/api-resource-schema-v1.json": [
      "**/resources/api/*/**.yaml"
    ]
  }
}
```

### 3. Start Coding

Open any YAML file and start typing:

```yaml
resource:
  shortName: # <- Autocomplete will suggest property types
  operations:
    - type: # <- Shows: Get, GetCollection, Post, Put, Patch, Delete
```

## Example Files

See complete example:
- `src/SprykerSdk/Api/resources/examples/customer.yaml`

## Common Properties

### Resource Level
- `shortName` (required) - Resource identifier
- `operations` (required) - Array of operations
- `properties` (required) - Resource properties
- `provider` - Provider class name
- `processor` - Processor class name
- `paginationEnabled` - Enable pagination
- `paginationItemsPerPage` - Items per page

### Operation Types
- `Get` - Single resource retrieval
- `GetCollection` - Collection retrieval
- `Post` - Create resource
- `Put` - Full replacement
- `Patch` - Partial update
- `Delete` - Delete resource

### Property Attributes
- `type` (required) - Data type: string, integer, boolean, array, object, mixed
- `identifier` - Mark as resource ID (at least one required)
- `required` - Required when creating
- `writable` - Can be updated (default: true)
- `readable` - Can be read (default: true)
- `openapiContext` - OpenAPI attributes

## Need Help?

See the full [IDE Integration Guide](README.md) for detailed setup and troubleshooting.
