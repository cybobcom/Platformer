<?php

//
// edit
//

//echo "<pre>"; print_r($_SERVER); echo "</pre>";
//echo $_SERVER['REDIRECT_HANDLER'];

if ( $_REQUEST['uid'] != "" ) {

    $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);
    //echo "<pre>"; print_r($objTmp); echo "</pre>";


    $arrPathInfo = pathinfo($objTmp->getAttribute('path').$objTmp->getAttribute('name'));


    ?>

    <script>
        function doModalEdit(){

            var tmp = $('#modal_item_edit').serializeArray();
            // alert( "doModalCategoryEdit "+tmp );

            var url = '<?php echo BASEURL; ?>/controller/medialibrary/updateItem/';
            //alert(url);

            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'dataType': 'text',  // <-- Das hier hinzufügen!
                'success': function(result){
                    //process here
                    //alert( "Load was performed. "+result );
//alert(result);

                    globalDetailModal.toggle();

                    listItems();


                }
            });

            return false; // no submit of form
        }
    </script>




    <div class="modal-header">
        <h5 class="modal-title">Eintrag bearbeiten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body">

        <form method="post" id="modal_item_edit" class="form-horizontal">

            <?php echo cb_makeHiddenForm ("uid",$objTmp->getAttribute('medialibrary_uid'),"form-control form-control-sm"); ?>
            <?php echo cb_makeHiddenForm ("save[name_old]",$objTmp->getAttribute('name'),"form-control form-control-sm"); ?>

            <table width="100%" class="table table-sm ">

                <tr class="back8">
                    <td>Preview</td>
                    <td><?php

                        $strPath = BASEDIR.$objTmp->getAttribute('path');
                        //echo "strPath<pre>"; print_r($strPath); echo "</pre>";

                        $file = $objTmp->getAttribute('name');

                        if ( $arrPathInfo['extension'] == "mp4" ) {
                            $file = str_replace(".mp4",".mp4.jpg",$file);
                        }
                        if ( $arrPathInfo['extension'] == "m4v" ) {
                            $file = str_replace(".m4v",".m4v.jpg",$file);
                        }
                        if ( $arrPathInfo['extension'] == "mpg" ) {
                            $file = str_replace(".mpg",".mpg.jpg",$file);
                        }
                        if ( $arrPathInfo['extension'] == "mpeg" ) {
                            $file = str_replace(".mpeg",".mpeg.jpg",$file);
                        }
                        if ( $arrPathInfo['extension'] == "pdf" ) {
                            $file = str_replace(".pdf",".pdf.jpg",$file);
                        }
                        //echo "<pre>"; print_r($strPath."_thumb_".$file); echo "</pre>";
                        //echo '<img class="img-thumbnail" src="'.$strPath."_thumb_".$file.'" ><br>';

                        //
                        if ( !stristr($strPath,BASEDIR) ) $strPath = BASEDIR.$strPath;


                        $data = "";
                        if ( is_file($strPath."_thumb_".$file) ) {

                            $data = file_get_contents($strPath."_thumb_".$file);
                            //echo $data;

                        } else {

                            $data = generatePreview($strPath,$objTmp->getAttribute('name'));

                            file_put_contents($strPath."_thumb_".$file, $data);

// 				 	unset($data);

                        }
                        //echo "data<pre>"; print_r($data); echo "</pre>";

                        //
                        $ext = pathinfo($file, PATHINFO_EXTENSION);

                        $strShowAction = "classid_download_file";
                        if ( $ext == "jpeg" || $ext == "jpg" || $ext == "png" || $ext == "pdf" || $ext == "gif" || $ext == "txt" ) {
                            $strShowAction = "classid_show_file";
                        }

                        $strImageTmp = '<img data-id="'.$objTmp->getAttribute('medialibrary_uid').'" data-url="'.str_replace(BASEDIR."data/media/", BASEURL."data/media/",$objTmp->getAttribute('path').$objTmp->getAttribute('name')).'" src="data:image/png;base64,'.base64_encode($data).'" class="img-thumbDEVnail '.$strShowAction.'" title="'.$file.' | '.getFilesizeWithPath($strPath.$file).'" width="160" height="160" style=" margin-right:10px;" /><small><span data-id="'.$objTmp->getAttribute('medialibrary_uid').'" data-url="'.str_replace(BASEDIR."data/media/", BASEURL."data/media/",$objTmp->getAttribute('path').$objTmp->getAttribute('name')).'" class="ti-eye '.$strShowAction.'" style="color:grey;"></span>&nbsp;<span class="bi bi-arrow-clockwise classid_thumb_generate" style="color:grey;"></span></small><br> ';//.$strTitle;
                        echo $strImageTmp;


                        echo '<small>';
                        echo getFilesizeWithPath(BASEDIR.$objTmp->getAttribute('path').$objTmp->getAttribute('name'));

                        $arrImageInformation = getimagesize(BASEDIR.$objTmp->getAttribute('path').$objTmp->getAttribute('name'));
                        //echo "<pre>"; print_r($arrImageInformation); echo "</pre>";

                        /*
                                    [0] => 1920
                                    [1] => 1080
                                    [2] => 2
                                    [3] => width="1920" height="1080"
                                    [bits] => 8
                                    [channels] => 3
                                    [mime] => image/jpeg
                        */

                        if ( is_array($arrImageInformation) && count($arrImageInformation) >= 1 ) {
                            echo ' | '.$arrImageInformation[0]." x ".$arrImageInformation[1]." px";
                        }

                        echo '</small>';
                        ?>

                        <br>



                        <script>
                            $(document).off('click', '.classid_thumb_generate');
                            $(document).on('click', '.classid_thumb_generate', function () {

                                generateThumb('<?php echo $strPath."".$objTmp->getAttribute('name'); ?>','<?php echo "".$objTmp->getAttribute('medialibrary_uid'); ?>');

                            });


                            $(document).off('click', '.classid_show_file');
                            $(document).on('click', '.classid_show_file', function () {

                                //showPrivateFile('<?php echo "".$objTmp->getAttribute('media_id'); ?>');
//			showPrivateFile( $(this).attr("data-id") );
                                showFile( $(this).attr("data-url") );

                            });

                            $(document).off('click', '.classid_download_file');
                            $(document).on('click', '.classid_download_file', function () {
                                //alert("dev"+$(this).attr("data-id"));
                                downloadPrivateFile( $(this).attr("data-id") );

                            });

                        </script>
                    </td>
                </tr>

                <tr class="back8">
                    <td>Name</td>
                    <td><?php
                        $arrPathInfo = pathinfo($objTmp->getAttribute('name'));
                        //echo "<pre>"; print_r($arrPathInfo); echo "</pre>";

                        $strName = $arrPathInfo['filename'];
                        echo cb_makeInputForm ("save[name]",$strName,"form-control form-control-sm");
                        echo $arrPathInfo['extension'];
                        echo cb_makeHiddenForm ("save[extension]",$arrPathInfo['extension'],"form-control form-control-sm");
                        ?></td>
                </tr>

                <tr class="back8">
                    <td>Titel</td>
                    <td><?php echo cb_makeInputForm ("save[title]",$objTmp->getAttribute('title'),"form-control form-control-sm"); ?></td>
                </tr>

                <tr class="back8">
                    <td>Beschreibung</td>
                    <td><?php echo cb_makeTextfieldForm ("save[description]",$objTmp->getAttribute('description'),8,"form-control form-control-sm"); ?></td>
                </tr>

                <tr class="back8">
                    <td>Keywords</td>
                    <td><?php echo cb_makeTextfieldForm ("save[keywords]",$objTmp->getAttribute('keywords'),4,"form-control form-control-sm"); ?></td>
                </tr>

                <tr class="back8">
                    <td>Copyright</td>
                    <td><?php echo cb_makeInputForm ("save[copyright]",$objTmp->getAttribute('copyright'),"form-control form-control-sm"); ?></td>
                </tr>

                <tr class="back8">
                    <td>Autor</td>
                    <td><?php echo cb_makeInputForm ("save[author]",$objTmp->getAttribute('author'),"form-control form-control-sm"); ?></td>
                </tr>

                <tr class="back8">
                    <td>Kategorien</td>
                    <td><?php
