<?php

//
$objTmp = CBinitObject("Structure");
//CBLog($objTmp);

$arrCondition = array();
$arrCondition["language_id"] = $_SESSION[PLATFORM_IDENTIFIER]["plattform_language_id"]??"1";

$selection = "";
// search
$boolSearch = false;
if (isset($_REQUEST["search"]) && $_REQUEST["search"] != "" && $_REQUEST["search"] != "undefined") {
    $boolSearch = true;
    $selection .= " (  name LIKE '%" . $_REQUEST['search'] . "%' ) ";
}

//
$arrIDs = $objTmp->getAllEntries("parent_id|sorting","ASC|ASC",$arrCondition,$selection,"structure_id, parent_id, previous_id, sorting, name",NULL);
//echo "arrIDs<pre>"; print_r($arrIDs); echo "</pre>";exit;

// get sorting
if ( is_array($arrIDs) && !$boolSearch ) {
    $arrIDs = $objTmp->sortStructureWithSorting($arrIDs);
}
//CBLog($arrIDs);

//
$objRoute = CBinitObject("Route");


//
if (is_array($arrIDs) && count($arrIDs) >= 1) {
    foreach ($arrIDs as $run => $arrEntry) {
        //echo "arrEntry<pre>"; print_r($arrEntry); echo "</pre>";

        $objTmp = CBinitObject("Structure",$arrEntry[$objTmp->strPrimaryKey]);

        $arrEntry = array_merge($arrEntry,$objTmp->arrAttributes);

        $arrEntry["route"] = $objRoute->getStructureRoute($objTmp->getAttribute($objTmp->strPrimaryKey));

        if ( isset($arrEntry["level"]) ) $arrEntry["level"]  = $arrEntry["level"]."";

        $arrIDs[$run] = $arrEntry;

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