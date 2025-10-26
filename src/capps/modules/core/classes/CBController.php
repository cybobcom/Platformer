<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

use Capps\Modules\Database\Classes\CBObject;

/**
 * CBController - Base Controller Class
 *
 * Provides common functionality for all controller actions:
 * - JSON response handling
 * - Error handling
 * - Permission checks
 * - Object initialization
 *
 * Usage in controller files:
 * require_once CAPPS.'modules/core/classes/CBController.php';
 * $controller = new \Capps\Modules\Core\Classes\CBController('Address');
 */
class CBController
{
    protected ?CBObject $objModule = null;
    protected string $moduleName = '';

    /**
     * Constructor
     *
     * @param string $moduleName Name of the module (e.g. 'Address', 'Content')
     * @param mixed $id Optional ID to load existing object
     */
    public function __construct(string $moduleName, mixed $id = null)
    {
        $this->moduleName = $moduleName;
        $this->objModule = CBinitObject(ucfirst($moduleName), $id);
    }

    /**
     * Send JSON response
     *
     * @param array $data Response data
     * @param int $status HTTP status code
     */
    protected function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_HEX_APOS);
        exit;
    }

    /**
     * Send error response
     *
     * @param string $message Error message
     * @param int $status HTTP status code (default 400)
     */
    protected function errorResponse(string $message, int $status = 400): void
    {
        $this->jsonResponse([
            'response' => 'error',
            'description' => $message
        ], $status);
    }

    /**
     * Send success response
     *
     * @param array $data Additional data to include in response
     */
    protected function successResponse(array $data = []): void
    {
        $this->jsonResponse(array_merge([
            'response' => 'success',
            'description' => 'ok'
        ], $data));
    }

    /**
     * Check if user has required permission
     *
     * @param string|array $requiredGroups Required addressgroups
     * @return bool
     */
    protected function checkPermission($requiredGroups): bool
    {
        global $objPlatformUser;

        if (empty($requiredGroups)) {
            return true;
        }

        $userGroups = $objPlatformUser->get('addressgroups') ?? '';

        return checkIntersection($userGroups, $requiredGroups);
    }

    /**
     * Validate required fields in request
     *
     * @param array $requiredFields List of required field names
     * @param array $data Data array to validate (default: $_REQUEST)
     * @return bool
     */
    protected function validateRequired(array $requiredFields, array $data = null): bool
    {
        $data = $data ?? $_REQUEST;

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $this->errorResponse("Required field missing: {$field}");
                return false;
            }
        }

        return true;
    }

    /**
     * Get module object
     *
     * @return CBObject|null
     */
    public function getModule(): ?CBObject
    {
        return $this->objModule;
    }

    // ================================================================
    // SECURITY FEATURES
    // ================================================================

    /**
     * Require user to be logged in
     * Stops execution if not authenticated
     */
    protected function requireLogin(): void
    {
        if (!isset($_SESSION[PLATFORM_IDENTIFIER]['login_verified'])
            || $_SESSION[PLATFORM_IDENTIFIER]['login_verified'] != "1"
            || !isset($_SESSION[PLATFORM_IDENTIFIER]['login_user_identifier'])
            || $_SESSION[PLATFORM_IDENTIFIER]['login_user_identifier'] == "") {
            $this->errorResponse('Authentication required', 401);
        }
    }

    /**
     * Require specific permission/addressgroup
     * Stops execution if permission denied
     *
     * @param string|array $requiredGroup Required addressgroup(s)
     */
    protected function requirePermission($requiredGroup): void
    {
        $this->requireLogin();

        if (!$this->checkPermission($requiredGroup)) {
            $this->errorResponse('Permission denied', 403);
        }
    }

    /**
     * Validate CSRF token for POST requests
     * Call this at the start of any state-changing operation
     */
    protected function validateCSRF(): void
    {
        // Only check POST/PUT/DELETE requests
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'DELETE'])) {
            return;
        }

        $token = $_REQUEST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if ($token === '' || $token !== $sessionToken) {
            $this->errorResponse('Invalid CSRF token', 403);
        }
    }

    /**
     * Rate limiting for actions (e.g. login attempts)
     *
     * @param string $action Action identifier (e.g. 'login', 'api_call')
     * @param int $maxAttempts Maximum attempts per time window
     * @param int $windowSeconds Time window in seconds (default: 60)
     */
    protected function rateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 60): void
    {
        $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Initialize if not exists
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'time' => time()
            ];
        }

        // Reset counter if time window expired
        if (time() - $_SESSION[$key]['time'] > $windowSeconds) {
            $_SESSION[$key] = [
                'count' => 0,
                'time' => time()
            ];
        }

        // Increment counter
        $_SESSION[$key]['count']++;

        // Check limit
        if ($_SESSION[$key]['count'] > $maxAttempts) {
            $this->errorResponse('Too many requests. Please try again later.', 429);
        }
    }

    /**
     * Sanitize input data to prevent XSS
     *
     * @param array $data Input data array
     * @return array Sanitized data
     */
    protected function sanitizeInput(array $data): array
    {
        return array_map(function($value) {
            if (is_array($value)) {
                return $this->sanitizeInput($value); // Recursive for nested arrays
            }
            if (is_string($value)) {
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            return $value;
        }, $data);
    }

    /**
     * Validate email format
     *
     * @param string $email Email to validate
     * @return bool
     */
    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate CSRF token (call once per session)
     * This should be called in your session initialization
     *
     * @return string The generated token
     */
    public static function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Get current CSRF token
     *
     * @return string
     */
    public static function getCSRFToken(): string
    {
        return $_SESSION['csrf_token'] ?? self::generateCSRFToken();
    }
}