<?php

//
// edit
//

if ( $_REQUEST['uid'] != "" ) {

    $objTmp = CBinitObject('MediaLibrary',$_REQUEST['uid']);


    ?>
    <div class="modal-header">
        <h5 class="modal-title">Verzeichnis bearbeiten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body">
        <form method="post" id="modal_item_edit" class="form-horizontal">


            <?php echo cb_makeHiddenForm ("uid",$objTmp->getAttribute('medialibrary_uid'),"form-control formular4"); ?>
            <?php echo cb_makeHiddenForm ("save[name_old]",$objTmp->getAttribute('name'),"form-control formular4"); ?>

            <table width="100%" border="0" cellspacing="1" cellpadding="5" class="table table-condensed">


                <tr class="back8">
                    <td>Name</td>
                    <td><?php echo cb_makeInputForm ("save[name]",$objTmp->getAttribute('title'),"form-control formular4"); ?></td>
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

                //
                // check sub directories
                //
                $dir = BASEDIR.$objTmp->getAttribute('path')."/".$objTmp->getAttribute('name');
                $files = array_diff(scandir($dir), array('.', '..'));
                //echo "files<pre>"; print_r($files); echo "</pre>";

                $boolHasSubDirectory = false;
                if ( is_array($files) && count($files) >= 0 ) {
                    foreach( $files as $file ) {
                        if ( is_dir($dir."/".$file) ) $boolHasSubDirectory = true;
                    }
                }

                ?>


            </table>
        </form>

    </div>

    <div class="modal-footer">

        <div class="me-auto">
            <!-- only if no subdirectory exists -->
            <?php
            if ( !$boolHasSubDirectory ) {
                ?>
                <span onclick="deleteDirectory('<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>')" class="bi bi-trash"></span>
                <?php
            }
            ?>
        </div>

        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
        <button type="button" class="btn btn-primary classid_button_editdirectory">Speichern</button>
    </div>

    <script>
        $(document).off('click', '.classid_button_editdirectory');
        $(document).on('click', '.classid_button_editdirectory', function () {

            var current_dir = $('.id_current_path').val();

            var tmp = $('#modal_item_edit').serializeArray();
            // alert( "doModalCategoryEdit "+tmp );

            var url = '<?php echo BASEURL; ?>/controller/medialibrary/updateDirectory/';
            url += "&current_dir="+encodeURI(current_dir);

            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'dataType': 'text',  // <-- Das hier hinzufügen!
                'success': function(result){
                    //process here

                    globalDetailModal.toggle();

                    listItems();

                }
            });

            return false; // no submit of form
        });
    </script>

    <?php

}

?>