<?php
// Load shedding: no DB, no Platformer classes
(static function (): void {
    $c = @include __DIR__ . '/overload.config.php';
    if (!is_array($c) || ($c['mode'] ?? 'off') === 'off') return;

    $p = (int)($c['shed_percent'] ?? 0);
    if ($c['mode'] === 'auto') {
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? null) : null;
        if ($load === null) return; // without load average: manual mode only
        $t   = (float)($c['auto']['load_threshold'] ?? 4);
        $max = (int)($c['auto']['max_percent'] ?? 80);
        $p   = $load <= $t ? 0 : (int)min($max, $max * ($load - $t) / $t);
    }
    if ($p <= 0 || rand(1, 100) > $p) return;

    $retry = (int)($c['retry_after'] ?? 60);
    http_response_code(503);
    header('Retry-After: ' . $retry);
    header('Cache-Control: no-store');

    $path  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $iface = (string)($_GET['interface'] ?? '');

    // API -> JSON-Objekt
    if (str_contains($path, '/api/')
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_GET['format'] ?? '') === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'busy', 'retry_after' => $retry]);
        exit;
    }

    // interface expecting JSON -> JSON array (like "invalid interface")
    if ($iface !== '' && in_array($iface, $c['json_interfaces'] ?? [], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([['id' => '', 'name' => 'busy', 'status' => 'busy', 'retry_after' => $retry]]);
        exit;
    }

    // otherwise HTML
    $title = $c['message_title'] ?? 'Kurze Pause';
    $msg   = $c['message_html'] ?? 'Aktuell sind sehr viele Nutzer online. Bitte später erneut versuchen.';
    $tpl   = @file_get_contents(($_SERVER['DOCUMENT_ROOT'] ?? '') . '/data/template/views/overload.html');
    if (!$tpl) {
        $tpl = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>###title###</title></head><body style="font-family:sans-serif;text-align:center;padding:2em"><h2>###title###</h2><p>###message###</p></body></html>';
    }
    header('Content-Type: text/html; charset=utf-8');
    echo str_replace(['###title###', '###message###'], [$title, $msg], $tpl);
    exit;
})();