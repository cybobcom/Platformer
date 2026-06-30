<?php


if ( $_REQUEST['uid'] != "" ) {

    //echo "<pre>"; print_r($_REQUEST); echo "</pre>";

    //if ( $_REQUEST['action'] == "saveModalNewCategory" ) {

    //
    $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);

    //
    $arrSave = array();
    $arrSave = $_REQUEST['save'];
//   		$arrSave['date_update'] = time();

    /*
            $arrSave['data_date_update'] = time();
            $arrSave['data_date_update_who'] = $_SESSION["aid"];
    */


    $name_old = sanitizeFileName($_REQUEST["save"]["name_old"]);
    //echo "<pre>"; print_r($name_old); echo "</pre>";//exit;

    $name_new = sanitizeFileName($_REQUEST["save"]["name"].'.'.$_REQUEST["save"]["extension"]);
    //$name_new = $name_new.'.'.$_REQUEST["save"]["extension"];
    //echo "<pre>"; print_r($name_new); echo "</pre>";exit;

    $arrSave['name'] = $name_new;

    // categories
    /*
    if ( isset($_REQUEST['categories'])  AND is_array($_REQUEST['categories'])  AND count($_REQUEST['categories']) >= 1 ){
        $arrUserCategories = explode(",",$objTmp->getAttribute("categories"));

        foreach ( $_REQUEST['categories'] as $gid=>$v ) {
            if ( $gid == '0' ) continue;
            if ( $v == "0" ) {
                // delete
                if ( is_array($arrUserCategories) && count($arrUserCategories) >= 1 ) {
                    foreach( $arrUserCategories as $rCG=>$vCG ) {
                        if ( $vCG == $gid ) {
                            unset($arrUserCategories[$rCG]);
                        }
                    }
                }
            } else {
                // insert
                if ( !in_array($gid, $arrUserCategories) ) $arrUserCategories[] = $gid;
            }

            $str = implode(",", $arrUserCategories);
            $str = ltrim($str,",");
            $arrSave["categories"] = $str;
        }
    }
    */


    //echo "<pre>"; print_r($arrSave); echo "</pre>";

    $res = $objTmp->saveContentUpdate($_REQUEST['uid'],$arrSave);


    $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);
    //CBLog( $objTmp );

    //
    $a = str_replace("//","/",BASEDIR.$objTmp->getAttribute('path').$name_old);
    $b = str_replace("//","/",BASEDIR.$objTmp->getAttribute('path').$name_new);
    if ( is_file($a) ) {
        rename($a, $b);
    } else {
        //echo "not valid file: $a";
    }




    //}

}

exit;
?>