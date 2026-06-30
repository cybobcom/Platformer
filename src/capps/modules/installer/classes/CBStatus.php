<?php

declare(strict_types=1);

namespace Capps\Modules\Installer\Classes;

use Capps\Modules\Database\Classes\CBDatabase;

/**
 * CBStatus - erzeugt einen Snapshot der aktuellen Installation
 * (DB-Struktur + Datei-Hashes) fuer alle Module mit install/-Verzeichnis.
 * Wird von CBCompare genutzt, um zwei Installationen zu vergleichen.
 */
class CBStatus
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
     * Baut den kompletten Snapshot
     */
    public function generateSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->getEnabledVendors() as $vendorKey => $cfg) {
            $modulesDir = rtrim($cfg['path'], '/') . '/modules/';
            if (!is_dir($modulesDir)) {
                continue;
            }

            foreach (scandir($modulesDir) as $moduleName) {
                if ($moduleName === '.' || $moduleName === '..') {
                    continue;
                }

                $modulePath = $modulesDir . $moduleName . '/';
                $installPath = $modulePath . 'install/';
                if (!is_dir($installPath)) {
                    continue;
                }

                $key = $vendorKey . '/' . $moduleName;

                $snapshot[$key] = [
                    'database' => $this->getDatabaseStructure($installPath),
                    'files'    => $this->getFileHashes($modulePath),
                ];
            }
        }

        return $snapshot;
    }

    /**
     * Liest die Tabellen aus schema.php und holt den LIVE-Ist-Zustand aus der DB
     */
    private function getDatabaseStructure(string $installPath): array
    {
        $schemaFile = $installPath . 'schema.php';
        if (!file_exists($schemaFile)) {
            return [];
        }

        $targetSchema = include $schemaFile;
        $result = [];

        foreach (array_keys($targetSchema) as $table) {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

            $columns = $this->db->get(
                'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA
                 FROM information_schema.columns
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION',
                [$table]
            );

            if (empty($columns)) {
                $result[$table] = null; // Tabelle existiert nicht (mehr)
                continue;
            }

            $cols = [];
            foreach ($columns as $col) {
                $cols[$col['COLUMN_NAME']] = $col['COLUMN_TYPE']
                    . ($col['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : '')
                    . ($col['EXTRA'] ? ' ' . $col['EXTRA'] : '');
            }
            $result[$table] = $cols;
        }

        return $result;
    }

    /**
     * Hasht alle Dateien unter classes/, controller/, views/ (relative Pfade als Key)
     */
    private function getFileHashes(string $modulePath): array
    {
        $hashes = [];
        $subdirs = ['classes', 'controller', 'views'];

        foreach ($subdirs as $subdir) {
            $dir = $modulePath . $subdir . '/';
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $relativePath = $subdir . '/' . ltrim(str_replace($dir, '', $file->getPathname()), '/');
                $hashes[$relativePath] = md5_file($file->getPathname());
            }
        }

        return $hashes;
    }
}
