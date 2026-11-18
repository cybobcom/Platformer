<?php

$objStructure = CBinitObject("Structure");
$objRoute = CBinitObject("Route");

if (isset($_REQUEST['id']) && $_REQUEST['id'] != "") {

    $objStructure = CBinitObject("Structure", $_REQUEST["id"]);

    $objStructure->arrAttributes["route"] = $objRoute->getStructureRoute($objStructure->getAttribute($objStructure->strPrimaryKey));

}


// important to preserve array order in json
//$arrIDs = array_values($objStructure->arrAttributes);

//
$output = json_encode($objStructure->arrAttributes, JSON_HEX_APOS|JSON_PRETTY_PRINT);

header('Content-Type: application/json');
echo $output;


?>