<?php

//
$controller = CBinitController('Address');

//
$controller->validateRequired(['id','save']);

//
if (!$controller->load($_REQUEST['id'])) {
    $controller->errorResponse('Item not found', 404);
}

//
$arrSave = array();
$arrSave = $_REQUEST['save'];
$arrSave['date_updated'] = date("Y-m-d H:i:s");

//
// addressgroups
//
if ( isset($_REQUEST['addressgroups'])  && is_array($_REQUEST['addressgroups'])  && count($_REQUEST['addressgroups']) >= 1 ){

    $strGroups = "";
    foreach ( $_REQUEST['addressgroups'] as $identifier=>$checked ) {

        if ( $identifier == '0' ) continue;

        if ( $checked == "0" ) {
            //
        } else {
            if ( $strGroups != "" ) $strGroups .= ",";
            $strGroups .= $identifier;
        }

        $arrSave["addressgroups"] = $strGroups;

    }

}

//
// toggle
//
if ( isset($_REQUEST['toggle'])  && is_array($_REQUEST['toggle'])  && count($_REQUEST['toggle']) >= 1 ) {

    foreach ($_REQUEST['toggle'] as $identifier => $value) {

        $v1 = $value;
        $v2 = "";
        if ( stristr($value,",") ) {
            $arrTmp = explode(",",$value);
            $v1 = $arrTmp[0];
            $v2 = $arrTmp[1];
        }

        if ( $controller->getAttribute($identifier) == "" || $controller->getAttribute($identifier) != $v1 ) {
            $arrSave[$identifier] = $v1;
        } else {
            $arrSave[$identifier] = $v2;
        }
    }

}

//
$res = $controller->saveContentUpdate($_REQUEST['id'],$arrSave);

//
$controller->successResponse(['id' => $_REQUEST['id']]);

?>