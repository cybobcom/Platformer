<?php
// views/cms/sortElements.php

header('Content-Type: application/json');

$arrResponse = array();
$arrResponse["success"] = false;

// POST Daten holen
$postData = file_get_contents('php://input');
$arrData = json_decode($postData, true);

if (!isset($arrData["ids"]) || !is_array($arrData["ids"])) {
    echo json_encode($arrResponse);
    exit;
}
//mail("robert.heuer@cybob.com","D",print_r($arrData,true));

// Sorting durchnummerieren und speichern
foreach ($arrData["ids"] as $index => $content_id) {

    $objContentTmp = CBinitObject("Content", $content_id);

    $arrSave = [];
    $arrSave["sorting"] = $index + 1;
    $objContentTmp->saveContentUpdate($content_id,$arrSave);

}

$arrResponse["success"] = true;
echo json_encode($arrResponse);

?>