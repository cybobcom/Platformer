<?php


if ( $_REQUEST['id'] != "" && $_REQUEST['media_uid'] != "" ) {

    //echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

    //if ( $_REQUEST['action'] == "saveModalNewCategory" ) {




    //
    $objTmp = connectClass('cproject/ProjectTodo.class.php',$_REQUEST['id']);

    $strDocuments = $objTmp->getAttribute("data_documents");

    if ( $strDocuments != "" ) $strDocuments .= ",";
    $strDocuments .= $_REQUEST['media_id'];

    //
    $arrSave = array();
    $arrSave["data_documents"] = $strDocuments;
    //echo "<pre>"; print_r($arrSave); echo "</pre>";exit;

    $res = $objTmp->saveContentUpdate($_REQUEST['id'],$arrSave);

    $objTmp = connectClass('cproject/ProjectTodo.class.php',$_REQUEST['id']);

    ?>
    <script>


        var value = $("#data_documents").val();

        if ( value != "" ) value += ",";
        value += <?php echo json_encode((string)($_REQUEST['media_uid'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        //alert(value);

        $("#data_documents").val(value);


        showDocuments(<?php echo json_encode((string)($_REQUEST['id'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);






    </script>

    <?php


    //}

}


?>