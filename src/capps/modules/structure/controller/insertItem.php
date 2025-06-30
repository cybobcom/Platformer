<?php

//echo "<pre>"; print_r($_REQUEST); echo "</pre>";

//
$strModuleName = CBgetModuleName(__FILE__);
//CBLog($strModuleName);

//
$objTmp = CBinitObject("Structure");
//CBLog($objTmp);

$dictResponse = array();
$dictResponse["response"] = "error";
$dictResponse["description"] = "something went wrong";

//
if (is_array($_REQUEST['save'])) {

    //
    $objTmp = CBinitObject("Structure");
    //$objTmp = new \capps\modules\database\classes\CBObject(NULL, "capps_address", "address_id");


    //
    $arrSave = array();
    $arrSave = $_REQUEST['save'];
    $arrSave['date_created'] = date("Y-m-d H:i:s");

    //
    $intID = $objTmp->saveContentNew($arrSave);

    //
    //$objTmp = CBinitObject(ucfirst($strModuleName),$intID);

    //
    $dictResponse["response"] = "success";
    $dictResponse["description"] = "ok";
    $dictResponse["id"] = $intID;

    //
    // route
    //
    $objRoute = CBinitObject("Route");
    $objRoute->generateStructureRoute($intID);

}


$output = json_encode($dictResponse, JSON_HEX_APOS);

header('Content-Type: application/json');
echo $output;

?>