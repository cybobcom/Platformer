<?php


if ( is_array($_REQUEST['save']) ) {

    // DEV
    //echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

    //
    $path = str_replace(BASEDIR, "/", $_REQUEST["current_dir"]);

    $objTmp = CBinitObject('MediaLibrary');

    $arrSave = array();
    $arrSave['type'] = "directory";
    $arrSave['name'] = sanitizeFileName($_REQUEST["save"]["name"]);
    $arrSave['path'] = $path;
    $arrSave['title'] = $_REQUEST["save"]["name"];
    $arrSave['date_created'] = date('Y-m-d H:i:s');
    $arrSave['address_uid'] = $_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"];

    //
    //$arrSave['media_uid'] = create_guid();

    //echo "<pre>"; print_r($arrSave); echo "</pre>";exit;

    $intID = $objTmp->saveContentNew($arrSave);
    //echo $intID;exit;
    //echo $objTmp->getLastError();exit;

    $objTmp = CBinitObject('MediaLibrary',$intID);
    //CBLog($objTmp);exit;

    //
    $path = $_REQUEST["current_dir"].sanitizeFileName($_REQUEST["save"]["name"]);
    //echo "<pre>"; print_r($path); echo "</pre>";exit;

    if (!file_exists($path)) {

        if ( @mkdir($path, 0777, true) ) {

            @chmod($path,0777);

        }

    }





}





?>