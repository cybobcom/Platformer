<?php

global $objPlattformUser;

//
// init
//

//
$arrSave = array();
$arrSave["settings_address_search"] = $_REQUEST["search"] ?? "";
$objPlattformUser->saveContentUpdate($_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"],$arrSave);


$objTmp = CBinitObject("Address");
//echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";exit;

//
$selection = "";

//
if ( $_REQUEST['search'] != "" && $_REQUEST['search'] != "undefined" ) {

    //
    $strSearch = $_REQUEST["search"];

    //
    $arrSearch = explode(" ",$strSearch);

    //
    foreach ( $arrSearch as $rS=>$strSearch ) {

        if ( $strSearch == "" ) continue;
        if ( $strSearch == " " ) continue;
        if ( strlen($strSearch) < 2 ) continue;

        if ( $selection != "" ) $selection .= " AND ";
        $selection .= " ( company LIKE '%".$strSearch."%' OR firstname LIKE '%".$strSearch."%' OR lastname LIKE '%".$strSearch."%' OR street LIKE '%".$strSearch."%' OR postcode LIKE '%".$strSearch."%' OR city LIKE '%".$strSearch."%' OR login LIKE '%".$strSearch."%' OR password LIKE '%".$strSearch."%' OR data LIKE '%".$strSearch."%' OR media LIKE '%".$strSearch."%'  ) ";

    }

}

$arrIDs = $objTmp->getAllEntries("lastname",NULL,NULL,$selection);
//echo "arrIDs<pre>"; print_r($arrIDs); echo "</pre>";exit;


?>

<div class="table-responsive contentarea" style="padding: 15px; margin-bottom: 15px;">


    <table class="table table-sm" id="table_list">
        <thead>
        <tr>
            <th class="cb_table_first_row"></th>
            <th>Name</th>
            <th>Login</th>
            <th>Addressgroups</th>
            <th>letzter Login</th>
        </tr>
        </thead>
        <?php


        if (is_array($arrIDs) && count($arrIDs) >= 1) {
            foreach ($arrIDs as $run => $arrEntry) {
                //echo "arrEntry<pre>"; print_r($arrEntry); echo "</pre>";

                //$objTmp = CBinitObject(ucfirst($strModuleName),$arrEntry["address_id"]);
                $objTmp = new \capps\modules\database\classes\CBObject($arrEntry["address_uid"], "capps_address", "address_uid");
                //echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";


                $strStyle = "";
                if ($objTmp->getAttribute('active') != "1") $strStyle = "opacity:0.25;";

                ?>

                <tr class="classid_entry" style="<?php echo $strStyle; ?>"
                    data-id="<?php echo $objTmp->getAttribute('address_uid'); ?>">

                    <td>
                        <div>
                            <?php
                            //echo cb_makeCheckboxForm ("save[".($strID)."]","");
                            ?>
                        </div>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('firstname')." ".$objTmp->getAttribute('lastname');
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('login')."<br>";
                        echo '<i>'.$objTmp->getAttribute('login_alternative')."</i><br>";
                        ?>
                    </td>

                    <td>
                        <?php
                        //echo $objTmp->getAttribute('addressgroups');
                        $arrAddressGroups = explode(",", $objTmp->getAttribute('addressgroups'));
                        asort($arrAddressGroups);
                        echo '<small>';
                        foreach ($arrAddressGroups as $entity) {
                            $objAddressGroupTmp = CBinitObject("AddressGroup","entity:".$entity);
                            echo $entity.": ".$objAddressGroupTmp->getAttribute('name')."<br>" ;
                        }
                        echo '</small>';
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('date_lastlogin');
                        ?>
                    </td>


                </tr>
                <?php

            }

        } else {
            echo "<tr><td><i>no entry</i></td></tr>";
        }

        ?>
    </table>

    <!--
    <div align="right" style="float:right;margin-right:20px; width:340px; display:block; border:0px solid #FF0000">

        <div align="left" style="float:left;margin-right:20px; width:220px; display:block; border:0px solid #FF0000">

            <select name="do_stack" class="form-control input-sm">
                <option value="">-- keine Angabe --</option>
                <option value="delete_selection">Auswahl löschen</option>
            </select></div>

        <input type="submit" name="button" id="button" value="Ausführen" class="btn btn-default btn-sm"
               onclick="doStack();">

    </div>

    <div align="left" style="float:left;margin-right:20px; width:320px; display:block; border:0px solid #FF0000">

        <a onclick="stacktoggle();"><i class="ti-control-shuffle"></i></a>

        <script language="JavaScript">
            function stacktoggle(source) {

                var inputs = document.getElementsByTagName("input");
                var checkboxes = [];
                for (var i = 0; i < inputs.length; i++) {
                    if (inputs[i].type == "checkbox") {
                        checkboxes.push(inputs[i]);
                        if (inputs[i].checked) {
                            //alert('checked');
                            inputs[i].checked = false;
                        } else {
                            inputs[i].checked = true;
                        }
                    }
                }

            }
        </script>

    </div>
    -->
</div>

<?php

//}

?>

<script>

    //
    $(document).on('click', '.classid_entry', function () {
        editItem($(this).attr('data-id'));
    });


</script>