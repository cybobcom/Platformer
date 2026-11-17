<?php

CBAuth('public'); // own check: ?token= must match CONFIGURATION['debug']['token'] (used by compare of another instance)

use Capps\Modules\Installer\Classes\CBStatus;

$token = (string)(CONFIGURATION['debug']['token'] ?? '');
if ($token === '' || !hash_equals($token, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit;
}

$status = new CBStatus();

header('Content-Type: application/json');
echo json_encode($status->generateSnapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
