<?php

declare(strict_types=1);

namespace Capps\Modules\Installer\Classes;

use Capps\Modules\Database\Classes\CBDatabase;

/**
 * Export-Logik fuer Modul-Tabellen.
 * Erzeugt install/schema.sql + install/schema.php fuer ein Modul.
 */
class CBExport
{
    private CBDatabase $db;

    public function __construct()
    {
        $this->db = new CBDatabase();
    }

    public function getEnabledVendors(): array
    {
        global $arrConf;

        $vendors = [];
        foreach ($arrConf['cbinit']['vendors'] as $key => $cfg) {
            if (!empty($cfg['enabled'])) {
                $vendors[$key] = $cfg;
            }
        }
        return $vendors;
    }

    public function getAllTables(): array
    {
        $rows = $this->db->show('SHOW TABLES');

        $tables = [];
        foreach ($rows as $row) {
            $tables[] = array_values($row)[0];
        }
        return $tables;
    }

    /**
     * Exportiert gewaehlte Tabellen nach {vendorPath}/modules/{modul}/install/
     */
    public function exportTables(string $vendorKey, string $moduleName, array $tableNames): array
    {
        $vendors = $this->getEnabledVendors();

        if (!isset($vendors[$vendorKey])) {
            return ['ok' => false, 'message' => 'Ungueltiger Vendor.'];
        }

        $moduleName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $moduleName);
        if ($moduleName === '') {
            return ['ok' => false, 'message' => 'Modulname fehlt oder ungueltig.'];
        }

        $installPath = rtrim($vendors[$vendorKey]['path'], '/')
            . '/modules/' . $moduleName . '/install/';

        if (!is_dir($installPath)) {
            mkdir($installPath, 0775, true);
        }

        $overwrite = file_exists($installPath . 'schema.sql') || file_exists($installPath . 'schema.php');

        $sqlParts  = [];
        $schemaArr = [];

        foreach ($tableNames as $table) {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table); // sanitize

            $createRows = $this->db->show('SHOW CREATE TABLE `' . $table . '`');
            if (!empty($createRows)) {
                $createSql = $createRows[0]['Create Table'] ?? '';
                $sqlParts[] = '-- Table: ' . $table . "\n" . $createSql . ";\n";
            }

            $columns = $this->db->get(
                'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA
                 FROM information_schema.columns
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION',
                [$table]
            );

            $cols = [];
            foreach ($columns as $col) {
                $cols[$col['COLUMN_NAME']] = $col['COLUMN_TYPE']
                    . ($col['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : '')
                    . ($col['EXTRA'] ? ' ' . $col['EXTRA'] : '');
            }
            $schemaArr[$table] = $cols;
        }

        if ($this->db->getLastError()) {
            return ['ok' => false, 'message' => 'DB-Fehler: ' . $this->db->getLastError()];
        }

        file_put_contents($installPath . 'schema.sql', implode("\n", $sqlParts));

        $phpExport = "<?php\nreturn " . var_export($schemaArr, true) . ";\n";
        file_put_contents($installPath . 'schema.php', $phpExport);

        return [
            'ok'      => true,
            'message' => ($overwrite ? 'Bestehende Dateien ueberschrieben. ' : 'Export erfolgreich. ')
                . 'Pfad: ' . $installPath,
        ];
    }

    // ================================================================
    // CMS EXPORT (capps_structure / capps_content)
    // ================================================================

    /**
     * Liefert alle Structure-Eintraege (fuer Root-Auswahl in der UI)
     */
    public function getAllStructureRows(): array
    {
        return $this->db->get(
            'SELECT structure_id, parent_id, name FROM capps_structure ORDER BY parent_id, sorting'
        );
    }

    /**
     * Exportiert eine Seite (structure_id) inkl. aller Kindseiten + zugehoeriger Content-Eintraege.
     * IDs werden auf lokale, installationsunabhaengige IDs umgemappt (previous_id ist deprecated, wird ignoriert).
     */
    public function exportCmsTree(string $vendorKey, string $moduleName, int $rootStructureId): array
    {
        $vendors = $this->getEnabledVendors();

        if (!isset($vendors[$vendorKey])) {
            return ['ok' => false, 'message' => 'Ungueltiger Vendor.'];
        }

        $moduleName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $moduleName);
        if ($moduleName === '') {
            return ['ok' => false, 'message' => 'Modulname fehlt oder ungueltig.'];
        }

        // 1) Alle Structure-Zeilen laden, Children-Index aufbauen
        $allRows = $this->db->get('SELECT * FROM capps_structure');

        $rowsById = [];
        $childrenByParent = [];
        foreach ($allRows as $row) {
            $rowsById[(int)$row['structure_id']] = $row;
            $childrenByParent[(int)$row['parent_id']][] = (int)$row['structure_id'];
        }

        if (!isset($rowsById[$rootStructureId])) {
            return ['ok' => false, 'message' => 'Root-Seite nicht gefunden.'];
        }

        // 2) Rekursiv Root + alle Kinder einsammeln (Reihenfolge = lokale ID Zuweisung)
        $collectedIds = [];
        $queue = [$rootStructureId];
        while ($queue) {
            $currentId = array_shift($queue);
            $collectedIds[] = $currentId;
            foreach ($childrenByParent[$currentId] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        // 3) Lokale IDs vergeben (1-basiert, Reihenfolge = Einsammel-Reihenfolge)
        $localIdMap = [];
        foreach ($collectedIds as $i => $realId) {
            $localIdMap[$realId] = $i + 1;
        }

        // 4) Structure-Export-Array bauen
        $structureExport = [];
        foreach ($collectedIds as $realId) {
            $row = $rowsById[$realId];
            unset($row['structure_id'], $row['previous_id']); // previous_id deprecated

            $parentId = (int)$row['parent_id'];
            $row['parent_id'] = $localIdMap[$parentId] ?? 0; // 0 = ausserhalb der Exportmenge

            $structureExport[$localIdMap[$realId]] = $row;
        }

        // 5) Zugehoerige Content-Eintraege laden
        $placeholders = implode(',', array_fill(0, count($collectedIds), '?'));
        $contentRows = $this->db->get(
            "SELECT * FROM capps_content WHERE structure_id IN ({$placeholders})",
            $collectedIds
        );

        $contentExport = [];
        foreach ($contentRows as $row) {
            unset($row['content_id'], $row['previous_id']); // content_id wird beim Import neu vergeben
            $row['structure_id'] = $localIdMap[(int)$row['structure_id']] ?? 0;
            $contentExport[] = $row;
        }

        if ($this->db->getLastError()) {
            return ['ok' => false, 'message' => 'DB-Fehler: ' . $this->db->getLastError()];
        }

        // 6) Schreiben
        $installPath = rtrim($vendors[$vendorKey]['path'], '/')
            . '/modules/' . $moduleName . '/install/';

        if (!is_dir($installPath)) {
            mkdir($installPath, 0775, true);
        }

        $overwrite = file_exists($installPath . 'cms.php');

        $cmsExport = [
            'structure' => $structureExport,
            'content'   => $contentExport,
        ];

        file_put_contents(
            $installPath . 'cms.php',
            "<?php\nreturn " . var_export($cmsExport, true) . ";\n"
        );

        return [
            'ok'      => true,
            'message' => ($overwrite ? 'Bestehende CMS-Dateien ueberschrieben. ' : 'CMS-Export erfolgreich. ')
                . count($structureExport) . ' Seite(n), ' . count($contentExport) . ' Element(e). '
                . 'Pfad: ' . $installPath,
        ];
    }
}
