<?php

use Capps\Modules\Installer\Classes\CBExport;

$exporter = new CBExport();

$vendors       = $exporter->getEnabledVendors();
$allTables     = $exporter->getAllTables();
$structureRows = $exporter->getAllStructureRows();

$message    = '';
$cmsMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Tabellen-Export ---
    if (isset($_POST['action']) && $_POST['action'] === 'export_tables') {
        $vendorKey  = $_POST['vendor'] ?? '';
        $moduleName = trim($_POST['module_name'] ?? '');
        $tables     = $_POST['tables'] ?? [];

        if ($vendorKey && $moduleName && $tables) {
            $result  = $exporter->exportTables($vendorKey, $moduleName, $tables);
            $message = $result['message'];
        } else {
            $message = 'Bitte Vendor, Modulname und mindestens eine Tabelle waehlen.';
        }
    }

    // --- CMS-Export ---
    if (isset($_POST['action']) && $_POST['action'] === 'export_cms') {
        $vendorKeyCms  = $_POST['vendor_cms'] ?? '';
        $moduleNameCms = trim($_POST['module_name_cms'] ?? '');
        $rootId        = (int)($_POST['root_structure_id'] ?? 0);

        if ($vendorKeyCms && $moduleNameCms && $rootId) {
            $result     = $exporter->exportCmsTree($vendorKeyCms, $moduleNameCms, $rootId);
            $cmsMessage = $result['message'];
        } else {
            $cmsMessage = 'Bitte Vendor, Modulname und eine Root-Seite waehlen.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Modul-Export</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 2rem auto; }
        fieldset { margin-bottom: 1rem; }
        .message { padding: 0.5rem 1rem; background: #eef; border: 1px solid #99c; margin-bottom: 1rem; }
        label { display: block; margin: 0.25rem 0; }
        hr { margin: 3rem 0; }
    </style>
</head>
<body>

<h1>Modul-Export</h1>

<?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="action" value="export_tables">

    <fieldset>
        <legend>Vendor</legend>
        <select name="vendor" required>
            <option value="">-- waehlen --</option>
            <?php foreach ($vendors as $key => $cfg): ?>
                <option value="<?= htmlspecialchars($key) ?>">
                    <?= htmlspecialchars($key) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </fieldset>

    <fieldset>
        <legend>Modulname</legend>
        <input type="text" name="module_name" placeholder="z.B. address" required>
    </fieldset>

    <fieldset>
        <legend>Tabellen</legend>
        <?php foreach ($allTables as $table): ?>
            <label>
                <input type="checkbox" name="tables[]" value="<?= htmlspecialchars($table) ?>">
                <?= htmlspecialchars($table) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <button type="submit">Tabellen exportieren</button>
</form>

<hr>

<h2>CMS-Export (Seiten + Elemente)</h2>

<?php if ($cmsMessage): ?>
    <div class="message"><?= htmlspecialchars($cmsMessage) ?></div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="action" value="export_cms">

    <fieldset>
        <legend>Vendor</legend>
        <select name="vendor_cms" required>
            <option value="">-- waehlen --</option>
            <?php foreach ($vendors as $key => $cfg): ?>
                <option value="<?= htmlspecialchars($key) ?>">
                    <?= htmlspecialchars($key) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </fieldset>

    <fieldset>
        <legend>Modulname</legend>
        <input type="text" name="module_name_cms" placeholder="z.B. push" required>
    </fieldset>

    <fieldset>
        <legend>Root-Seite (inkl. aller Kindseiten wird exportiert)</legend>
        <select name="root_structure_id" required>
            <option value="">-- waehlen --</option>
            <?php foreach ($structureRows as $row): ?>
                <option value="<?= (int)$row['structure_id'] ?>">
                    #<?= (int)$row['structure_id'] ?> - <?= htmlspecialchars($row['name'] ?? '') ?>
                    (parent: <?= (int)$row['parent_id'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </fieldset>

    <button type="submit">CMS-Baum exportieren</button>
</form>

</body>
</html>
