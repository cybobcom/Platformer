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

        // File and thumbs ("_thumb_<name>" for images, "_thumb_<name>.jpg" for pdf/video), only below data/media/
        $dir = str_replace("//", "/", BASEDIR . $objTmp->getAttribute('path'));
        $name = (string)$objTmp->getAttribute('name');
        if ( $name != "" && stristr($dir, BASEDIR . "data/media/") ) {
            foreach ([$name, "_thumb_" . $name, "_thumb_" . $name . ".jpg"] as $f) {
                if ( is_file($dir . $f) ) unlink($dir . $f);
            }
        }

        // DB entry also if the file is already missing (otherwise the list shows it again with an empty thumb)
        $objTmp->delete($_REQUEST['uid']);


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