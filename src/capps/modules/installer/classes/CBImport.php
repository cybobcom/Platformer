<?php

declare(strict_types=1);

namespace Capps\Modules\Installer\Classes;

use Capps\Modules\Database\Classes\CBDatabase;

/**
 * CBImport - Modul-Update-Mechanik
 *
 * Liest install/schema.php + install/schema.sql + install/cms.php aus allen
 * Modulen (ueber alle Vendor-Pfade) und gleicht sie live gegen die aktuelle
 * Datenbank ab. Kein persistentes Hash-Tracking - alles wird zur Laufzeit
 * verglichen (information_schema fuer Tabellen, template-Match fuer CMS).
 */
class CBImport
{
    private CBDatabase $db;

    public function __construct()
    {
        $this->db = new CBDatabase();
    }

    private function getEnabledVendors(): array
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

    /**
     * Scannt alle Vendor-Pfade nach Modulen mit install/-Verzeichnis
     */
    public function scanModules(): array
    {
        $modules = [];

        foreach ($this->getEnabledVendors() as $vendorKey => $cfg) {
            $modulesDir = rtrim($cfg['path'], '/') . '/modules/';
            if (!is_dir($modulesDir)) {
                continue;
            }

            foreach (scandir($modulesDir) as $moduleName) {
                if ($moduleName === '.' || $moduleName === '..') {
                    continue;
                }

                $installPath = $modulesDir . $moduleName . '/install/';
                if (!is_dir($installPath)) {
                    continue;
                }

                $modules[] = [
                    'key'         => $vendorKey . '/' . $moduleName,
                    'vendor'      => $vendorKey,
                    'module'      => $moduleName,
                    'install_path' => $installPath,
                    'has_schema'  => file_exists($installPath . 'schema.php'),
                    'has_cms'     => file_exists($installPath . 'cms.php'),
                ];
            }
        }

        return $modules;
    }

    // ================================================================
    // SCHEMA DIFF / APPLY
    // ================================================================

