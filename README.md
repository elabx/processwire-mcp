# ProcessWire MCP Server

An extensible MCP (Model Context Protocol) server for ProcessWire CMS that enables AI assistants like Claude to interact with your ProcessWire installation.

## Features

- **Full ProcessWire API access** - Query, create, update, and delete pages
- **Template & field inspection** - Understand your site's data structure
- **User management** - List users and roles (passwords never exposed)
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

```bash
# With DDEV
ddev exec php vendor/bin/pw-mcp-server --pw-path=/var/www/html

# Standalone
php vendor/bin/pw-mcp-server --pw-path=/path/to/processwire

# Using environment variable
PW_PATH=/path/to/processwire php vendor/bin/pw-mcp-server
```

### Claude Code Configuration

Add to your `~/.claude/settings.json`:

```json
{
  "mcpServers": {
    "processwire": {
      "command": "ddev",
      "args": ["exec", "php", "vendor/bin/pw-mcp-server", "--pw-path=/var/www/html"]
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
      "args": ["/path/to/vendor/bin/pw-mcp-server", "--pw-path=/path/to/processwire"]
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

### Template Tools

| Tool | Description |
|------|-------------|
| `list_templates` | List all available templates |
| `get_template_fields` | Get template fields and configuration |
| `get_template_file` | Get template file path |

### Field Tools

| Tool | Description |
|------|-------------|
| `list_fields` | List all fields |
| `get_field` | Get field details and configuration |
| `list_field_types` | List available field types |

### User Tools

| Tool | Description |
|------|-------------|
| `list_users` | List users (optionally by role) |
| `get_user` | Get user details |
| `list_roles` | List all roles |

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
use Mcp\Attribute\McpTool;

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

## Optional ProcessWire Module

Install the optional `ProcessWireMcp` module for:

- Configuration UI in ProcessWire admin
- Custom blocked selector configuration
- Hook support for third-party extensions

Copy `ProcessWireMcp.module.php` to `site/modules/ProcessWireMcp/` and install via ProcessWire admin.

## License

MIT License

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## Credits

Built with the [MCP PHP SDK](https://github.com/logiscape/mcp-sdk-php).
