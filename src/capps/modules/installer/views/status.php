<?php

use Capps\Modules\Installer\Classes\CBStatus;

$status = new CBStatus();

header('Content-Type: application/json');
echo json_encode($status->generateSnapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
