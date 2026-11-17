<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

class CBDebugger
{
    private static array $points = [];
    private static array $queries = [];

    public static function start(): void
    {
        self::$points[] = [$_SERVER['REQUEST_TIME_FLOAT'], 'Request-Start', 0, 0];
        self::point('bis Debugger bereit');
    }

    public static function point(string $name): void
    {
        self::$points[] = [microtime(true), $name, memory_get_usage(), count(self::$queries)];
    }

    public static function query(string $sql, float $t0): void
    {
        $dur = microtime(true) - $t0;
        $caller = '';
        $fr = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
        foreach ($fr as $i => $f) {
            $b = basename($f['file'] ?? '');
            if ($b === '' || in_array($b, ['CBDatabase.php', 'CBObject.php', 'CBDebugger.php', 'functions.php'], true)) continue;
            if (($fr[$i + 1]['function'] ?? '') === '__construct') continue; // parent::__construct überspringen
            $caller = $b . ':' . ($f['line'] ?? 0);
            break;
        }
        self::$queries[] = [$sql, $dur, $caller];
    }

    public static function attach(string $output, string $type): string
    {
        self::point('Stop');
        $data = self::summary();

        if ($type === 'json') {
            $obj = json_decode($output);
            if (!is_object($obj)) {
                return $output; // Liste oder kein JSON: weglassen
            }
            $obj->_debug = $data;
            return json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $output;
        }

        $html = self::renderHtml($data);
        $pos = strripos($output, '</body>');
        return $pos === false ? $output . $html : substr_replace($output, $html, $pos, 0);
    }

    private static function normalize(string $sql): string
    {
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql);
        $sql = preg_replace('/\b\d+\b/', '?', $sql);
        $sql = preg_replace('/\(\s*\?(?:\s*,\s*\?)+\s*\)/', '(?)', $sql);
        return trim(preg_replace('/\s+/', ' ', $sql));
    }

    private static function summary(): array
    {
        $p = self::$points;
        $n = count($p);
        $total = $n > 1 ? $p[$n - 1][0] - $p[0][0] : 0;

        $points = [];
        for ($i = 1; $i < $n; $i++) {
            $dt = $p[$i][0] - $p[$i - 1][0];
            $points[] = [
                'name' => $p[$i][1],
                'ms' => round($dt * 1000, 2),
                'pct' => $total > 0 ? round($dt * 100 / $total, 1) : 0,
                'mb' => round($p[$i][2] / 1048576, 2),
                'queries' => $p[$i][3] - $p[$i - 1][3],
            ];
        }

        $groups = [];
        $dbTime = 0.0;
        foreach (self::$queries as [$sql, $dur, $caller]) {
            $dbTime += $dur;
            $k = self::normalize($sql);
            $groups[$k] ??= ['sql' => $k, 'count' => 0, 'ms' => 0.0, 'caller' => $caller];
            $groups[$k]['count']++;
            $groups[$k]['ms'] += $dur * 1000;
        }

        $rep = array_values(array_filter($groups, fn($g) => $g['count'] > 1));
        usort($rep, fn($a, $b) => $b['count'] <=> $a['count']);
        $rep = array_map(fn($g) => ['count' => $g['count'], 'ms' => round($g['ms'], 2), 'caller' => $g['caller'], 'sql' => substr($g['sql'], 0, 300)], array_slice($rep, 0, 15));

        $all = [];
        foreach (self::$queries as $i => [$sql, $dur, $caller]) {
            $all[] = ['#' => $i + 1, 'ms' => round($dur * 1000, 2), 'caller' => $caller, 'sql' => substr(trim(preg_replace('/\s+/', ' ', $sql)), 0, 300)];
        }

        return [
            'total_ms' => round($total * 1000, 2),
            'queries' => count(self::$queries),
            'db_ms' => round($dbTime * 1000, 2),
            'peak_mb' => round(memory_get_peak_usage() / 1048576, 2),
            'points' => $points,
            'repeated' => $rep,
            'slow_ms' => (float)(CONFIGURATION['debug']['slow_ms'] ?? 5),
            'all' => $all,
        ];
    }

    private static function table(array $rows, ?float $slowMs = null): string {
        if (!$rows) {
            return '<i>keine</i>';
        }
        $o = '<table border="1" cellpadding="3" style="border-collapse:collapse;width:100%"><tr>';
        foreach (array_keys($rows[0]) as $h) {
            $o .= '<th align="left">' . htmlspecialchars((string)$h) . '</th>';
        }
        $o .= '</tr>';
        foreach ($rows as $r) {
            $o .= '<tr' . ($slowMs !== null && $r['ms'] >= $slowMs ? ' style="background:#fcc;color:#900"' : '') . '>';
            foreach ($r as $v) {
                $o .= '<td>' . htmlspecialchars((string)$v, ENT_QUOTES) . '</td>';
            }
            $o .= '</tr>';
        }
        return $o . '</table>';
    }

    private static function renderHtml(array $d): string
    {
        return '<!--CBDEBUG--><div style="clear:both;...">'
            . '<div style="clear:both;font:13px monospace;background:#fff;color:#000;padding:10px;margin:10px;border:1px solid #ccc">'
            . '<b>CBDebugger</b>: ' . $d['total_ms'] . ' ms, ' . $d['queries'] . ' Queries (' . $d['db_ms'] . ' ms), Peak ' . $d['peak_mb'] . ' MB'
            . '<br><br><b>Messpunkte</b>' . self::table($d['points'])
            . '<br><b>Wiederholte Queries</b>' . self::table($d['repeated'])
            . '<br><b>Alle Queries (chronologisch, rot ab ' . $d['slow_ms'] . ' ms)</b>' . self::table($d['all'], $d['slow_ms'])
            . '</div>'
            . '</div><!--/CBDEBUG-->';
    }
}