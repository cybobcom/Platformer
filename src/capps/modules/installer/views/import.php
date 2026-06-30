<?php

use Capps\Modules\Installer\Classes\CBImport;

$importer = new CBImport();
$modules  = $importer->scanModules();

$schemaLog  = [];
$cmsMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Schema anwenden ---
    if (($_POST['action'] ?? '') === 'apply_schema') {
        $installPath = $_POST['install_path'] ?? '';
        $modifySelection = $_POST['modify'] ?? []; // ['table' => ['col1','col2']]

        if ($installPath && is_dir($installPath)) {
            $schemaLog = $importer->applySchema($installPath, $modifySelection);
        }
    }

    // --- CMS importieren ---
    if (($_POST['action'] ?? '') === 'apply_cms') {
        $installPath = $_POST['install_path'] ?? '';
        $targetParentId = (int)($_POST['target_parent_id'] ?? 0);

        if ($installPath && is_dir($installPath)) {
            $status = $importer->checkCmsStatus($installPath);
            if (!empty($status['installed'])) {
                $cmsMessage = 'Modul bereits installiert (Root-Template gefunden), kein Import noetig.';
            } else {
                $result = $importer->applyCms($installPath, $targetParentId);
                $cmsMessage = $result['message'];
            }
        }
    }
}

$structureRows = $importer->getAllStructureRows();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Modul-Update</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 2rem auto; }
        fieldset { margin-bottom: 1.5rem; }
        .message { padding: 0.5rem 1rem; background: #eef; border: 1px solid #99c; margin-bottom: 1rem; }
        .module-block { border: 1px solid #ccc; padding: 1rem; margin-bottom: 1.5rem; }
        .tag { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 3px; font-size: 0.85em; margin-left: 0.5rem; }
        .tag-new { background: #cfc; }
        .tag-changed { background: #ffc; }
        .tag-ok { background: #eee; }
        table { border-collapse: collapse; width: 100%; margin: 0.5rem 0; }
        th, td { border: 1px solid #ddd; padding: 0.3rem 0.5rem; text-align: left; font-size: 0.9em; }
        ul { margin: 0.3rem 0; }
    </style>
</head>
<body>

<h1>Modul-Update</h1>

<?php if ($schemaLog): ?>
    <div class="message">
        <strong>Schema-Ergebnis:</strong>
        <ul><?php foreach ($schemaLog as $line): ?><li><?= htmlspecialchars($line) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($cmsMessage): ?>
    <div class="message"><strong>CMS-Ergebnis:</strong> <?= htmlspecialchars($cmsMessage) ?></div>
<?php endif; ?>

<?php foreach ($modules as $mod): ?>
    <div class="module-block">
        <h2><?= htmlspecialchars($mod['key']) ?></h2>

        <?php if ($mod['has_schema']): ?>
            <?php $diff = $importer->diffSchema($mod['install_path']); ?>
            <h3>Schema</h3>
            <form method="post">
                <input type="hidden" name="action" value="apply_schema">
                <input type="hidden" name="install_path" value="<?= htmlspecialchars($mod['install_path']) ?>">

                <?php foreach ($diff as $table => $info): ?>
                    <p>
                        <strong><?= htmlspecialchars($table) ?></strong>
                        <?php if ($info['missing']): ?>
                            <span class="tag tag-new">NEU - wird angelegt</span>
                        <?php elseif ($info['add'] || $info['modify']): ?>
                            <span class="tag tag-changed">geaendert</span>
                        <?php else: ?>
                            <span class="tag tag-ok">unveraendert</span>
                        <?php endif; ?>
                    </p>

                    <?php if (!$info['missing'] && $info['add']): ?>
                        <p>Wird automatisch hinzugefuegt:</p>
                        <ul>
                            <?php foreach ($info['add'] as $col => $def): ?>
                                <li><?= htmlspecialchars($col) ?> (<?= htmlspecialchars($def) ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!$info['missing'] && $info['modify']): ?>
                        <table>
                            <tr><th></th><th>Spalte</th><th>Aktuell</th><th>Neu</th></tr>
                            <?php foreach ($info['modify'] as $col => $vals): ?>
                                <tr>
                                    <td><input type="checkbox" name="modify[<?= htmlspecialchars($table) ?>][]" value="<?= htmlspecialchars($col) ?>"></td>
                                    <td><?= htmlspecialchars($col) ?></td>
                                    <td><?= htmlspecialchars($vals['old']) ?></td>
                                    <td><?= htmlspecialchars($vals['new']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>

                    <?php if (!$info['missing'] && $info['extra']): ?>
                        <p>Hinweis: Spalten in DB, nicht mehr im Schema (kein Auto-Drop): <?= htmlspecialchars(implode(', ', $info['extra'])) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>

                <button type="submit">Schema ausfuehren</button>
            </form>
        <?php endif; ?>

        <?php if ($mod['has_cms']): ?>
            <?php $cmsStatus = $importer->checkCmsStatus($mod['install_path']); ?>
            <h3>CMS</h3>
            <?php if (!empty($cmsStatus['installed'])): ?>
                <p><span class="tag tag-ok">bereits installiert</span> (Root-Seite "<?= htmlspecialchars($cmsStatus['root_name'] ?? '') ?>" gefunden)</p>
            <?php else: ?>
                <p><span class="tag tag-new">noch nicht installiert</span></p>
                <form method="post">
                    <input type="hidden" name="action" value="apply_cms">
                    <input type="hidden" name="install_path" value="<?= htmlspecialchars($mod['install_path']) ?>">
                    <label>
                        Ziel-Elternseite:
                        <select name="target_parent_id" required>
                            <option value="0">-- Root-Ebene (0) --</option>
                            <?php foreach ($structureRows as $row): ?>
                                <option value="<?= (int)$row['structure_id'] ?>">
                                    #<?= (int)$row['structure_id'] ?> - <?= htmlspecialchars($row['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">CMS importieren</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

</body>
</html>
