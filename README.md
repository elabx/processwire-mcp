# ProcessWire MCP Server

An extensible MCP (Model Context Protocol) server for ProcessWire CMS that enables AI assistants like Claude to interact with your ProcessWire installation.

## Features

- **Full ProcessWire API access** - Query, create, update, and delete pages
- **Template & field management** - Inspect and modify your site's data structure
- **User & role management** - Create users, manage roles and permissions
- **Module management** - List, install, and configure modules
- **File management** - Access page files and images
- **Extensible architecture** - Third-party modules can register custom tools
- **Security-focused** - Admin pages blocked, configurable restrictions

## Requirements

- PHP 8.1+
- ProcessWire 3.0.200+
- Composer

## Installation

```bash
# Using Composer (recommended)
composer require elabx/processwire-mcp

# With DDEV
ddev composer require elabx/processwire-mcp
```

## Usage

### Running the Server

The server auto-detects your ProcessWire root when installed via Composer — no path flag needed:

```bash
# Auto-detects PW root (recommended)
vendor/bin/pw-mcp-server

# With DDEV
ddev exec php vendor/bin/pw-mcp-server

# Explicit path (if auto-detect doesn't work)
php vendor/bin/pw-mcp-server --pw-path=/path/to/processwire

# Using environment variable
PW_PATH=/path/to/processwire php vendor/bin/pw-mcp-server
```

### Claude Code Configuration

Add to your project's `.claude/settings.json` or `~/.claude/settings.json`:

```json
{
  "mcpServers": {
    "processwire": {
      "command": "ddev",
      "args": ["exec", "php", "vendor/bin/pw-mcp-server"]
    }
  }
}
```

For non-DDEV setups:

```json
{
  "mcpServers": {
    "processwire": {
      "command": "php",
      "args": ["vendor/bin/pw-mcp-server"]
    }
  }
}
```

## Available Tools

### Page Tools

| Tool | Description |
|------|-------------|
| `find_pages` | Query pages with ProcessWire selectors |
| `get_page` | Get single page by ID or path |
| `create_page` | Create new page |
| `update_page` | Update page field values |
| `delete_page` | Trash or permanently delete page |
| `get_children` | Get child pages of a parent |
| `clone_page` | Clone a page, optionally with children |
| `sort_pages` | Sort a page relative to a sibling |
| `restore_page` | Restore a page from trash |

### Template Tools

| Tool | Description |
|------|-------------|
| `find_templates` | Find templates using ProcessWire selector syntax |
| `list_templates` | List all available templates |
| `get_template_fields` | Get template fields and configuration |
| `get_template_file` | Get template file path |
| `create_template` | Create a new template |
| `update_template` | Update template settings (label, restrictions, cache, etc.) |
| `delete_template` | Delete a template (must have no pages) |
| `clone_template` | Clone a template with all fields and settings |
| `add_field_to_template` | Add a field to a template with positioning |
| `remove_field_from_template` | Remove a field from a template |

### Field Tools

| Tool | Description |
|------|-------------|
| `find_fields` | Find fields using ProcessWire selector syntax |
| `list_fields` | List all fields, optionally filter by type |
| `get_field` | Get field details and configuration |
| `list_field_types` | List available field types |
| `create_field` | Create a new field with type and settings |
| `update_field` | Update field properties |
| `delete_field` | Delete a field (must not be in use) |
| `clone_field` | Clone a field with all settings |

### User Tools

| Tool | Description |
|------|-------------|
| `list_users` | List users (optionally by role) |
| `get_user` | Get user details |
| `create_user` | Create a user with email, password, and roles |
| `update_user` | Update user email, password, or roles |
| `delete_user` | Delete a user |
| `list_roles` | List all roles |

### Role Tools

| Tool | Description |
|------|-------------|
| `create_role` | Create a new role with optional permissions |
| `update_role` | Add or remove permissions from a role |
| `delete_role` | Delete a role (must not be assigned to users) |
| `list_permissions` | List all available permissions |
| `create_permission` | Create a new permission |
| `set_template_access` | Set role-based access control on a template |

### Module Tools

| Tool | Description |
|------|-------------|
| `list_modules` | List all modules (optionally installed only) |
| `get_module_info` | Get module details and requirements |
| `install_module` | Install a module (requires confirmation) |
| `uninstall_module` | Uninstall a module (requires confirmation) |
| `get_module_config` | Get module configuration (sensitive values redacted) |
| `save_module_config` | Save module configuration values |

### File Tools

| Tool | Description |
|------|-------------|
| `get_page_files` | Get files/images attached to a page |
| `get_image_variations` | Get image size variations |
| `get_page_files_path` | Get page files directory path |

## Available Resources

Resources provide read-only access to ProcessWire data:

| URI | Description |
|-----|-------------|
| `templates://list` | All templates with their fields |
| `fields://list` | All field definitions |
| `users://list` | All users (basic info only) |
| `roles://list` | All roles with permissions |

## Extending with Custom Tools

Third-party ProcessWire modules can register custom MCP tools by extending `ProcessWireMcpTool`:

### 1. Create Your Tool Class

```php
<?php

namespace YourVendor\YourModule;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;

class MyCustomTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'my_custom_tools',
            'description' => 'Custom tools for my module',
            'priority' => 100, // Lower = loaded first
        ];
    }

    #[McpTool(
        name: 'my_custom_operation',
        description: 'Does something custom with pages'
    )]
    public function myCustomOperation(string $selector): array
    {
        // Use ProcessWire APIs via inherited methods
        $count = $this->pages()->count($selector);

        return $this->success([
            'count' => $count,
        ]);
    }
}
```

