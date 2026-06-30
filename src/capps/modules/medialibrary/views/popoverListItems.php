<?php

//echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

//
// init
//
$objDBS = connectClass('cbasic/DatabaseConnector.class.php');
$obj = connectClass('cmedia/MediaLibrary.class.php');
// echo "<pre>"; print_r($obj); echo "</pre>";

//
// 	$secure_user_data_path = SECUREDIR."user/".$_SESSION["aid"]."/data/";
//$media_path = PATH_TO_MEDIA."";
$media_path = "/data/media/";



$arrCondition = array();
$arrCondition["deleted"] = "NOT 1";
$arrCondition["type"] = "NOT directory";
// $arrCondition["active"] = "1";

if ( $_REQUEST['filter_address_id'] != "" && $_REQUEST['filter_address_id'] != "undefined" ) {
    $arrCondition["address_id"] =  $_REQUEST['filter_address_id'];
}

//echo "<pre>"; print_r($arrCondition); echo "</pre>";

$selection = "";
$selection .= " path LIKE '".$media_path."%' ";

//
if ( $_REQUEST['search'] != "" && $_REQUEST['search'] != "undefined" ) {

    $sorting = "name";
    $order = "ASC";

    $_REQUEST['search'] = str_replace("/"," ",$_REQUEST['search']); // search & find full path

    $arrSearch = explode(" ",$_REQUEST['search']);

    if ( is_array($arrSearch) && count($arrSearch) >= 1 ) {

        foreach ( $arrSearch as $strSearch ) {

            if ( $strSearch == "" ) continue;
            if ( $strSearch == " " ) continue;
            if ( strlen($strSearch) < 2 ) continue;

            if ( $selection != "" ) $selection .= " AND ";
            $selection .= " ( name LIKE '%".$strSearch."%' OR title LIKE '%".$strSearch."%' OR description LIKE '%".$strSearch."%' OR path LIKE '%".$strSearch."%' ) ";

        }
    }


}


$arrIDsAll = $obj->getAllEntries($sorting,$order,$arrCondition,$selection,NULL);
$arrIDs = $obj->getAllEntries($sorting,$order,$arrCondition,$selection,NULL,"100");
//debug_print_r($arrIDs);exit;
//echo $selection;exit;


//
// json
//
if ( $_REQUEST["format"] == "json" ) {

    $arrOutput = array();

    if ( is_array($arrIDs) && count($arrIDs) >= 1 ) {
        foreach ($arrIDs as $run=>$strID){

            $strUID = trim($strID["media_uid"]);
            $objTmp = connectClass('cmedia/MediaLibrary.class.php',$strID."");

            // no strUID as key cause js
            $arrOutput[] = $objTmp->arrAttributes;

        }
    }


    $file = json_encode($arrOutput);

    header('Content-Type: application/json');

    header('Content-Encoding: gzip');
    header('Accept-Encoding: gzip');
    echo gzencode($file,9,FORCE_GZIP);


    exit;
}



/*
echo count($arrIDsAll)." Einträge";
echo ' <small>(Limit: 100)</small>';
*/

?>



<div id="checkboxes" style="width: 100%;">

    <script>

        //$("#table_list td").click(function() {
        $('#table_list td').off('click');
        $('#table_list td').on('click',function() {

            var one = $(this).html();
            var two = $(this).parent().children("td").html();

        });

        //$(".id_document_popover_item<?php echo $_REQUEST["target"]; ?>").click(function() {
        $(".id_document_popover_item<?php echo $_REQUEST["target"]; ?>").off('click');
        $(".id_document_popover_item<?php echo $_REQUEST["target"]; ?>").on('click',function() {

            var id = $(this).attr( 'data-id' );
            var file = $(this).attr( 'data-file' );
            var thumb = $(this).attr( 'data-thumb' );
            //alert(file);
            console.log("popoverListItems click <?php echo $_REQUEST["target"]; ?> id: "+id)
            console.log("popoverListItems click <?php echo $_REQUEST["target"]; ?> file: "+file)
            console.log("popoverListItems click <?php echo $_REQUEST["target"]; ?> thumb: "+thumb)

            $(".<?php echo $_REQUEST["target"]; ?>").val(file);
            $(".<?php echo $_REQUEST["target"]; ?>_id").val(id);
            $(".<?php echo $_REQUEST["target"]; ?>_preview").prop("src","<?php echo BASEURL; ?>"+thumb);
            $(".<?php echo $_REQUEST["target"]; ?>_selector").attr("data-value",file);

            $(".<?php echo $_REQUEST["target"]; ?>").change();

            $("#<?php echo $_REQUEST["target"]; ?>_container").css("display","none");

        });

    </script>

    <table class="table table-strDEViped table-sm" id="table_list" style="width: 100%;">
        <!--
          <thead>
          <tr>
            <th></th>
            <th>Name</th>
          </tr>
          </thead>
        -->
        <?php

        $inLevel = 0;
        $arrParents = array();
        $strToRepeat = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

        if ( is_array($arrIDs) && count($arrIDs) >= 1 ) {
            foreach ($arrIDs as $run=>$strID){

                $strUID = trim($strID["media_uid"]);
                $objTmp = connectClass('cmedia/MediaLibrary.class.php',$strID."");

                $strStyle = "";
// 		if ( $objTmp->getAttribute("active") != "1" ) $strStyle = "opacity:0.4;";

                $path = $objTmp->getAttribute("path");
                $file = $objTmp->getAttribute("name");

                $strPath = str_replace(BASEDIR, "", $path);


                $thumbnail = $strPath.'_thumb_'.$file;
                if ( !is_file( BASEDIR.$thumbnail ) ) $thumbnail = $strPath.''.$file;
                ?>

                <tr class="cbDEV_pointer id_document_popover_item<?php echo $_REQUEST["target"]; ?>"  data-id="<?php echo $objTmp->getAttribute("media_uid"); ?>" data-file="<?php echo $strPath.$file; ?>" data-thumb="<?php echo $thumbnail; ?>" style="<?php echo $strStyle; ?>">

                    <td width="50px">
                        <?php

                        //
                        //
                        //


                        $data = "";
                        if ( is_file($path."_thumb_".$file) ) {

                            //$data = file_get_contents($path."_thumb_".$file);

                        } else {

                            $data = generatePreview($path,$file);

                            file_put_contents($path."_thumb_".$file, $data);

                            unset($data);

                        }

                        $strPath = str_replace(PATH_TO_MEDIA, URL_TO_MEDIA, $path);
                        //echo $strPath."_thumb_".$file;

                        if ( !is_file( BASEDIR.str_replace(BASEURL,"",BASEURL.$strPath."_thumb_".$file) ) ) {
                            echo '<img src="'.BASEURL.$strPath."".$file.'" width="50" class="img-thumbnail" style="width:50px !important;">';
                        } else {
                            echo '<img src="'.BASEURL.$strPath."_thumb_".$file.'" width="50" class="img-thumbnail" style="width:50px !important;">';
                        }
                        ?>
                    </td>

                    <td style="font-size:13px; line-height: 13px;">
                        <span name="<?php echo $objTmp->getAttribute('media_uid'); ?>"></span>

                        <?php
                        echo '<b>'.htmlspecialchars($objTmp->getAttribute('name')).'</b><br>';
                        echo '<small>'.str_replace(BASEDIR,"",$objTmp->getAttribute('path')).'</small><br>';
                        ?>

                    </td>

                </tr>
                <?php

            }

        } else {
            echo "<tr><td><i>keine Inhalte</i></td></tr>";
        }

        ?>
    </table>




