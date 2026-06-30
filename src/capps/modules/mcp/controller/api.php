<?php

declare(strict_types=1);

/**
 * MCP API Controller
 *
 * HTTP endpoint for MCP operations
 * Route: /controller/mcp/api
 *
 * Expected Request Format:
 * POST /controller/mcp/api
 * Headers:
 *   Authorization: Bearer {token}
 *   Content-Type: application/json
 * Body:
 * {
 *   "module": "addresses",
 *   "operation": "list|get|create|update|delete",
 *   "data": {
 *     "id": "...",           // for get, update, delete
 *     "fields": {...},       // for create, update
 *     "conditions": {...},   // for list
 *     "options": {...}       // for list (order, limit, etc.)
 *   }
 * }
 */

// Ensure we're returning JSON
header('Content-Type: application/json');

// CORS headers (if needed for remote access)
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: POST, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

try {
    // Load MCP class
    $mcp = CBinitObject('MCP');


    // Extract token - robust für Authorization Header ODER X-API-Token
    $token = null;

    // Try getallheaders (Apache/MAMP)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();

        // Option 1: X-API-Token (direkter Wert)
        if (isset($headers['X-API-Token'])) {
            $token = trim($headers['X-API-Token']);
        }
        // Case-insensitive fallback für X-API-Token
        elseif (isset($headers['x-api-token'])) {
            $token = trim($headers['x-api-token']);
        }
        // Option 2: Authorization Header (Bearer Token)
        else {
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
                $token = trim($matches[1]);
            }
        }
    }
    // Fallback für andere Server (nginx etc.)
    else {
        // Try X-API-Token from $_SERVER
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? null;

        if (!$token) {
            // Try Authorization header
            $authHeader = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? getenv('HTTP_AUTHORIZATION')
                ?? '';

            if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
                $token = trim($matches[1]);
            }
        }
    }

    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }



    // Validate token
    if (empty($token)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Missing authorization token'
        ]);
        exit;
    }

    $user = $mcp->validateToken($token);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired token'
        ]);
        exit;
    }

    // Parse JSON body
    $rawBody = file_get_contents('php://input');
    $request = json_decode($rawBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON: ' . json_last_error_msg()
        ]);
        exit;
    }

    // Validate request structure
    $module = $request['module'] ?? null;
    $operation = $request['operation'] ?? null;
    $data = $request['data'] ?? [];

    /*
    if (empty($module)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required field: module'
        ]);
        exit;
    }
    */

// Module is optional for discover operation
    if (empty($module) && $operation !== 'discover') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required field: module'
        ]);
        exit;
    }

    if (empty($operation)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required field: operation'
        ]);
        exit;
    }

    // Validate operation
    $validOperations = ['list', 'get', 'create', 'update', 'delete', 'discover'];
    if (!in_array($operation, $validOperations)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid operation. Allowed: ' . implode(', ', $validOperations)
        ]);
        exit;
    }

    // Handle discovery separately (no module-specific check needed)
    if ($operation === 'discover') {
        $discovered = $mcp->discoverModules();

        // Format for MCP clients
        $modules = [];
        foreach ($discovered as $moduleName => $moduleData) {
            $modules[$moduleName] = [
                'vendor' => $moduleData['vendor'],
                'class' => $moduleData['config']['class'],
                'operations' => $moduleData['config']['operations'] ?? [],
                'fields' => $moduleData['config']['fields'] ?? []
            ];
        }

        $result = [
            'success' => true,
            'data' => ['modules' => $modules]
        ];
    } else {
        // Execute normal operation
        $result = $mcp->executeOperation($module, $operation, $data);
    }

    // Set appropriate HTTP status code
    if ($result['success']) {
        http_response_code(200);
    } else {
        // Determine error type
        $errorMsg = strtolower($result['error'] ?? '');

        if (strpos($errorMsg, 'not found') !== false) {
            http_response_code(404);
        } elseif (strpos($errorMsg, 'access denied') !== false || strpos($errorMsg, 'permission') !== false) {
            http_response_code(403);
        } else {
            http_response_code(400);
        }
    }

    // Return result
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log error
    error_log("MCP API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        //'message' => (getenv('ENVIRONMENT') === 'development') ? $e->getMessage() : 'An error occurred'
        'message' => $e->getMessage()

    ]);
}

exit;