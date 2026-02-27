<?php

declare(strict_types=1);

namespace ProcessWire;

/**
 * ProcessWire MCP Module
 *
 * Optional ProcessWire module for configuration and extension support.
 * Installing this module enables hooks for third-party tool registration.
 *
 * @property string $blockedSelectors
 * @property string $remoteSites
 */
class ProcessWireMcp extends WireData implements Module, ConfigurableModule
{
    /**
     * Module info
     */
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'ProcessWire MCP Server',
            'summary' => 'MCP (Model Context Protocol) Server for AI assistant integration',
            'version' => '0.1.0',
            'author' => 'Elabx',
            'href' => 'https://github.com/elabx/processwire-mcp',
            'autoload' => true,
            'singular' => true,
            'icon' => 'robot',
            'requires' => [
                'PHP>=8.1',
                'ProcessWire>=3.0.200',
            ],
        ];
    }

    /**
     * Default configuration
     */
    public function __construct()
    {
        parent::__construct();
        $this->set('blockedSelectors', '');
        $this->set('remoteSites', '');
    }

    /**
     * Initialize the module
     */
    public function init(): void
    {
        // Module is loaded - hooks are available for extensions
    }

    /**
     * Get tool classes (hookable)
     *
     * Third-party modules can hook this to add custom tool classes:
     *
     * ```php
     * $this->addHookAfter('ProcessWireMcp::getToolClasses', function($event) {
     *     $classes = $event->return;
     *     $classes[] = MyCustomTools::class;
     *     $event->return = $classes;
     * });
     * ```
     *
     * @return array<class-string>
     */
    public function ___getToolClasses(): array
    {
        // Return empty array - core tools are registered by the server itself
        // This hook is for extensions to add their tools
        return [];
    }

    /**
     * Get resource classes (hookable)
     *
     * Third-party modules can hook this to add custom resource classes.
     *
     * @return array<class-string>
     */
    public function ___getResourceClasses(): array
    {
        return [];
    }

    /**
     * Get blocked selectors
     *
     * @return array Selectors that should be blocked
     */
    public function getBlockedSelectors(): array
    {
        $blocked = $this->get('blockedSelectors');
        if (empty($blocked)) {
            return [];
        }
        return array_filter(array_map('trim', explode("\n", $blocked)));
    }

    /**
     * Module configuration
     */
    public function getModuleConfigInputfields(InputfieldWrapper $inputfields): InputfieldWrapper
    {
        $modules = $this->wire()->modules;

        // Header info
        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = 'MCP Server Information';
        $f->value = $this->getInfoMarkup();
        $inputfields->add($f);

        // Blocked selectors
        /** @var InputfieldTextarea $f */
        $f = $modules->get('InputfieldTextarea');
        $f->name = 'blockedSelectors';
        $f->label = 'Blocked Selectors';
        $f->description = 'Enter selectors (one per line) that should be blocked from MCP queries. These selectors will be excluded from all page searches.';
        $f->notes = 'Example: template=admin';
        $f->value = $this->get('blockedSelectors');
        $f->rows = 5;
        $inputfields->add($f);

        // Remote sites
        /** @var InputfieldTextarea $f */
        $f = $modules->get('InputfieldTextarea');
        $f->name = 'remoteSites';
        $f->label = 'Remote Sites (DDEV Multi-Instance)';
        $f->description = 'Configure remote ProcessWire sites accessible via DDEV for cross-site tool execution. One site per line.';
        $f->notes = 'Format: project-name | Optional Label | /optional/pw/path' . "\n"
            . 'Example: my-other-site | My Other Site | /var/www/html' . "\n"
            . 'Lines starting with # are treated as comments.';
        $f->value = $this->get('remoteSites');
        $f->rows = 5;
        $f->collapsed = empty($this->get('remoteSites'))
            ? \ProcessWire\Inputfield::collapsedYes
            : \ProcessWire\Inputfield::collapsedNo;
        $inputfields->add($f);

        return $inputfields;
    }

    /**
     * Get info markup for config page
     */
    private function getInfoMarkup(): string
    {
        $config = $this->wire()->config;

        // Generate example Claude Code config
        $exampleConfig = json_encode([
            'mcpServers' => [
                'processwire' => [
                    'command' => 'ddev',
                    'args' => ['exec', 'php', 'vendor/bin/pw-mcp-server', '--pw-path=/var/www/html'],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<HTML
<div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
    <h3>ProcessWire MCP Server</h3>
    <p>This module provides MCP (Model Context Protocol) integration for AI assistants like Claude Code.</p>

    <h4>Running the Server</h4>
    <pre style="background: #333; color: #fff; padding: 15px; border-radius: 3px; overflow-x: auto;">
# With DDEV
ddev exec php vendor/bin/pw-mcp-server --pw-path=/var/www/html

# Standalone
php vendor/bin/pw-mcp-server --pw-path=/path/to/processwire</pre>

    <h4>Claude Code Configuration</h4>
    <p>Add the following to your Claude Code settings (<code>~/.claude/settings.json</code>):</p>
    <pre style="background: #333; color: #fff; padding: 15px; border-radius: 3px; overflow-x: auto;">{$exampleConfig}</pre>

    <h4>Remote Sites (Multi-Instance)</h4>
    <p>In DDEV environments, you can configure remote ProcessWire sites to enable cross-site tool execution.
    Use the "Remote Sites" field below to whitelist DDEV projects, then use <code>list_remote_sites</code>,
    <code>list_remote_tools</code>, and <code>remote_call</code> tools to interact with them.</p>

    <h4>Registering Custom Tools</h4>
    <p>Third-party modules can register custom MCP tools by hooking into this module:</p>
    <pre style="background: #333; color: #fff; padding: 15px; border-radius: 3px; overflow-x: auto;">
// In your module's ready() method:
\$this->addHookAfter('ProcessWireMcp::getToolClasses', function(\$event) {
    \$classes = \$event->return;
    \$classes[] = MyCustomMcpTools::class;
    \$event->return = \$classes;
});</pre>
</div>
HTML;
    }
}
