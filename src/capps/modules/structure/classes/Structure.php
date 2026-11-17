<?php

declare(strict_types=1);

namespace capps\modules\structure\classes;

use capps\modules\database\classes\CBObject;
use capps\modules\database\classes\CBDatabase;


class Structure extends CBObject
{

    public function __construct(
        mixed $id = null,
        ?array $arrDB_Data = null,
        array $config = []
    ) {
        // Call parent constructor with structure settings
        parent::__construct(
            $id,                    // ID to load
            'capps_structure',          // Table name
            'structure_id',            // Primary key
            $arrDB_Data,            // Database config
            $config                 // Additional config
        );
    }




    function sortStructureWithSorting(array $items): array {
        $sortedItems = array();

        // Arrays for structure and parent ids
        $arrStructureById = array();
        $arrParentIDs = array();

        // Build structure and parent ids
        foreach ($items as $item) {
            $arrStructureById[$item["structure_id"]] = $item;
            $arrParentIDs[$item["parent_id"]][] = $item["structure_id"];
        }

        // Structure as comma list
        $strStructure = "";
        foreach ($arrParentIDs as $parent_id => $children) {
            $insert = "," . $parent_id . "," . implode(",", $children) . ",";
            if (stristr($strStructure, "," . $parent_id . ",")) {
                $strStructure = str_replace(",$parent_id,", $insert, $strStructure);
            } else {
                $strStructure .= $insert;
            }
        }
        $strStructure = trim($strStructure, ",");

        // Structure list to array
        $arrStructure = array();
        if ( $strStructure != "" ) $arrStructure = explode(",", $strStructure);

        // Map of each item with level and path
        $itemPaths = array();

        // Init paths and levels
        foreach ($arrStructure as $item) {
            if ($item == "0") continue; // skip root

            if (!isset($arrStructureById[$item])) continue; // parent not in this language/deleted
            $currentItem = $arrStructureById[$item];
            $parentId = $currentItem['parent_id'];
            $path = array();
            $level = 0;

            // Build the path of the item by walking up the parent ids
            while ($parentId != 0 && $level < 50) { // limit: parent cycle in the data must not hang the site
                if (isset($arrStructureById[$parentId])) {
                    array_unshift($path, $parentId); // prepend parent
                    $parentId = $arrStructureById[$parentId]['parent_id']; // next parent
                    $level++;
                } else {
                    break; // parent missing: stop
                }
            }

            // Add item with path and level to the map
            $currentItem['path'] = $path;
            $currentItem['level'] = $level;
            $itemPaths[$item] = $currentItem;
        }
        //CBLog($itemPaths);

        // Result in structure order
        
        foreach ($arrStructure as $item) {
            if ($item == "0") continue; // skip root
            if (isset($itemPaths[$item])) $sortedItems[$item] = $itemPaths[$item];
        }

        // Sorted items with path and level
        return $sortedItems;
    }





    /**
     * @param $language_id
     * @return array
     */
    function generateSortedStructure($language_id=""): array
    {
        $arrCondition = array();
        $arrCondition["language_id"] = $_SESSION[PLATFORM_IDENTIFIER]["plattform_language_id"]??"1";
        if ( $language_id != "" ) $arrCondition["language_id"] = $language_id;

        $arrIDs = $this->getAllEntries("parent_id|sorting","ASC|ASC",$arrCondition,NULL,"*");

        //
        if ( is_array($arrIDs) ) return $this->sortStructureWithSorting($arrIDs);

        // fallback
        return array();
    }


}
