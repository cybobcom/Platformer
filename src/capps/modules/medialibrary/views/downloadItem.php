<?php

CBAuth(''); // logged-in users

// Download a media library item: ?item=<medialibrary_uid> (also base64 encoded)
// TODO: access check per item (categories/addressgroups), as before not implemented
$id = (string)($_REQUEST['item'] ?? '');
if ($id === '') {
    exit;
}
if (base64_encode(base64_decode($id)) === $id) {
    $id = base64_decode($id);
}

$objM = CBinitObject('MediaLibrary', $id);

// SECURITY: only files below data/ (path and name come from the DB)
$file = realpath(BASEDIR . $objM->getAttribute('path') . $objM->getAttribute('name'));
$dataDir = realpath(BASEDIR . 'data');
if ($file === false || $dataDir === false || !str_starts_with($file, $dataDir . '/') || !is_file($file)) {
    http_response_code(404);
    exit;
}

// Send the file unbuffered (large files must not end up in the output buffer)
while (ob_get_level()) {
    ob_end_clean();
}
$mime = function_exists('mime_content_type') ? (mime_content_type($file) ?: 'application/octet-stream') : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename($file)) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