    /**
     * Vergleicht install/schema.php gegen die aktuelle DB.
     * Rueckgabe pro Tabelle: missing (Tabelle existiert nicht),
     * add (fehlende Spalten), modify (geaenderte Spalten, Soll/Ist),
     * extra (Spalten in DB, nicht im Schema - nur Hinweis)
     */
    public function diffSchema(string $installPath): array
    {
        $schemaFile = $installPath . 'schema.php';
        if (!file_exists($schemaFile)) {
            return [];
        }

        $targetSchema = include $schemaFile; // ['table' => ['col' => 'TYPE NOT NULL', ...]]
        $result = [];

        foreach ($targetSchema as $table => $targetColumns) {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

            $existing = $this->db->get(
                'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA
                 FROM information_schema.columns
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );

            if (empty($existing)) {
                $result[$table] = ['missing' => true, 'add' => [], 'modify' => [], 'extra' => []];
                continue;
            }

            $actualColumns = [];
            foreach ($existing as $col) {
                $actualColumns[$col['COLUMN_NAME']] = $col['COLUMN_TYPE']
                    . ($col['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : '')
                    . ($col['EXTRA'] ? ' ' . $col['EXTRA'] : '');
            }

            $add = [];
            $modify = [];
            foreach ($targetColumns as $col => $targetDef) {
                if (!isset($actualColumns[$col])) {
                    $add[$col] = $targetDef;
                } elseif (trim((string)$actualColumns[$col]) !== trim((string)$targetDef)) {
                    $modify[$col] = ['old' => $actualColumns[$col], 'new' => $targetDef];
                }
            }

            $extra = array_diff(array_keys($actualColumns), array_keys($targetColumns));

            $result[$table] = [
                'missing' => false,
                'add'     => $add,
                'modify'  => $modify,
                'extra'   => $extra,
            ];
        }

        return $result;
    }

    /**
     * Fuehrt CREATE TABLE (fuer fehlende Tabellen) + ADD COLUMN (automatisch) +
     * MODIFY COLUMN (nur fuer explizit ausgewaehlte Spalten) aus.
     *
     * @param array $modifySelection ['table' => ['col1', 'col2', ...]] - welche MODIFY ausgefuehrt werden sollen
     */
    public function applySchema(string $installPath, array $modifySelection = []): array
    {
        $diff = $this->diffSchema($installPath);
        $log = [];

        // CREATE TABLE fuer fehlende Tabellen aus schema.sql
        $missingTables = array_keys(array_filter($diff, fn($d) => $d['missing']));
        if ($missingTables) {
            $sqlFile = $installPath . 'schema.sql';
            $sqlContent = file_exists($sqlFile) ? file_get_contents($sqlFile) : '';

            foreach ($missingTables as $table) {
                if (preg_match('/-- Table: ' . preg_quote($table, '/') . '\n(.*?);\s*(?=-- Table:|$)/s', $sqlContent, $m)) {
                    $createSql = str_ireplace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $m[1]);
                    $this->db->query($createSql);
                    $log[] = "Tabelle '{$table}' angelegt.";
                } else {
                    $log[] = "FEHLER: CREATE-Statement fuer '{$table}' nicht in schema.sql gefunden.";
                }
            }
        }

        // ADD COLUMN automatisch fuer existierende Tabellen
        foreach ($diff as $table => $info) {
            if ($info['missing']) {
                continue; // wurde gerade erst angelegt, keine ADD COLUMN noetig
            }
            foreach ($info['add'] as $col => $def) {
                $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
                $log[] = "Spalte '{$table}.{$col}' hinzugefuegt.";
            }
        }

        // MODIFY COLUMN nur fuer ausgewaehlte
        foreach ($modifySelection as $table => $columns) {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            if (!isset($diff[$table])) {
                continue;
            }
            foreach ($columns as $col) {
                if (!isset($diff[$table]['modify'][$col])) {
                    continue;
                }
                $newDef = $diff[$table]['modify'][$col]['new'];
                $this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `{$col}` {$newDef}");
                $log[] = "Spalte '{$table}.{$col}' geaendert.";
            }
        }

        if ($this->db->getLastError()) {
            $log[] = 'DB-Fehler: ' . $this->db->getLastError();
        }

        return $log;
    }

    // ================================================================
    // CMS IMPORT
    // ================================================================

    /**
     * Prueft, ob die Root-Seite des Moduls bereits installiert ist (Template-Match)
     */
    public function checkCmsStatus(string $installPath): array
    {
        $cmsFile = $installPath . 'cms.php';
        if (!file_exists($cmsFile)) {
            return ['has_cms' => false];
        }

        $cms = include $cmsFile;
        $rootLocalId = array_key_first($cms['structure'] ?? []);
        if ($rootLocalId === null) {
            return ['has_cms' => true, 'installed' => false, 'reason' => 'Keine Root-Seite im Export.'];
        }

        $root = $cms['structure'][$rootLocalId];
        $template = $root['template'] ?? '';

        if ($template === '') {
            return ['has_cms' => true, 'installed' => false, 'reason' => 'Root-Seite hat kein Template, kann nicht geprueft werden.'];
        }

        $existing = $this->db->selectOne(
            'SELECT structure_id FROM capps_structure WHERE template = ? LIMIT 1',
            [$template]
        );

        return [
            'has_cms'   => true,
            'installed' => $existing !== null,
            'root_name' => $root['name'] ?? '',
            'template'  => $template,
        ];
    }

    /**
     * Importiert den kompletten CMS-Baum (Structure + Content) eines Moduls.
     * Wird nur aufgerufen, wenn checkCmsStatus() 'installed' => false meldet.
     *
     * @param int $targetParentId Wohin die Root-Seite gehaengt werden soll
     */
    public function applyCms(string $installPath, int $targetParentId): array
    {
        $cmsFile = $installPath . 'cms.php';
        if (!file_exists($cmsFile)) {
            return ['ok' => false, 'message' => 'cms.php nicht gefunden.'];
        }

        $cms = include $cmsFile;
        $structure = $cms['structure'] ?? [];
        $content = $cms['content'] ?? [];

        // Structure-Knoten in Reihenfolge der lokalen IDs einfuegen (Parent vor Kind, da Export per BFS sortiert hat)
        $idMap = []; // lokale ID => neue echte ID
        ksort($structure);

        foreach ($structure as $localId => $row) {
            $parentLocalId = (int)$row['parent_id'];
            $row['parent_id'] = $parentLocalId === 0
                ? $targetParentId
                : ($idMap[$parentLocalId] ?? $targetParentId);

            $newId = $this->db->insert('capps_structure', $row);
            if ($newId === false) {
                return ['ok' => false, 'message' => 'Fehler beim Anlegen der Seite "' . ($row['name'] ?? '') . '": ' . $this->db->getLastError()];
            }
            $idMap[$localId] = $newId;
        }

        // Content-Eintraege mit gemappter structure_id einfuegen
        $contentCount = 0;
        foreach ($content as $row) {
            $localStructureId = (int)$row['structure_id'];
            $row['structure_id'] = $idMap[$localStructureId] ?? null;

            if ($row['structure_id'] === null) {
                continue; // Sicherheitsnetz, sollte nicht vorkommen
            }

            $newId = $this->db->insert('capps_content', $row);
            if ($newId === false) {
                return ['ok' => false, 'message' => 'Fehler beim Anlegen eines Content-Eintrags: ' . $this->db->getLastError()];
            }
            $contentCount++;
        }

        return [
            'ok'      => true,
            'message' => count($structure) . ' Seite(n) und ' . $contentCount . ' Element(e) importiert.',
        ];
    }

    /**
     * Liefert alle Structure-Eintraege (fuer Ziel-Parent-Auswahl in der UI)
     */
    public function getAllStructureRows(): array
    {
        return $this->db->get(
            'SELECT structure_id, parent_id, name FROM capps_structure ORDER BY parent_id, sorting'
        );
    }
}
