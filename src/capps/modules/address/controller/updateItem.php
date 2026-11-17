<?php

CBAuth(''); // logged-in users: own record only (admins: all)

//
$controller = CBinitController('Address');

//
$controller->validateRequired(['id']);

// SECURITY: non-admins may only edit their own record, without groups/active/type
$isAdmin = $controller->checkPermission('admin');
if (!$isAdmin && $_REQUEST['id'] !== ($_SESSION[PLATFORM_IDENTIFIER]['login_user_identifier'] ?? '')) {
    $controller->errorResponse('Permission denied', 403);
}

//
if (!$controller->load($_REQUEST['id'])) {
    $controller->errorResponse('Item not found', 404);
}

//
$arrSave = array();
$arrSave = is_array($_REQUEST['save'] ?? null) ? $_REQUEST['save'] : [];
if (!$isAdmin) {
    unset($arrSave['addressgroups'], $arrSave['active'], $arrSave['type'], $_REQUEST['addressgroups'], $_REQUEST['toggle']);
}
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