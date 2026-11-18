<?php

CBAuth('admin');

// Shared hosting: set_time_limit() often disabled
if (!function_exists('set_time_limit')) {
    function set_time_limit($seconds) {}
}

// Include Adminer from outside webroot
// Adjust path to where you store the adminer file
$adminerFile = __DIR__ . '/../vendor/adminer/adminer-4.8.1-de.php';

if (!file_exists($adminerFile)) {
    echo '<p style="color:red;">Adminer not found at: ' . htmlspecialchars($adminerFile) . '</p>';
    exit;
}

include $adminerFile;
exit;