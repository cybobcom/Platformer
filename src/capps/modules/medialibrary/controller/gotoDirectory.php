<?php

global $objPlatformUser;

//
$arrSave = array();
$arrSave["settings_medialibrary_current_directory"] = $_REQUEST["dir"];
// fix path
$arrSave["settings_medialibrary_current_directory"] = str_replace(BASEDIR, "/", $arrSave["settings_medialibrary_current_directory"]);
if ( !stristr( $arrSave["settings_medialibrary_current_directory"], trim(BASEDIR,"/") ) ) $arrSave["settings_medialibrary_current_directory"] = BASEDIR.trim($arrSave["settings_medialibrary_current_directory"],"/");

//
$objPlatformUser->saveContentUpdate($_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"],$arrSave);

?>