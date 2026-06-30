<?php
//
global $objPlattformUser;

// echo "DEV<pre>"; print_r($_SESSION); echo "</pre>";
// echo "<pre>"; print_r($_REQUEST); echo "</pre>";
// exit;

// only login in
if ( !isset($_SESSION[PLATFORM_IDENTIFIER]["login_verified"]) || $_SESSION[PLATFORM_IDENTIFIER]["login_verified"] != "1" ) {
    exit;
}
if ( !isset($_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"]) || $_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"] == "" ) {
    exit;
}


if ( isset($_REQUEST['file']) && $_REQUEST['file'] != "" ) {

    //echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

    $file = $_REQUEST['file'];

    $path = "";
    if ( !stristr($file,BASEDIR) ) $path = BASEDIR;

    //
    $filename_new = "";
    if ( isset($_REQUEST['filename_new']) && $_REQUEST['filename_new'] != "" ) $filename_new = $_REQUEST['filename_new'];

    //
    downloadItem($path,$file,$filename_new);
    exit;

}

if ( isset($_REQUEST['path']) && $_REQUEST['path'] != "" && isset($_REQUEST['filename']) && $_REQUEST['filename'] != "" ) {

    //echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

    $path = $_REQUEST['path'];
    $filename = $_REQUEST['filename'];

    downloadItem(BASEDIR.$path,$filename);
    exit;

}


if ( isset($_REQUEST['item']) && $_REQUEST['item'] != "" ) {

    $id = $_REQUEST['item'];
    if ( base64_encode(base64_decode($_REQUEST['item'])) === $_REQUEST['item']){
        $id = base64_decode($_REQUEST['item']);
    }
    //echo "id<pre>"; print_r($id); echo "</pre>";exit;

    // $objAtmp = connectClass('caddress/Address.class.php');

    $objM = CBinitObject('MediaLibrary',$id);
    $section = $objM->getAttribute("section");
    $path = $objM->getAttribute("path");
    $filename = $objM->getAttribute("name");


    //echo "<pre>"; print_r($_SESSION['user_groups']); echo "</pre>";
    //echo "<pre>"; print_r($objM); echo "</pre>";
    //echo "<pre>"; print_r($objPlattformUser); echo "</pre>";
    //echo "id<pre>"; print_r($objM); echo "</pre>";exit;

    //
    // check user
    //
    // TODO neu
    /*
    $arrUserCategories = explode(",", $objPlattformUser->getAttribute("data_categories"));
    //echo "arrUserCategories<pre>"; print_r($arrUserCategories); echo "</pre>";

    $arrMediaCategories = explode(",", $objM->getAttribute("categories"));
    //echo "arrMediaCategories<pre>"; print_r($arrMediaCategories); echo "</pre>";
    //exit;

    $boolCheckAccess = false;
    if ( is_array($arrUserCategories) && count($arrUserCategories) >= 1 && is_array($arrMediaCategories) && count($arrMediaCategories) >= 1 ) {

        $arrIntersect = array_intersect($arrUserCategories, $arrMediaCategories);
        //echo "arrIntersect<pre>"; print_r($arrIntersect); echo "</pre>";exit;

        if ( is_array($arrIntersect) && count($arrIntersect) >= 1 ) {
            $boolCheckAccess = true;
        }

    }


    if ( !$boolCheckAccess ) {
        echo '<font color="red"><i>Zugriff verboten</i></font>';
        exit;
    }
*/


    //
    //
    //
    //CBLog(BASEDIR.$path.$filename);exit;

    // goliath
    downloadItem(BASEDIR.$path,$filename);

    //
    exit;

}

?>