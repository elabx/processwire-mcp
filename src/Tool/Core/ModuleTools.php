<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * Module management tools for ProcessWire MCP
 *
 * Provides tools for listing, inspecting, installing, uninstalling,
 * and configuring modules.
 */
class ModuleTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'module_tools',
            'description' => 'Module management tools for ProcessWire',
            'priority' => 70,
        ];
    }

    /**
     * List all modules
     *
     * @param bool $installedOnly Whether to show only installed modules
     * @return array Module list
     */
    #[McpTool(
        name: 'list_modules',
        description: 'List all modules. Set installedOnly=true to show only installed modules.'
    )]
    public function listModules(bool $installedOnly = false): array
    {
        try {
            $modules = $this->modules();
            $modules->resetCache();

            $allInfo = [];

            // Installed modules
            foreach ($modules as $module) {
                $info = $modules->getModuleInfoVerbose($module);
                $allInfo[] = [
                    'className' => $module->className(),
                    'title' => $info['title'] ?? '',
                    'version' => $info['version'] ?? 0,
                    'summary' => $info['summary'] ?? '',
                    'installed' => true,
                ];
            }

            // Uninstalled modules
            if (!$installedOnly) {
                $installable = $modules->getInstallable();
                foreach ($installable as $className) {
                    $info = $modules->getModuleInfoVerbose($className);
                    $allInfo[] = [
                        'className' => $className,
                        'title' => $info['title'] ?? '',
                        'version' => $info['version'] ?? 0,
                        'summary' => $info['summary'] ?? '',
                        'installed' => false,
                    ];
                }
            }

            // Sort by className
            usort($allInfo, fn($a, $b) => strcasecmp($a['className'], $b['className']));

            return $this->success([
                'count' => count($allInfo),
                'modules' => $allInfo,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to list modules: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed information about a module
     *
     * @param string $className Module class name
     * @return array Module details
     */
    #[McpTool(
        name: 'get_module_info',
        description: 'Get detailed information about a module including its requirements, installs, and whether it is configurable.'
    )]
    public function getModuleInfo(string $className): array
    {
        try {
            $info = $this->modules()->getModuleInfoVerbose($className);

            if (empty($info) || empty($info['name'])) {
                return $this->error("Module not found: {$className}", 'NOT_FOUND');
            }

            $result = [
                'className' => $className,
                'title' => $info['title'] ?? '',
                'version' => $info['version'] ?? 0,
                'summary' => $info['summary'] ?? '',
                'href' => $info['href'] ?? '',
                'installed' => $this->modules()->isInstalled($className),
                'configurable' => !empty($info['configurable']),
                'requires' => $info['requires'] ?? [],
                'installs' => $info['installs'] ?? [],
                'author' => $info['author'] ?? '',
            ];

            return $this->success($result);

        } catch (\Throwable $e) {
            return $this->error('Failed to get module info: ' . $e->getMessage());
        }
    }

    /**
     * Install a module
     *
     * @param string $className Module class name
     * @param bool $confirm Safety confirmation flag
     * @return array Installation result
     */
    #[McpTool(
        name: 'install_module',
        description: 'Install a module. Requires confirm=true as a safety measure.'
    )]
    public function installModule(string $className, bool $confirm = false): array
    {
        try {
            if (!$confirm) {
                return $this->error('You must set confirm=true to install a module. This is a safety measure.');
            }

            if ($this->modules()->isInstalled($className)) {
                return $this->error("Module is already installed: {$className}");
            }

            $module = $this->modules()->install($className);

            if (!$module) {
                return $this->error("Failed to install module: {$className}");
            }

            $info = $this->modules()->getModuleInfoVerbose($className);

            return $this->success([
                'className' => $className,
                'title' => $info['title'] ?? '',
                'version' => $info['version'] ?? 0,
                'message' => "Module '{$className}' installed successfully.",
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to install module: ' . $e->getMessage());
        }
    }

    /**
     * Uninstall a module
     *
     * @param string $className Module class name
     * @param bool $confirm Safety confirmation flag
     * @return array Uninstallation result
     */
    #[McpTool(
        name: 'uninstall_module',
        description: 'Uninstall a module. Requires confirm=true as a safety measure. Fails if other modules depend on it.'
    )]
    public function uninstallModule(string $className, bool $confirm = false): array
    {
        try {
            if (!$confirm) {
                return $this->error('You must set confirm=true to uninstall a module.');
            }

            if (!$this->modules()->isInstalled($className)) {
                return $this->error("Module is not installed: {$className}");
            }

            $this->modules()->uninstall($className);

            return $this->success([
                'className' => $className,
                'message' => "Module '{$className}' uninstalled successfully.",
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to uninstall module: ' . $e->getMessage());
        }
    }

    /**
     * Get configuration values for an installed module
     *
     * @param string $className Module class name
     * @return array Module configuration
     */
    #[McpTool(
        name: 'get_module_config',
        description: 'Get the configuration values for an installed module. Sensitive values (passwords, secrets, tokens, keys) are redacted.'
    )]
    public function getModuleConfig(string $className): array
    {
        try {
            if (!$this->modules()->isInstalled($className)) {
                return $this->error("Module is not installed: {$className}");
            }

            $config = $this->modules()->getConfig($className);

            if (!is_array($config) || empty($config)) {
                return $this->success([
                    'className' => $className,
                    'config' => [],
                ]);
            }

            // Redact sensitive values
            $sensitivePattern = '/pass|secret|token|api.?key|private.?key/i';
            foreach ($config as $key => $value) {
                if (preg_match($sensitivePattern, $key)) {
                    $config[$key] = '[REDACTED]';
                }
            }

            return $this->success([
                'className' => $className,
                'config' => $config,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to get module config: ' . $e->getMessage());
        }
    }

    /**
     * Save configuration values for an installed module
     *
     * @param string $className Module class name
     * @param array $config Configuration key => value pairs to save
     * @return array Save result
     */
    #[McpTool(
        name: 'save_module_config',
        description: 'Save configuration values for an installed, configurable module.'
    )]
    public function saveModuleConfig(
        string $className,
        #[Schema(type: 'object', description: 'Configuration key => value pairs to save', additionalProperties: true)]
        array $config
    ): array {
        try {
            if (!$this->modules()->isInstalled($className)) {
                return $this->error("Module is not installed: {$className}");
            }

            $info = $this->modules()->getModuleInfoVerbose($className);
            if (empty($info['configurable'])) {
                return $this->error("Module is not configurable: {$className}");
            }

            if (empty($config)) {
                return $this->error('No configuration values provided.');
            }

            $this->modules()->saveConfig($className, $config);

            return $this->success([
                'className' => $className,
                'savedKeys' => array_keys($config),
                'message' => "Configuration saved for module '{$className}'.",
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to save module config: ' . $e->getMessage());
        }
    }
}
