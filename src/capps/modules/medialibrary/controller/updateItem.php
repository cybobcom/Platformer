<?php


if ( $_REQUEST['uid'] != "" ) {

    //echo "<pre>"; print_r($_REQUEST); echo "</pre>";

    //if ( $_REQUEST['action'] == "saveModalNewCategory" ) {

    //
    $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);

    //
    $arrSave = is_array($_REQUEST['save'] ?? null) ? $_REQUEST['save'] : [];
    unset($arrSave['path'], $arrSave['medialibrary_uid']); // SECURITY: path never from the request
//   		$arrSave['date_update'] = time();

    /*
            $arrSave['data_date_update'] = time();
            $arrSave['data_date_update_who'] = $_SESSION["aid"];
    */


    $name_old = (string)$objTmp->getAttribute('name'); // SECURITY: from the DB, not from the request
    //echo "<pre>"; print_r($name_old); echo "</pre>";//exit;

    $name_new = sanitizeFileName($_REQUEST["save"]["name"].'.'.$_REQUEST["save"]["extension"]);
    //$name_new = $name_new.'.'.$_REQUEST["save"]["extension"];
    //echo "<pre>"; print_r($name_new); echo "</pre>";exit;

    // SECURITY: same extensions as uploadFile.php (keep in sync), e.g. no rename to .php
    $arrAdditionsAllow = array('gif','png','jpg','jpeg','pdf','txt','rtf','doc','docx','xls','xlsx','csv','ppt','pptx','pps','mp4','m4v','mpg','mpeg','mp3','m4a');
    if ( !in_array(strtolower(pathinfo($name_new, PATHINFO_EXTENSION)), $arrAdditionsAllow, true) ) {
        echo "error"; exit;
    }

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
    if ( is_file($a) && $a !== $b ) {
        rename($a, $b);

        // rename thumbs too ("_thumb_<name>" for images, "_thumb_<name>.jpg" for pdf/video)
        $dir = dirname($a) . "/";
        foreach (["", ".jpg"] as $suffix) {
            if ( is_file($dir . "_thumb_" . $name_old . $suffix) ) {
                rename($dir . "_thumb_" . $name_old . $suffix, $dir . "_thumb_" . $name_new . $suffix);
            }
        }
    }




    //}

}

exit;
?>