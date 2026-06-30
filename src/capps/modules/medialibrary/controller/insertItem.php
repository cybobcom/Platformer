<?php


if ( is_array($_REQUEST['save']) ) {

    //echo "<pre>"; print_r($_REQUEST); echo "</pre>";

    //if ( $_REQUEST['action'] == "saveModalNewCategory" ) {

    $arrSave = array();
    $arrSave = $_REQUEST['save'];
// 		$arrSave['date_created'] = time();

    //
    $arrSave['media_uid'] = create_guid();

    $objTmp = connectClass('cmedia/MediaLibrary.class.php');
    $intID = $objTmp->saveContentNew($arrSave,true);

    $objTmp = connectClass('cmedia/MediaLibrary.class.php',$arrSave['media_uid']);

    ?>
    <script>

        $('#myModal').modal('toggle');

        listItems();

    </script>

    <?php


    //}

}





?>