<?php

//
// edit
//
//echo "dsf".__FILE__;
//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";

use capps\modules\database\classes\CBObject;

//
$strModuleName = "###MODULE###";
//CBLog($strModuleName);


if ($_REQUEST['id'] != "") {

    //$objTmp = CBinitObject("Address",$_REQUEST['id']);
    //$objTmp = new CBObject($_REQUEST['id'],"capps_address","address_id");
    //echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";

    //
    $objTmp = CBinitObject("Structure", $_REQUEST["id"]);
//CBLog($objTmp);


    $arrIDs = $objTmp->getAllEntries("parent_id|sorting","ASC|ASC",NULL,NULL,"structure_id, parent_id, previous_id, sorting, name",NULL);
    if ( is_array($arrIDs) ) {
        $arrIDs = $objTmp->sortStructureWithSorting($arrIDs);
    }

    $arrPageSelect = array();
    if ( is_array($arrIDs) && count($arrIDs) >= 1 ) {
        foreach ( $arrIDs as $item ) {
            $arrPageSelect[$item["structure_id"]] = str_repeat("-",$item["level"]*2).$item["name"];
        }
    }
        ?>



        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Eintrag bearbeiten</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
            <form method="post" id="modal_item_update" class="form-horizontal">

                <?php echo cb_makeHiddenForm("id", $objTmp->getAttribute($objTmp->strPrimaryKey), "form-control form-control-sm"); ?>

                <table class="table table-sm">


                <tr class="">
                    <td>name</td>
                    <td><?php echo cb_makeInputForm("save[name]", $objTmp->getAttribute('name'), "form-control "); ?></td>
                </tr>

                <tr class="">
                    <td>SEO name</td>
                    <td><?php echo cb_makeInputForm("save[data_seo_name]", $objTmp->getAttribute('data_seo_name'), "form-control "); ?></td>
                </tr>

                <tr class="">
                    <td>language_id</td>
                    <td><?php echo cb_makeInputForm("save[language_id]", $objTmp->getAttribute('language_id'), "form-control "); ?></td>
                </tr>

                <tr class="">
                    <td>parent_id</td>
                    <td><?php
                        //echo cb_makeInputForm("save[parent_id]", $objTmp->getAttribute('parent_id'), "form-control ");
                        echo cb_makeSelectForm("save[parent_id]", array_keys($arrPageSelect),array_values($arrPageSelect),$objTmp->getAttribute('parent_id'));
                        ?></td>
                </tr>

                <tr class="">
                    <td>previous_id LEGACY</td>
                    <td><?php echo cb_makeInputForm("save[previous_id]", $objTmp->getAttribute('previous_id'), "form-control "); ?></td>
                </tr>

                <tr class="">
                    <td>sorting</td>
                    <td><?php echo cb_makeInputForm("save[sorting]", $objTmp->getAttribute('sorting'), "form-control "); ?></td>
                </tr>

                <tr class="">
                    <td>template</td>
                    <td><?php echo cb_makeInputForm("save[template]", $objTmp->getAttribute('template'), "form-control "); ?></td>
                </tr>

                <tr class="">
                    <td>Nutzergruppe</td>
                    <td><?php

                        $arrAddressGroups = explode(",",$objTmp->getAttribute('addressgroups'));

                        $objAG = new CBObject(NULL,"capps_addressgroup","addressgroup_uid");

                        //$objAG = connectClass('caddress/Addressgroup.class.php');
                        $arrAG = $objAG->getAllEntries("sorting|name","ASC|ASC",NULL,NULL,"*");
                        //echo "<pre>"; print_r($arrAG); echo "</pre>";

                        if ( is_array($arrAG) && count($arrAG) >= 1 ) {
                            foreach ( $arrAG as $rAG=>$vAG ) {



                                //
                                $tmp = "";
                                if ( in_array($vAG["entity"],$arrAddressGroups) ) $tmp = "1";


                                echo '<label for="'."addressgroups[".$vAG["entity"]."]".'" style="display:block;">';
                                echo cb_makeCheckboxForm ("addressgroups[".$vAG["entity"]."]",$tmp,NULL,NULL,NULL,NULL,"classid_checkbox_addressgroup")." ".$vAG["name"]."<br>";
                                echo '</label>';

                            }
                        }


                        ?></td>
                </tr>

                <tr class="">
                    <td>visible</td>
                    <td><?php echo cb_makeCheckboxForm("save[visible]", $objTmp->getAttribute('visible')); ?></td>
                </tr>

                <tr class="">
                    <td>aktiv</td>
                    <td><?php echo cb_makeCheckboxForm("save[active]", $objTmp->getAttribute('active')); ?></td>
                </tr>

                <tr class="">
                    <td>icon</td>
                    <td><?php echo cb_makeInputForm("save[data_icon]", $objTmp->getAttribute('data_icon'), "form-control "); ?></td>
                </tr>


            </table>

            </form>
        </div>

        <div class="modal-footer">

            <!-- 		<span onclick="deleteItem('<?php echo $objTmp->getAttribute($objTmp->strPrimaryKey); ?>')" class="me-auto bi bi-trash"></span> -->

            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
            <button type="button" class="btn btn-primary classid_button_update">Speichern</button>
        </div>



    <script>
        $(document).off('click', '.classid_button_update');
        $(document).on('click', '.classid_button_update', function () {


            var tmp = $('#modal_item_update').serializeArray();
            // alert( "doModalCategoryEdit "+tmp );

            var url = "<?php echo BASEURL; ?>controller/<?php echo $strModuleName; ?>/updateItem/";

            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'success': function (result) {
                    //process here
                    //alert( "Load was performed. "+url );

                    if (typeof window.mountedApp !== 'undefined') {
                        // vue.js
                        globalDetailModal.hide();
                        window.mountedApp.listPages();
                        window.mountedApp.listElements('<?php echo $objTmp->getAttribute('structure_id'); ?>');
                    } else {
                        // classic
                        globalDetailModal.hide();
                        listItems();

                    }
                }
            });

            return false; // no submit of form

        });

    </script>
    <?php

}

?>