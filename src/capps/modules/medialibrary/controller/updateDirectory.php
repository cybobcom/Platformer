<?php


if ( is_array($_REQUEST['save']) ) {



    $arrSave = array();
// 		$arrSave = $_REQUEST['save'];
    $arrSave['name'] = sanitizeFileName($_REQUEST["save"]["name"]);
    $arrSave['title'] = $_REQUEST["save"]["name"];
    $arrSave['date_updated'] = date('Y-m-d H:i:s');

    $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);



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


    $objTmp->saveContentUpdate($_REQUEST['uid'],$arrSave);

    $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);


    $path_old = $_REQUEST["current_dir"].sanitizeFileName($_REQUEST["save"]["name_old"]);
    //echo "<pre>"; print_r($path_old); echo "</pre>";//exit;

    $path_new = $_REQUEST["current_dir"].sanitizeFileName($_REQUEST["save"]["name"]);
    //echo "<pre>"; print_r($path_new); echo "</pre>";exit;


    if ( file_exists($path_old) ) {

        //
        // rename file system
        //
        rename($path_old,$path_new);

        //
        // rename all media items
        //
        $objM = CBinitObject('MediaLibrary');

        $arrCondition = array();
        $arrCondition["path"] = $path_old.'/%'; // and sub
        //echo "<pre>"; print_r($arrCondition); echo "</pre>";

        $selection = "";

        $arrIDs = $objM->getAllEntries('name','ASC',$arrCondition,$selection,"medialibrary_uid");
        //echo "<pre>"; print_r($arrIDs); echo "</pre>";exit;

        if ( is_array($arrIDs) && count($arrIDs) >= 1 ) {
            foreach ($arrIDs as $r=>$v){

                $objMtmp = CBinitObject('MediaLibrary',$v["medialibrary_uid"]);

                $arrSave = array();
                $arrSave["path"] = $path_new.'/';

                $objMtmp->saveContentUpdate($v["medialibrary_uid"],$arrSave);

            }
        }

    }

    //exit;




    //}

}





?>