### 2. Register via Hook

In your ProcessWire module:

```php
<?php

namespace ProcessWire;

class MyModule extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'My Module',
            'requires' => ['ProcessWireMcp'],
            // ...
        ];
    }

    public function ready(): void
    {
        $this->addHookAfter('ProcessWireMcp::getToolClasses', function($event) {
            $classes = $event->return;
            $classes[] = \YourVendor\YourModule\MyCustomTools::class;
            $event->return = $classes;
        });
    }
}
```

### Base Class Methods

`ProcessWireMcpTool` provides these helper methods:

```php
// ProcessWire API access
$this->pages()      // Pages API
$this->templates()  // Templates API
$this->fields()     // Fields API
$this->users()      // Users API
$this->sanitizer()  // Sanitizer API
$this->get($name)   // Any wire service

// Security helpers
$this->isAdminPage($page)          // Check if page is admin
$this->assertNotAdminPage($page)   // Throw if admin page
$this->excludeAdminFromSelector($selector)  // Add admin exclusion

// Response helpers
$this->success($data, $message)    // Success response
$this->error($message, $code)      // Error response
$this->pageToArray($page, $fields) // Convert page to array
```

## Security

The MCP server includes several security measures:

1. **Admin page protection** - Pages under `/processwire/` (ID 2) are blocked
2. **No password exposure** - User tools never return password data
3. **Selector filtering** - Configurable blocked selectors (via module config)
4. **System page protection** - Cannot delete system pages (ID <= 7)
5. **File restrictions** - Only ProcessWire-managed files accessible
6. **Module safety** - Install/uninstall require explicit confirmation
7. **Config redaction** - Sensitive module config values (passwords, keys) are redacted

## Optional ProcessWire Module

Install the optional `ProcessWireMcp` module for:

- Configuration UI in ProcessWire admin
- Custom blocked selector configuration
- Hook support for third-party extensions

Copy `ProcessWireMcp.module.php` to `site/modules/ProcessWireMcp/` and install via ProcessWire admin.

## Testing

Tests run against a live ProcessWire installation. You can use either Docker (self-contained) or DDEV (existing dev environment).

### Docker (recommended for CI)

The Docker setup provisions a fresh ProcessWire install and runs the full test suite with a single command:

```bash
# Run all tests
docker compose -f docker-compose.test.yml run --rm tests

# Run a specific test class (use --filter, not file paths)
docker compose -f docker-compose.test.yml run --rm tests --filter=PageToolsTest

# Run multiple test classes
docker compose -f docker-compose.test.yml run --rm tests --filter='FieldToolsTest|TemplateToolsTest'

# Run with verbose output
docker compose -f docker-compose.test.yml run --rm tests --testdox

# Tear down containers
docker compose -f docker-compose.test.yml down --volumes

# Rebuild after Dockerfile changes
docker compose -f docker-compose.test.yml build --no-cache tests
```

> **Note:** Use `--filter` to select tests, not file paths. Passing paths like `tests/Tool/PageToolsTest.php` will fail with "Test file not found" because the package is symlinked inside the container at a different location (`/package` via Composer path repo) than the PHPUnit working directory (`/var/www/html`).

### DDEV

If you already have a DDEV project with ProcessWire:

#### Prerequisites

- A working DDEV project with ProcessWire installed and a populated database
- The package mounted into the DDEV container (e.g. via a docker-compose volume at `/packages/ProcessWireMcp`)
- The package installed as a Composer dependency so its classes are autoloaded
- PHPUnit available in the project's vendor (e.g. `phpunit/phpunit` in `require-dev`)
- The test namespace registered in your project's `autoload-dev`:

```json
{
  "autoload-dev": {
    "psr-4": {
      "Elabx\\ProcessWireMcp\\Tests\\": "/packages/ProcessWireMcp/tests/"
    }
  }
}
```

After changing autoload config, regenerate the autoloader:

```bash
ddev composer dump-autoload
```

#### Running Tests

```bash
ddev exec vendor/bin/phpunit --configuration /packages/ProcessWireMcp/phpunit.xml
```

With readable output:

```bash
ddev exec vendor/bin/phpunit --configuration /packages/ProcessWireMcp/phpunit.xml --testdox
```

Run a specific test class:

```bash
ddev exec vendor/bin/phpunit --configuration /packages/ProcessWireMcp/phpunit.xml --filter PageTools
```

### How It Works

The test bootstrap (`tests/bootstrap.php`) loads the host project's Composer autoloader, then boots ProcessWire by including its `index.php`. This gives tests access to the full ProcessWire API with a real database connection — the same environment the MCP server runs in. The `PW_PATH` environment variable controls the ProcessWire root (defaults to `/var/www/html`).

`tests/ProcessWireTestCase.php` is the base class for all tests. It exposes `$this->wire()` which returns the bootstrapped `ProcessWire` instance.

### Writing Tests

Extend `ProcessWireTestCase` and instantiate tool classes directly:

```php
<?php

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\PageTools;

class PageToolsTest extends ProcessWireTestCase
{
    private PageTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new PageTools();
        $this->tools->setWire($this->wire());
    }

    public function testFindPagesReturnsResults(): void
    {
        $result = $this->tools->findPages('limit=3');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('pages', $result['data']);
    }
}
```

## License

MIT License

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## Credits

Built with the [MCP PHP SDK](https://github.com/logiscape/mcp-sdk-php).
