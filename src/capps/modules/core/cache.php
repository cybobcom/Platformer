<?php
// Page cache: GET only, not logged in, fixed TTL, no invalidation
(static function (): void {
    $c = CONFIGURATION['cache'] ?? [];
    if (empty($c['enabled'])) return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { header('X-Cache-Skip: method'); return; }

    $loggedIn = static fn() => !empty($_SESSION[PLATFORM_IDENTIFIER]['login_verified']);
    if ($loggedIn()) { header('X-Cache-Skip: login'); return; }

    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH) ?: '';
    if (!empty($c['paths'])) {
        $ok = false;
        foreach ($c['paths'] as $p) {
            if (str_starts_with($path, $p)) { $ok = true; break; }
        }
        if (!$ok) { header('X-Cache-Skip: path'); return; }
    }

    $dbgToken = (string)(CONFIGURATION['debug']['token'] ?? '');
    $debug    = $dbgToken !== '' && hash_equals($dbgToken, (string)($_GET['cbdebug'] ?? ''));
    $keyUri   = $uri;
    if ($debug) { // remove parameter from cache key
        $q = $_GET; unset($q['cbdebug']);
        $keyUri = $path . ($q ? '?' . http_build_query($q) : '');
    }

    $dir  = dirname(SOURCEDIR) . '/cache/system/';
    $file = $dir . md5(($_SERVER['HTTP_HOST'] ?? '') . $keyUri) . '.cache';
    $ttl  = (int)($c['ttl'] ?? 60);

    // Hit
    if (is_file($file) && time() - filemtime($file) < $ttl) {
        $d = @unserialize((string)@file_get_contents($file), ['allowed_classes' => false]);
        if (is_array($d)) {
            foreach ($d['h'] as $h) header($h);
            header('X-Cache: HIT');
            if ($debug) header('X-Cache-Time: ' . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms');
            // CSRF: insert the token of the current session (see placeholder below)
            $b = str_replace('###CB_CSRF_TOKEN###', $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)), $d['b']);
            if ($debug && !in_array(ltrim($b)[0] ?? '', ['{', '['], true)) {
                $box = '<div style="clear:both;font:13px monospace;background:#fff;color:#000;padding:10px;margin:10px;border:1px solid #ccc"><b>CBDebugger</b>: CACHE HIT, '
                    . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . ' ms, 0 Queries</div>';
                $pos = strripos($b, '</body>');
                $b = $pos === false ? $b . $box : substr_replace($b, $box, $pos, 0);
            }
            echo $b;
            exit;
        }
    }

    ob_start(static function (string $buf) use ($file, $dir, $loggedIn, $debug): string {
        if (http_response_code() !== 200 || $buf === '' || $loggedIn()) return $buf;

        $save = $buf;
        if ($debug) {
            if (in_array(ltrim($buf)[0] ?? '', ['{', '['], true)) return $buf; // do not cache JSON with _debug
            $save = preg_replace('/<!--CBDEBUG-->.*<!--\/CBDEBUG-->/s', '', $buf);
        }

        // CSRF: never cache the token of this session, store a placeholder instead
        if (!empty($_SESSION['csrf_token'])) $save = str_replace($_SESSION['csrf_token'], '###CB_CSRF_TOKEN###', $save);

        $h = [];
        foreach (headers_list() as $line) {
            if (preg_match('/^(Content-Type|Content-Encoding):/i', $line)) $h[] = $line;
        }
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $tmp = $file . '.' . getmypid();
        if (@file_put_contents($tmp, serialize(['h' => $h, 'b' => $save])) !== false) {
            @rename($tmp, $file);
        }
        return $buf;
    });
})();