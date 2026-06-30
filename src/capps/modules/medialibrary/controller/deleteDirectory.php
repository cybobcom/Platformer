<?php

//CBLog($_REQUEST);
//[CBroute] => controller/medialibrary/deleteDirectory

if ( isset($_REQUEST['uid']) && $_REQUEST['uid'] != "" ) {

    if ( $_REQUEST['CBroute'] == "controller/medialibrary/deleteDirectory" ) {

        $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);
        //echo "<pre>"; print_r($objTmp); echo "</pre>";exit();

        //
        $dir = $objTmp->getAttribute('path').''.$objTmp->getAttribute('name').'/';
        if ( !stristr($dir,BASEDIR) ) $dir = BASEDIR.$dir;
        //echo "<pre>BASEDIR"; print_r(BASEDIR); echo "</pre>";
        //echo "<pre>dir"; print_r($dir); echo "</pre>";

        //
        // check sub directories
        //
        $files = array_diff(scandir($dir), array('.', '..'));
        //echo "files<pre>"; print_r($files); echo "</pre>";

        $boolHasSubDirectory = false;
        if ( is_array($files) && count($files) >= 0 ) {
            foreach( $files as $file ) {
                if ( is_dir($dir."/".$file) ) $boolHasSubDirectory = true;
            }
        }

        // clean
        $dir = str_replace("//", "/", $dir);


        // for sure to avoid delete root
        if ( $dir != "" && $dir != "/" && !$boolHasSubDirectory ) {

            // for sure to avoid delete root
            if ( $dir == "" ) exit;
            if ( $dir == "/" ) exit;
            if ( $dir == BASEDIR ) exit;
            if ( !stristr($dir, BASEDIR."data/media/") ) exit;

            //
            if ( is_dir($dir) ) {
                //echo "$dir available";exit;

                //
                // delete file system
                //
                if ( is_array($files) && count($files) >= 0 ) {
                    foreach( $files as $file ) {
                        if ( is_file($dir."/".$file) ) {
                            //echo "DELETE File ".$dir."/".$file."<br>";
                            unlink($dir."/".$file);
                        }
                    }
                }

                //
                //echo "DELETE directory ".$dir."<br>";
                if ( $dir != "" && $dir != "/" ) {
                    rmdir($dir);
                }


                //
                // delete database
                //
                $objM = CBinitObject('MediaLibrary');

                $arrCondition = array();
                $arrCondition["path"] = str_replace(BASEDIR, "/", $dir).''.''; //
                //echo "<pre>"; print_r($arrCondition); echo "</pre>";

                $selection = "";

                $arrIDs = $objM->getAllEntries('name','ASC',$arrCondition,$selection,NULL);
                //echo "<pre>"; print_r($arrIDs); echo "</pre>";exit;

                if ( is_array($arrIDs) && count($arrIDs) >= 1 ) {
                    foreach ($arrIDs as $r=>$v){
                        //echo "<pre>"; print_r($v); echo "</pre>";

                        if ( $v["medialibrary_uid"] == "" ) continue;

                        $objMtmp = CBinitObject('MediaLibrary',$v["medialibrary_uid"]);
                        //echo "DELETE Database ".$objMtmp->getAttribute("medialibrary_uid").": ".$objMtmp->getAttribute("path").$objMtmp->getAttribute("name")."<br>";
                        $objMtmp->deleteEntry($v["medialibrary_uid"]);

                    }
                }

                // dir itself
                $tmp = $objTmp->deleteEntry($_REQUEST['uid']);

            }
        }
        //exit;




    }

}



?>