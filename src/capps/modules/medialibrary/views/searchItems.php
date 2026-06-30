<?php

// 	echo "<pre>"; print_r($_REQUEST); echo "</pre>";

//
// init
//

//
$objMedia = CBinitObject('MediaLibrary');


//
$secure_user_data_path = "/data/media/";//SECUREDIR."user/".$_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"]."/data/";
// TODO: admin should enter all user data

//
$current_path = $secure_user_data_path;






$arrCondition = array();
//$arrCondition["type"] = "NOT directory";
//$arrCondition["deleted"] = "NOT 1";
// $arrCondition["active"] = "1";

if ( isset($_REQUEST['filter_address_id']) && $_REQUEST['filter_address_id'] != "" && $_REQUEST['filter_address_id'] != "undefined" ) {
    $arrCondition["address_id"] =  $_REQUEST['filter_address_id'];
}

//echo "<pre>"; print_r($arrCondition); echo "</pre>";

$boolSearch = false;

$selection = "";
$selection .= " path LIKE '".$secure_user_data_path."%' ";

if ( isset($_REQUEST['search']) && $_REQUEST['search'] != "" && $_REQUEST['search'] != "undefined" ) {
    $boolSearch = true;


    //
    $arrSearch = explode(" ",$_REQUEST['search']);

    if ( is_array($arrSearch) && count($arrSearch) >= 1 ) {

        foreach ( $arrSearch as $strSearch ) {

            if ( $strSearch == "" ) continue;
            if ( $strSearch == " " ) continue;
            //if ( strlen($strSearch) < 3 ) continue; // 2026-05-21 Bob: make onfocus list without searchstring possible

            if ( $selection != "" ) $selection .= " AND ";

            //		$selection .= " ( name LIKE '%".$_REQUEST['search']."%' OR description LIKE '%".$_REQUEST['search']."%' OR path LIKE '%".$_REQUEST['search']."%' ) ";
            $selection .= " ( name LIKE '%".$strSearch."%' OR description LIKE '%".$strSearch."%' OR path LIKE '%".$strSearch."%' ) ";

        }

    }
} else {

    // 2026-05-21 Bob: make onfocus list without searchstring possible
    // if ( $selection != "" ) $selection .= " AND ";
    // $selection .= " ( name LIKE 'NEVERNEVER' ) ";

}
//echo $selection;
//CBLog( $arrCondition );

//$arrIDs = $objMedia->getAllEntries('name','ASC',$arrCondition,$selection,"medialibrary_uid","100");
$arrIDs = $objMedia->getAllEntries('date_created','DESC',$arrCondition,$selection,"medialibrary_uid","100");
//CBLog( $objMedia->getLastError() );
//CBLog($arrIDs);


$arrOutput = [];
if ( is_array($arrIDs) && count($arrIDs) >= 1 ) {
    foreach ($arrIDs as $run=>$strFile){
        //echo "<pre>"; print_r($strFile); echo "</pre>";

        $strUID = trim($strFile["medialibrary_uid"]);
        $objTmp = CBinitObject('MediaLibrary',$strUID);

        // all string
        $arrOutput[] = array_map('strval',$objTmp->arrAttributes);

    }
}


$output = json_encode($arrOutput, JSON_HEX_APOS);

header('Content-Type: application/json');
echo $output;




?>
