<?php


if ( isset($_REQUEST['uid']) && $_REQUEST['uid'] != "" ) {

    /*
        echo "<pre>"; print_r($_REQUEST); echo "</pre>";
        exit();
    */
    if ( $_REQUEST['CBroute'] == "controller/medialibrary/deleteItem" ) {

        /*
                $objTmp = connectClass('ccomment/Comment.class.php',$_REQUEST['id']);
                //$objTmp->deleteEntry($_REQUEST['id']);
        */

        /*
                $arrSave = array();
                $arrSave['deleted'] = "1";
                $arrSave['data_deleted_who'] = $_SESSION["aid"];

                $objTmp = connectClass('cmedia/Media.class.php',$_REQUEST['id']);

                $res = $objTmp->saveContentUpdate($_REQUEST['id'],$arrSave);

                //
                $name_old = $objTmp->getAttribute('name');

                //
                $name_new = "_deleted_".$objTmp->getAttribute('name');

                //
                 rename($objTmp->getAttribute('path').$name_old,$objTmp->getAttribute('path').$name_new);
        */

        $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);
        //echo "<pre>"; print_r($objTmp); echo "</pre>";exit;

        $file = $objTmp->getAttribute('path').$objTmp->getAttribute('name');
        if ( !stristr($file,BASEDIR) ) $file = BASEDIR.$file;
        $file = str_replace("//", "/", $file);
        //echo "<pre>"; print_r($file); echo "</pre>";exit;

        if ( $file != "" && $file != "/" ) {
            if ( is_file($file) ) {
                //echo "$file is file";exit;

                // file
                if ( $file != "" && $file != "/" && stristr($file,BASEDIR."data/media/") ) {

                    // file
                    //echo "unlink $file <br>";
                    unlink($file);

                    // thumb
                    $thumb = $objTmp->getAttribute('path')."_thumb_".$objTmp->getAttribute('name');
                    if ( !stristr($thumb,BASEDIR) ) $thumb = BASEDIR.$thumb;
                    $thumb = str_replace("//", "/", $thumb);

                    if ( is_file($thumb) ) {
                        if ( $thumb != "" && $thumb != "/" && stristr($file,BASEDIR."data/media/") ) {
                            //echo "unlink $thumb <br>";
                            unlink($thumb);
                        }
                    }
                    // thumb - second
                    $thumb = $objTmp->getAttribute('path')."_thumb_".$objTmp->getAttribute('name').".jpg";
                    if ( !stristr($thumb,BASEDIR) ) $thumb = BASEDIR.$thumb;
                    $thumb = str_replace("//", "/", $thumb);

                    if ( is_file($thumb) ) {
                        if ( $thumb != "" && $thumb != "/" && stristr($file,BASEDIR."data/media/") ) {
                            //echo "unlink $thumb <br>";
                            unlink($thumb);
                        }
                    }

                }

                //echo $_REQUEST['uid'];exit;
                // db
                $tmp = $objTmp->deleteEntry($_REQUEST['uid']);
                //echo "tmp:$tmp";exit;

            } else {
                //echo "$file is not"; exit;
            }
        }


        ?>
        <script>

            $('#myModal').modal('toggle');
            globalDetailModal.toggle();

            listItems();


        </script>

        <?php


    }

}





?>