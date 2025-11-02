<?php
namespace capps\modules\route\classes;

class Route extends \capps\modules\database\classes\CBObject
{
    public function __construct(
        mixed $id = null,
        ?array $arrDB_Data = null,
        array $config = []
    ) {
        // Call parent constructor with agent-specific settings
        parent::__construct(
            $id,                    // ID to load
            'capps_route',          // Table name
            'route_id',            // Primary key
            $arrDB_Data,            // Database config
            $config                 // Additional config
        );
    }

    /**
     * @param string $structure_id
     * @return string
     */
    public function getStructureRoute(string $structure_id): string
    {

        $objStructure = CBinitObject("Structure", $structure_id);
        $arrSortedStructure = $objStructure->generateSortedStructure($objStructure->getAttribute("language_id"));

        $strRoute = "";
        // TODO : language path
        if (isset($arrSortedStructure[$structure_id])) {
            // path
            foreach ($arrSortedStructure[$structure_id]["path"] as $sid) {

                // get object
                $objStructureTmp = CBinitObject("Structure", $sid);

                // name
                $strName = $objStructureTmp->getAttribute("name");
                if ($objStructureTmp->getAttribute("data_seo_name") != "") $strName = $objStructureTmp->getAttribute("data_seo_name");

                if ($strName != "IGNORE") {
                    if ($strRoute != "") $strRoute .= "/";
                    $strRoute .= sanitizeFileName($strName);
                }
            }
            // self
            $strName = $objStructure->getAttribute("name");
            if ($objStructure->getAttribute("data_seo_name") != "") $strName = $objStructure->getAttribute("data_seo_name");

            if ($strName != "IGNORE") {
                if ($strRoute != "") $strRoute .= "/";
                $strRoute .= sanitizeFileName($strName);
            }
        }
        $strRoute .= "/";
        //CBLog($strRoute);

        return $strRoute;
    }

    public function generateStructureRoute(string $structure_id): string
    {
        //
        $strRoute = $this->getStructureRoute($structure_id);

        //
        $objStructure = CBinitObject("Structure", $structure_id);

        //
        $arrSave = array();
        $arrSave["language_id"] = $objStructure->getAttribute("language_id");
        $arrSave["structure_id"] = $objStructure->getAttribute("structure_id");
        $arrSave["route"] = $strRoute;

        //
        $arrConditions = array();
        $arrConditions["language_id"] = $objStructure->getAttribute("language_id");
        $arrConditions["structure_id"] = $objStructure->getAttribute("structure_id");
        $arrConditions["content_id"] = "NULL";
        $arrConditions["address_id"] = "NULL";

        //CBLog($arrSave);
        //CBLog($arrConditions);
        $objRoute = $this->save($arrSave,$arrConditions);
        //CBLog($this->getLastError());

        return $strRoute;
    }

    public function getObjectFromRoute($route)
    {
        $arrConditions = array();
        $arrConditions["route"] = "NOTHING";
        if ($route != "") $arrConditions["route"] = $route;
        $arrIDs = $this->getAllEntries(NULL, NULL, $arrConditions, NULL);

        if (is_array($arrIDs) && count($arrIDs) > 0 && $arrIDs[0][$this->strPrimaryKey] != "") {
            return new Route($arrIDs[0][$this->strPrimaryKey]);
        }

        return NULL;
    }

}
