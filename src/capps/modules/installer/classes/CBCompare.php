<?php

declare(strict_types=1);

namespace Capps\Modules\Installer\Classes;

/**
 * CBCompare - vergleicht den lokalen Snapshot (CBStatus) mit dem
 * Snapshot einer anderen Installation (per URL abgerufen).
 */
class CBCompare
{
    /**
     * Holt das JSON von der Ziel-URL und vergleicht es mit dem lokalen Snapshot
     */
    public function compareWithUrl(string $targetUrl): array
    {
        $local = (new CBStatus())->generateSnapshot();

        $remoteRaw = @file_get_contents($targetUrl);
        if ($remoteRaw === false) {
            return ['ok' => false, 'message' => 'Konnte Ziel-URL nicht laden: ' . $targetUrl];
        }

        $remote = json_decode($remoteRaw, true);
        if (!is_array($remote)) {
            return ['ok' => false, 'message' => 'Antwort der Ziel-URL ist kein gueltiges JSON.'];
        }

        $allKeys = array_unique(array_merge(array_keys($local), array_keys($remote)));
        $diff = [];

        foreach ($allKeys as $moduleKey) {
            $localModule  = $local[$moduleKey] ?? null;
            $remoteModule = $remote[$moduleKey] ?? null;

            $diff[$moduleKey] = [
                'only_local'  => $localModule !== null && $remoteModule === null,
                'only_remote' => $localModule === null && $remoteModule !== null,
                'database'    => $this->diffSection($localModule['database'] ?? [], $remoteModule['database'] ?? []),
                'files'       => $this->diffFiles($localModule['files'] ?? [], $remoteModule['files'] ?? []),
            ];
        }

        return ['ok' => true, 'diff' => $diff];
    }

    /**
     * Vergleicht Tabellen/Spalten-Struktur (local vs remote)
     */
    private function diffSection(array $local, array $remote): array
    {
        $result = [];
        $allTables = array_unique(array_merge(array_keys($local), array_keys($remote)));

        foreach ($allTables as $table) {
            $localCols  = $local[$table] ?? null;
            $remoteCols = $remote[$table] ?? null;

            if ($localCols === null) {
                $result[$table] = ['status' => 'nur_remote'];
                continue;
            }
            if ($remoteCols === null) {
                $result[$table] = ['status' => 'nur_local'];
                continue;
            }

            $colDiff = [];
            $allCols = array_unique(array_merge(array_keys($localCols), array_keys($remoteCols)));
            foreach ($allCols as $col) {
                $l = $localCols[$col] ?? null;
                $r = $remoteCols[$col] ?? null;
                if ($l !== $r) {
                    $colDiff[$col] = ['local' => $l, 'remote' => $r];
                }
            }

            if ($colDiff) {
                $result[$table] = ['status' => 'unterschiedlich', 'columns' => $colDiff];
            }
        }

        return $result;
    }

    /**
     * Vergleicht Datei-Hashes (nur Hash-Vergleich, kein Inhalts-Diff)
     */
    private function diffFiles(array $local, array $remote): array
    {
        $result = [];
        $allFiles = array_unique(array_merge(array_keys($local), array_keys($remote)));

        foreach ($allFiles as $file) {
            $l = $local[$file] ?? null;
            $r = $remote[$file] ?? null;

            if ($l === null) {
                $result[$file] = 'nur_remote';
            } elseif ($r === null) {
                $result[$file] = 'nur_local';
            } elseif ($l !== $r) {
                $result[$file] = 'unterschiedlich';
            }
            // identisch -> kein Eintrag
        }

        return $result;
    }
}
