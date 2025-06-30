<?php

declare(strict_types=1);

namespace capps\modules\structure\classes;

use capps\modules\database\classes\CBObject;
use capps\modules\database\classes\CBDatabase;
//use Psr\Log\LoggerInterface;
//use Psr\Log\NullLogger;
use Capps\Modules\Database\Classes\NullLogger;

class Structure extends CBObject
{

    public function __construct(
        mixed $id = null,
        ?array $arrDB_Data = null,
        ?LoggerInterface $logger = null,
        array $config = []
    ) {
        // Call parent constructor with agent-specific settings
        parent::__construct(
            $id,                    // ID to load
            'capps_structure',          // Table name
            'structure_id',            // Primary key
            $arrDB_Data,            // Database config
            $logger ?? new NullLogger(), // Logger
            $config                 // Additional config
        );
    }




    function sortStructure(array $items): array
    {
        // Schritt 1: Indexiere die Elemente nach ihrer structure_id
        $itemsById = [];
        foreach ($items as $item) {
            $itemsById[$item['structure_id']] = $item;
        }

        // Schritt 2: Erstelle eine Sortierung nach parent_id und previous_id
        $sortedItems = [];
        $unsortedItems = $items;

        while (count($unsortedItems) > 0) {
            foreach ($unsortedItems as $key => $item) {
                if ($item['previous_id'] == 0 || isset($sortedItems[$item['previous_id']])) {
                    $sortedItems[$item['structure_id']] = $item;
                    unset($unsortedItems[$key]);
                }
            }
        }

        // Schritt 3: Berechne Pfad und Level für jedes Element
        foreach ($sortedItems as &$item) {
            $path = [];
            $current = $item;
            while ($current['parent_id'] != 0) {
                $path[] = $current['parent_id'];
                $current = $itemsById[$current['parent_id']];
            }
            $item['path'] = array_reverse($path);  // Pfad umkehren, damit er von der Wurzel bis zum Parent führt
            $item['level'] = count($item['path']); // Level ist die Länge des Pfads
        }
        //CBLog($sortedItems);

        // without structure_id as key
        //return array_values($sortedItems);

        // with structure_id as key
        //CBLog($sortedItems);
        return $sortedItems;
    }


    function sortStructureWithSorting(array $items): array {
        $sortedItems = array();

        // Arrays für Struktur und Parent IDs
        $arrStructureById = array();
        $arrParentIDs = array();

        // Aufbauen von Struktur- und Parent-IDs
        foreach ($items as $item) {
            $arrStructureById[$item["structure_id"]] = $item;
            $arrParentIDs[$item["parent_id"]][] = $item["structure_id"];
        }

        // Variable für die Struktur
        $strStructure = "";
        foreach ($arrParentIDs as $parent_id => $children) {
            $insert = "," . $parent_id . "," . implode(",", $children) . ",";
            if (stristr($strStructure, "," . $parent_id . ",")) {
                $strStructure = str_replace(",$parent_id,", $insert, $strStructure);
            } else {
                $strStructure .= $insert;
            }
        }

        // Struktur in ein Array umwandeln
        $arrStructure = explode(",", trim($strStructure, ","));

        // Wir erstellen eine Map für jedes Element mit einem Level und einem Pfad
        $itemPaths = array();

        // Initialisieren von Pfaden und Leveln
        foreach ($arrStructure as $item) {
            if ($item == "0") continue; // Ignoriere Root-Elemente

            $currentItem = $arrStructureById[$item];
            $parentId = $currentItem['parent_id'];
            $path = array();
            $level = 0;

            // Generiere den Pfad für das aktuelle Element, indem wir die Parent-IDs durchsuchen
            while ($parentId != 0) {
                if (isset($arrStructureById[$parentId])) {
                    array_unshift($path, $parentId); // Eltern an den Anfang des Pfades setzen
                    $parentId = $arrStructureById[$parentId]['parent_id']; // Zum nächsten Elternteil wechseln
                    $level++;
                } else {
                    break; // Falls keine Eltern-ID vorhanden ist, breche ab
                }
            }

            // Füge das aktuelle Element mit Pfad und Level zu der Map hinzu
            $currentItem['path'] = $path;
            $currentItem['level'] = $level;
            $itemPaths[$item] = $currentItem;
        }
        //CBLog($itemPaths);

        // Ergebnisse nach den berechneten Pfaden und Leveln sortieren
        // Wir durchlaufen jedes Element und fügen es in das Ergebnis ein
        foreach ($arrStructure as $item) {
            if ($item == "0") continue; // Ignoriere Root-Elemente
            $sortedItems[$item] = $itemPaths[$item];
        }

        // Rückgabe der sortierten und erweiterten Daten
        return $sortedItems;
    }





    /**
     * @param $language_id
     * @return array
     */
    function generateSortedStructure($language_id=""): array
    {
        $arrCondition = array();
        $arrCondition["language_id"] = $_SESSION[PLATTFORM_IDENTIFIER]["plattform_language_id"]??"1";
        if ( $language_id != "" ) $arrCondition["language_id"] = $language_id;

        $arrIDs = $this->getAllEntries("parent_id|sorting","ASC|ASC",$arrCondition,NULL,"*");

        //
        if ( is_array($arrIDs) ) return $this->sortStructureWithSorting($arrIDs);

        // fallback
        return array();
    }


}
