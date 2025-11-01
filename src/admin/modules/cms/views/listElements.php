<?php

//
$objTmp = CBinitObject("Content");
//CBLog($objTmp);

$arrCondition = array();
$arrCondition["language_id"] = $_SESSION[PLATFORM_IDENTIFIER]["plattform_language_id"]??"1";
$arrCondition["structure_id"] = "NEVER";
if (isset($_REQUEST["structure_id"]) && $_REQUEST["structure_id"] != "" && $_REQUEST["structure_id"] != "undefined") {
    $arrCondition["structure_id"] = $_REQUEST["structure_id"];
}

$selection = "";
// search
$boolSearch = false;
if (isset($_REQUEST["search"]) && $_REQUEST["search"] != "" && $_REQUEST["search"] != "undefined") {
    $boolSearch = true;
    $selection .= " (  name LIKE '%" . $_REQUEST['search'] . "%' ) ";
}

//
$arrIDs = $objTmp->getAllEntries("sorting","ASC",$arrCondition,$selection,"content_id, structure_id, previous_id, name",NULL);
//echo "arrIDs<pre>"; print_r($arrIDs); echo "</pre>";exit;

// get sorting
if ( is_array($arrIDs) && !$boolSearch ) {
   // $arrIDs = $objTmp->sortStructure($arrIDs);
}
//CBLog($arrIDs);

//
$objRoute = CBinitObject("Route");


//
if (is_array($arrIDs) && count($arrIDs) >= 1) {
    foreach ($arrIDs as $run => $arrEntry) {
        //echo "arrEntry<pre>"; print_r($arrEntry); echo "</pre>";

        $objTmp = CBinitObject("Content",$arrEntry[$objTmp->strPrimaryKey]);

        //$arrEntry = array_merge($arrEntry,$objTmp->arrAttributes);

        //$arrEntry["route"] = $objRoute->getStructureRoute($objTmp->getAttribute($objTmp->strPrimaryKey));

        //if ( isset($arrEntry["level"]) ) $arrEntry["level"]  = $arrEntry["level"]."";

        //$arrIDs[$run] = $arrEntry;
        $arrIDs[$run] = $objTmp->arrAttributes;

    }

}
//CBLog($arrIDs);

// important to preserve array order in json
$arrIDs = array_values($arrIDs);

//
$output = json_encode($arrIDs, JSON_HEX_APOS|JSON_PRETTY_PRINT);

header('Content-Type: application/json');
echo $output;


?>