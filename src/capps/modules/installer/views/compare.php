<?php

use Capps\Modules\Installer\Classes\CBCompare;

$comparer = new CBCompare();

$result = null;
$targetUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUrl = trim($_POST['target_url'] ?? '');
    if ($targetUrl) {
        $result = $comparer->compareWithUrl($targetUrl);
    }
}
// Basis-URL fuer den lokalen filecontent-Endpunkt (aus der aktuellen URL abgeleitet)
$localBase = preg_replace('#/compare/?(\?.*)?$#', '/filecontent/', $_SERVER['REQUEST_URI']);

// Basis-URL fuer den remote filecontent-Endpunkt (aus der eingegebenen status-URL abgeleitet)
$remoteBase = $targetUrl ? preg_replace('#/status/?(\?.*)?$#', '/filecontent/', $targetUrl) : '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Installations-Vergleich</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 2rem auto; }
        .message { padding: 0.5rem 1rem; background: #fdd; border: 1px solid #c99; margin-bottom: 1rem; }
        .module-block { border: 1px solid #ccc; padding: 1rem; margin-bottom: 1.5rem; }
        .tag { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 3px; font-size: 0.85em; margin-left: 0.5rem; }
        .tag-local { background: #cfe; }
        .tag-remote { background: #fec; }
        .tag-diff { background: #ffc; }
        table { border-collapse: collapse; width: 100%; margin: 0.5rem 0; }
        th, td { border: 1px solid #ddd; padding: 0.3rem 0.5rem; text-align: left; font-size: 0.9em; }
        ul { margin: 0.3rem 0; }

        dialog#diffModal { width: 90%; max-width: 1000px; padding: 0; border: 1px solid #999; }
        dialog#diffModal .diff-header { padding: 0.75rem 1rem; background: #333; color: #fff; display: flex; justify-content: space-between; align-items: center; }
        dialog#diffModal .diff-body { display: flex; max-height: 70vh; overflow: auto; font-family: monospace; font-size: 0.85em; }
        dialog#diffModal .diff-col { flex: 1; padding: 0.5rem; white-space: pre-wrap; word-break: break-all; border-right: 1px solid #ccc; }
        .diff-line-removed { background: #fdd; }
        .diff-line-added { background: #dfd; }
    </style>
</head>
<body>

<dialog id="diffModal">
    <div class="diff-header">
        <span id="diffTitle"></span>
        <button type="button" onclick="document.getElementById('diffModal').close()">Schliessen</button>
    </div>
    <div class="diff-body">
        <div class="diff-col" id="diffLocal">Lade...</div>
        <div class="diff-col" id="diffRemote">Lade...</div>
    </div>
</dialog>

<script>
    async function showDiff(moduleKey, file, targetUrl) {
        const modal = document.getElementById('diffModal');
        document.getElementById('diffTitle').textContent = moduleKey + ' / ' + file;
        document.getElementById('diffLocal').textContent = 'Lade...';
        document.getElementById('diffRemote').textContent = 'Lade...';
        modal.showModal();

        const localUrl = '<?= htmlspecialchars($localBase, ENT_QUOTES) ?>?module=' + encodeURIComponent(moduleKey) + '&file=' + encodeURIComponent(file);
        const remoteUrl = '<?= htmlspecialchars($remoteBase, ENT_QUOTES) ?>?module=' + encodeURIComponent(moduleKey) + '&file=' + encodeURIComponent(file);

        let localText = '(Fehler beim Laden)';
        let remoteText = '(Fehler beim Laden)';

        try { localText = await (await fetch(localUrl)).text(); } catch (e) {}
        try { remoteText = await (await fetch(remoteUrl)).text(); } catch (e) {}

        renderDiff(localText, remoteText);
    }

    // Einfaches zeilenbasiertes Diff (LCS), markiert nur entfernte/hinzugefuegte Zeilen
    function renderDiff(localText, remoteText) {
        const a = localText.split('\n');
        const b = remoteText.split('\n');

        const m = a.length, n = b.length;
        const lcs = Array.from({length: m + 1}, () => new Array(n + 1).fill(0));
        for (let i = m - 1; i >= 0; i--) {
            for (let j = n - 1; j >= 0; j--) {
                lcs[i][j] = a[i] === b[j] ? lcs[i+1][j+1] + 1 : Math.max(lcs[i+1][j], lcs[i][j+1]);
            }
        }

        let i = 0, j = 0;
        const localHtml = [];
        const remoteHtml = [];
        const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

        while (i < m && j < n) {
            if (a[i] === b[j]) {
                localHtml.push(esc(a[i]));
                remoteHtml.push(esc(b[j]));
                i++; j++;
            } else if (lcs[i+1][j] >= lcs[i][j+1]) {
                localHtml.push('<span class="diff-line-removed">' + esc(a[i]) + '</span>');
                i++;
            } else {
                remoteHtml.push('<span class="diff-line-added">' + esc(b[j]) + '</span>');
                j++;
            }
        }
        while (i < m) { localHtml.push('<span class="diff-line-removed">' + esc(a[i]) + '</span>'); i++; }
        while (j < n) { remoteHtml.push('<span class="diff-line-added">' + esc(b[j]) + '</span>'); j++; }

        document.getElementById('diffLocal').innerHTML = localHtml.join('\n');
        document.getElementById('diffRemote').innerHTML = remoteHtml.join('\n');
    }
</script>

<h1>Installations-Vergleich</h1>

<form method="post">
    <label>
        Ziel-URL (z.B. https://andere-installation.de/view/core/status/)
        <br>
        <input type="url" name="target_url" value="<?= htmlspecialchars($targetUrl) ?>" style="width: 100%;" required>
    </label>
    <br><br>
    <button type="submit">Vergleichen</button>
</form>

<hr>

<?php if ($result && !$result['ok']): ?>
    <div class="message"><?= htmlspecialchars($result['message']) ?></div>
<?php elseif ($result): ?>

    <?php
    $hasAnyDiff = false;
    foreach ($result['diff'] as $modDiff) {
        if (!empty($modDiff['database']) || !empty($modDiff['files']) || $modDiff['only_local'] || $modDiff['only_remote']) {
            $hasAnyDiff = true;
            break;
        }
    }
    ?>

    <?php if (!$hasAnyDiff): ?>
        <div class="message" style="background:#dfd;border-color:#9c9;">Keine Unterschiede gefunden - beide Installationen sind identisch.</div>
    <?php endif; ?>

    <?php foreach ($result['diff'] as $moduleKey => $modDiff): ?>
        <?php
        $hasDbDiff    = !empty($modDiff['database']);
        $hasFileDiff  = !empty($modDiff['files']);
        $isOnlyLocal  = $modDiff['only_local'];
        $isOnlyRemote = $modDiff['only_remote'];

        if (!$hasDbDiff && !$hasFileDiff && !$isOnlyLocal && !$isOnlyRemote) {
            continue; // identisch, nicht anzeigen
        }
        ?>
        <div class="module-block">
            <h2><?= htmlspecialchars($moduleKey) ?>
                <?php if ($isOnlyLocal): ?><span class="tag tag-local">nur lokal vorhanden</span><?php endif; ?>
                <?php if ($isOnlyRemote): ?><span class="tag tag-remote">nur remote vorhanden</span><?php endif; ?>
            </h2>

            <?php if ($hasDbDiff): ?>
                <h3>Datenbank</h3>
                <?php foreach ($modDiff['database'] as $table => $info): ?>
                    <p>
                        <strong><?= htmlspecialchars($table) ?></strong>
                        <span class="tag tag-diff"><?= htmlspecialchars($info['status']) ?></span>
                    </p>
                    <?php if (!empty($info['columns'])): ?>
                        <table>
                            <tr><th>Spalte</th><th>Lokal</th><th>Remote</th></tr>
                            <?php foreach ($info['columns'] as $col => $vals): ?>
                                <tr>
                                    <td><?= htmlspecialchars($col) ?></td>
                                    <td><?= htmlspecialchars($vals['local'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($vals['remote'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($hasFileDiff): ?>
                <h3>Dateien</h3>
                <ul>
                    <?php foreach ($modDiff['files'] as $file => $fstatus): ?>
                        <li>
                            <?= htmlspecialchars($file) ?> - <span class="tag tag-diff"><?= htmlspecialchars($fstatus) ?></span>
                            <?php if ($fstatus === 'unterschiedlich'): ?>
                                <button type="button" onclick="showDiff('<?= htmlspecialchars($moduleKey, ENT_QUOTES) ?>', '<?= htmlspecialchars($file, ENT_QUOTES) ?>', '<?= htmlspecialchars($targetUrl, ENT_QUOTES) ?>')">Diff anzeigen</button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

</body>
</html>