<?php

declare(strict_types=1);

namespace Capps\Modules\Mcp\Classes;

use Capps\Modules\Database\Classes\CBDatabase;
use Capps\Modules\Database\Classes\CBObject;
use InvalidArgumentException;
use RuntimeException;

/**
 * CBMcp - MCP (Model Context Protocol) Integration
 *
 * KISS Principles:
 * - Auto-discovery via mcp.json
 * - Vendor override support (custom > agent > admin > capps)
 * - Token-based authentication
 * - Graceful error handling
 *
 * @example
 * // Initialize and discover modules
 * $mcp = new CBMcp();
 * $modules = $mcp->discoverModules();
 *
 * @example
 * // Validate token and get user context
 * $user = $mcp->validateToken($token);
 *
 * @example
 * // Get module config
 * $config = $mcp->getModuleConfig('addresses');
 */
class MCP
{
    private CBDatabase $db;
    private array $vendors;
    private array $discoveredModules = [];
    private ?array $currentUser = null;

    /**
     * Constructor
     */
    public function __construct(?array $dbConfig = null)
    {
        $this->db = new CBDatabase($dbConfig);
        $this->vendors = CONFIGURATION['cbinit']['vendors'] ?? [];

        // Sort by priority (highest first)
        uasort($this->vendors, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        // Ensure token column exists
        $this->ensureTokenColumn();
    }

    /**
     * Ensure mcp_token column exists in capps_address
     */
    /**
     * Ensure mcp_token column exists in capps_address
     */
    private function ensureTokenColumn(): bool
    {
        // Check if column exists using selectOne (safer)
        $result = $this->db->selectOne("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'capps_address' 
            AND COLUMN_NAME = 'mcp_token'
        ");

        if ($result !== null) {
            return true; // Column exists
        }

        // Create column
        $success = $this->db->query("
            ALTER TABLE capps_address 
            ADD COLUMN mcp_token VARCHAR(64) NULL,
            ADD COLUMN mcp_token_created DATETIME NULL,
            ADD INDEX idx_mcp_token (mcp_token)
        ");

        if (!$success) {
            error_log("CBMcp: Failed to create mcp_token column - " . $this->db->getLastError());
            return false;
        }

        return true;
    }

    /**
     * Generate MCP token for user
     *
     * @param string $addressUid User UUID
     * @return string|false Generated token or false on error
     */
    public function generateToken(string $addressUid): string|false
    {
        if (empty($addressUid)) {
            return false;
        }

        // Generate secure random token
        $token = bin2hex(random_bytes(32));

        // Update user record
        $success = $this->db->query("
            UPDATE capps_address 
            SET mcp_token = ?,
                mcp_token_created = NOW()
            WHERE address_uid = ?
        ", [$token, $addressUid]);

        if (!$success) {
            error_log("CBMcp: Failed to generate token - " . $this->db->getLastError());
            return false;
        }

        return $token;
    }

    /**
     * Revoke MCP token for user
     *
     * @param string $addressUid User UUID
     * @return bool Success
     */
    public function revokeToken(string $addressUid): bool
    {
        if (empty($addressUid)) {
            return false;
        }

        $success = $this->db->query("
            UPDATE capps_address 
            SET mcp_token = NULL,
                mcp_token_created = NULL
            WHERE address_uid = ?
        ", [$addressUid]);

        return (bool)$success;
    }

    /**
     * Validate token and return user data
     *
     * @param string $token MCP token from request
     * @return array|false User data or false
     */
    public function validateToken(string $token): array|false
    {
        if (empty($token)) {
            return false;
        }

        $result = $this->db->selectOne("
            SELECT address_uid, addressgroups, login, firstname, lastname
            FROM capps_address 
            WHERE mcp_token = ?
            AND active = 1
        ", [$token]);

        if (!$result) {
            return false;
        }

        $this->currentUser = $result;
        return $result;
    }

    /**
     * Get current authenticated user
     *
     * @return array|null User data or null
     */
    public function getCurrentUser(): ?array
    {
        return $this->currentUser;
    }

    /**
     * Check if current user has required role/group
     *
     * @param array $requiredGroups Array of group IDs
     * @return bool Has access
     */
    public function hasAccess(array $requiredGroups): bool
    {
        if (empty($requiredGroups)) {
            return true; // No restrictions
        }

        if (!$this->currentUser) {
            return false; // Not authenticated
        }

        $userGroups = explode(',', $this->currentUser['addressgroups'] ?? '');
        $userGroups = array_map('trim', $userGroups);

        // Check intersection
        return !empty(array_intersect($requiredGroups, $userGroups));
    }

    /**
     * Discover all MCP-enabled modules across vendors
     *
     * @return array Discovered modules with configs
     */
    public function discoverModules(): array
    {
        $this->discoveredModules = [];

        foreach ($this->vendors as $vendorName => $vendorConfig) {
            if (!($vendorConfig['enabled'] ?? true)) {
                continue;
            }

            $vendorPath = $vendorConfig['path'] ?? '';
            if (empty($vendorPath) || !is_dir($vendorPath)) {
                continue;
            }

            $modulesPath = rtrim($vendorPath, '/') . '/modules';
            if (!is_dir($modulesPath)) {
                continue;
            }

            // Scan modules in this vendor
            $modules = scandir($modulesPath);
            foreach ($modules as $module) {
                if ($module === '.' || $module === '..') {
                    continue;
                }

                $mcpJsonPath = "{$modulesPath}/{$module}/mcp.json";
                if (!file_exists($mcpJsonPath)) {
                    continue; // Module not MCP-enabled
                }

                // Load mcp.json
                $config = $this->loadModuleConfig($mcpJsonPath);
                if (!$config || !($config['enabled'] ?? true)) {
                    continue;
                }

                // Store with vendor prefix (higher priority overwrites)
                $moduleKey = $module;

                // Only store if not already stored OR if this vendor has higher priority
                if (!isset($this->discoveredModules[$moduleKey])) {
                    $this->discoveredModules[$moduleKey] = [
                        'vendor' => $vendorName,
                        'module' => $module,
                        'config' => $config,
                        'priority' => $vendorConfig['priority'] ?? 0
                    ];
                } else {
                    // Check if current vendor has higher priority
                    $currentPriority = $vendorConfig['priority'] ?? 0;
                    $existingPriority = $this->discoveredModules[$moduleKey]['priority'];

                    if ($currentPriority > $existingPriority) {
                        $this->discoveredModules[$moduleKey] = [
                            'vendor' => $vendorName,
                            'module' => $module,
                            'config' => $config,
                            'priority' => $currentPriority
                        ];
                    }
                }
            }
        }

        return $this->discoveredModules;
    }

    /**
     * Get module config (with vendor override support)
     *
     * @param string $module Module name
     * @return array|null Module config or null
     */
    public function getModuleConfig(string $module): ?array
    {
        // Discover if not done yet
        if (empty($this->discoveredModules)) {
            $this->discoverModules();
        }

        return $this->discoveredModules[$module] ?? null;
    }

    /**
     * Load mcp.json file
     *
     * @param string $path Path to mcp.json
     * @return array|null Parsed config or null
     */
    private function loadModuleConfig(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $config = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("CBMcp: Invalid JSON in {$path} - " . json_last_error_msg());
            return null;
        }

        return $config;
    }

    /**
     * Execute module operation
     *
     * @param string $module Module name
     * @param string $operation Operation (list, get, create, update, delete)
     * @param array $data Operation data
     * @return array Result with success/error
     */
    public function executeOperation(string $module, string $operation, array $data = []): array
    {
        // Get module config
        $moduleData = $this->getModuleConfig($module);
        if (!$moduleData) {
            return $this->error("Module '{$module}' not found or not MCP-enabled");
        }

        $config = $moduleData['config'];

        // Check if operation is allowed (new format with roles per operation)
        $operations = $config['operations'] ?? [];

        // Support old format: ["list", "get"] and new format: {"list": {"roles": []}}
        if (isset($operations[$operation])) {
            // New format
            $opConfig = $operations[$operation];
            $requiredRoles = $opConfig['roles'] ?? [];
        } elseif (in_array($operation, $operations)) {
            // Old format (backward compatible)
            $requiredRoles = $config['roles'] ?? [];
        } else {
            return $this->error("Operation '{$operation}' not allowed for module '{$module}'");
        }

        // Check operation-level permissions
        if (!empty($requiredRoles) && !$this->hasAccess($requiredRoles)) {
            return $this->error("Access denied - insufficient permissions for operation '{$operation}'");
        }

        // Load class
        $className = $config['class'] ?? null;
        if (!$className) {
            return $this->error("No class defined in mcp.json");
        }

        // Execute operation
        try {
            return match($operation) {
                'list' => $this->opList($className, $data),
                'get' => $this->opGet($className, $data),
                'create' => $this->opCreate($className, $data, $config),
                'update' => $this->opUpdate($className, $data, $config),
                'delete' => $this->opDelete($className, $data),
                default => $this->error("Unknown operation: {$operation}")
            };
        } catch (\Exception $e) {
            error_log("CBMcp: Operation failed - " . $e->getMessage());
            return $this->error("Operation failed: " . $e->getMessage());
        }
    }

    /**
     * Filter fields based on user permissions
     *
     * @param array $fields Input fields
     * @param array $config Module config
     * @return array Filtered fields
     */
    private function filterFieldsByPermissions(array $fields, array $config): array
    {
        if (!isset($config['fields'])) {
            return $fields; // No field restrictions
        }

        $allowedFields = [];
        $fieldConfig = $config['fields'];

        foreach ($fields as $fieldName => $value) {
            // Check if field exists in config
            if (!isset($fieldConfig[$fieldName])) {
                // Field not in config - allow by default (KISS)
                $allowedFields[$fieldName] = $value;
                continue;
            }

            // Check field-level roles
            $fieldRoles = $fieldConfig[$fieldName]['roles'] ?? [];

            if (empty($fieldRoles)) {
                // No role restrictions
                $allowedFields[$fieldName] = $value;
            } elseif ($this->hasAccess($fieldRoles)) {
                // User has required role
                $allowedFields[$fieldName] = $value;
            } else {
                // User doesn't have permission - skip field silently
                error_log("CBMcp: Field '{$fieldName}' filtered for user (insufficient permissions)");
            }
        }

        return $allowedFields;
    }

    /**
     * Operation: List
     */
    private function opList(string $className, array $data): array
    {
        $obj = CBinitObject($className);

        $conditions = $data['conditions'] ?? [];
        $options = $data['options'] ?? [];

        $results = $obj->findAll($conditions, $options);

        return $this->success([
            'items' => $results,
            'count' => count($results)
        ]);
    }

    /**
     * Operation: Get
     */
    private function opGet(string $className, array $data): array
    {
        $id = $data['id'] ?? null;
        if (!$id) {
            return $this->error("ID required for get operation");
        }

        $obj = CBinitObject($className, $id);

        if (!$obj->identifier) {
            return $this->error("Object not found");
        }

        return $this->success([
            'item' => $obj->arrAttributes
        ]);
    }

    /**
     * Operation: Create (with field filtering)
     */
    private function opCreate(string $className, array $data, array $config): array
    {
        $obj = CBinitObject($className);

        $fields = $data['fields'] ?? [];
        if (empty($fields)) {
            return $this->error("No fields provided");
        }

        // Filter fields by permissions
        $filteredFields = $this->filterFieldsByPermissions($fields, $config);

        if (empty($filteredFields)) {
            return $this->error("No allowed fields provided");
        }

        $newId = $obj->create($filteredFields);

        if (!$newId) {
            return $this->error("Create failed: " . $obj->getLastError());
        }

        return $this->success([
            'id' => $newId,
            'message' => 'Created successfully'
        ]);
    }

    /**
     * Operation: Update (with field filtering)
     */
    private function opUpdate(string $className, array $data, array $config): array
    {
        $id = $data['id'] ?? null;
        if (!$id) {
            return $this->error("ID required for update operation");
        }

        $fields = $data['fields'] ?? [];
        if (empty($fields)) {
            return $this->error("No fields provided");
        }

        $obj = CBinitObject($className, $id);

        if (!$obj->identifier) {
            return $this->error("Object not found");
        }

        // Filter fields by permissions
        $filteredFields = $this->filterFieldsByPermissions($fields, $config);

        if (empty($filteredFields)) {
            return $this->error("No allowed fields provided");
        }

        $success = $obj->update($filteredFields, $id);

        if (!$success) {
            return $this->error("Update failed: " . $obj->getLastError());
        }

        return $this->success([
            'id' => $id,
            'message' => 'Updated successfully',
            'updated_fields' => array_keys($filteredFields)
        ]);
    }

    /**
     * Operation: Delete
     */
    private function opDelete(string $className, array $data): array
    {
        $id = $data['id'] ?? null;
        if (!$id) {
            return $this->error("ID required for delete operation");
        }

        $obj = CBinitObject($className, $id);

        if (!$obj->identifier) {
            return $this->error("Object not found");
        }

        $success = $obj->delete($id);

        if (!$success) {
            return $this->error("Delete failed: " . $obj->getLastError());
        }

        return $this->success([
            'id' => $id,
            'message' => 'Deleted successfully'
        ]);
    }

    /**
     * Success response
     */
    private function success(array $data = []): array
    {
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Error response
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message
        ];
    }
}