/*
                        //
                        $arrUserCategories = array();
                        if ( $objTmp->getAttribute('categories') != "" ) {
                            $arrUserCategories = explode(",", $objTmp->getAttribute('categories'));
                        }
                        //echo "<pre>"; print_r($arrUserCategories); echo "</pre>";

                        //
                        $objCategory = CBinitObject('Category');
                        $arrCategories = $objCategory->getAggregatedList();
                        //echo "<pre>"; print_r($arrCategories); echo "</pre>";

                        if ( is_array($arrCategories) && count($arrCategories) >= 1 ) {
                            foreach ( $arrCategories as $rCat=>$vCat ) {

                                //
                                $tmp = "";
                                if ( in_array($vCat["category_id"],$arrUserCategories) ) $tmp = "1";

                                //
                                echo '<div style="margin-left:'.$vCat["level"].'0px;">';
                                if ( $vCat["level"] == "0" ) {
                                    echo '<b>'.$vCat["name"].'</b><br>';
                                } else {
                                    echo '<label for="'."categories[".$vCat["category_id"]."]".'" style="display:block;">';
                                    echo cb_makeCheckboxForm ("categories[".$vCat["category_id"]."]",$tmp,NULL,NULL,NULL,NULL,"classid_checkbox_categories")." ".$vCat["name"]."<br>";
                                    echo '</label>';

                                }
                                echo '</div>';

                            }
                        }
                        */


                        ?></td>
                </tr>


                <?php
                ?>



                <!--
	<tr class="back8">
  <td>aktiv</td>
	<td><?php echo cb_makeCheckboxForm ("save[active]",$objTmp->getAttribute('active')); ?></td>
  </tr>
-->

                <?php
                ?>


            </table>
        </form>

    </div>

    <div class="modal-footer">

        <span onclick="deleteItem('<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>')" class="me-auto bi bi-trash"></span>

        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
        <button type="button" class="btn btn-primary" onclick="doModalEdit()">Speichern</button>
    </div>




    <?php

}

?>