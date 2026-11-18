<?php
// controller/cms/sortPages.php

header('Content-Type: application/json');

$arrResponse = array();
$arrResponse["success"] = false;

// POST Daten holen
$postData = file_get_contents('php://input');
$arrData = json_decode($postData, true);

if (!isset($arrData["pages"]) || !is_array($arrData["pages"])) {
    echo json_encode($arrResponse);
    exit;
}

//mail("robert.heuer@cybob.com","D",print_r($arrData,true));exit;

// Jede Seite mit parent_id, level und sorting speichern
foreach ($arrData["pages"] as $page) {

    $objStructureTmp = CBinitObject("Structure", $page["structure_id"]);

    $arrSave = array();
    $arrSave["parent_id"] = $page["parent_id"];
    $arrSave["level"] = $page["level"];
    $arrSave["sorting"] = $page["sorting"];

    $objStructureTmp->saveContentUpdate($page["structure_id"], $arrSave);
}

$arrResponse["success"] = true;
echo json_encode($arrResponse);

?>