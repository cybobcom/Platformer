<?php

CBAuth('admin');

// Include Adminer from outside webroot
// Adjust path to where you store the adminer file
$adminerFile = SOURCEDIR . 'admin/modules/tools/vendor/adminer/adminer-4.8.1-de.php';

if (!file_exists($adminerFile)) {
    echo '<p style="color:red;">Adminer not found at: ' . htmlspecialchars($adminerFile) . '</p>';
    exit;
}

include $adminerFile;
exit;