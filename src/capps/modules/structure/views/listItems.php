<?php

//use capps\modules\content\classes\Content;
//require_once(CAPPS . "modules/content/classes/Structure.php");

//
$strModuleName = CBgetModuleName(__FILE__);
//CBLog($strModuleName);

//
$objTmp = CBinitObject("Structure");
//CBLog($objTmp);


//$objTmp = new Content();
//echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";exit;

$arrIDs = $objTmp->getAllEntries("parent_id|sorting","ASC|ASC",NULL,NULL,"structure_id, parent_id, previous_id, sorting, name",NULL);
//echo "arrIDs<pre>"; print_r($arrIDs); echo "</pre>";exit;



// Beispiel-Daten
$data = [
    ['structure_id' => 1, 'parent_id' => null, 'previous_id' => null],
    ['structure_id' => 2, 'parent_id' => 1, 'previous_id' => null],
    ['structure_id' => 3, 'parent_id' => 1, 'previous_id' => 2],
    ['structure_id' => 4, 'parent_id' => 1, 'previous_id' => 3],
    ['structure_id' => 5, 'parent_id' => null, 'previous_id' => 1],
    ['structure_id' => 6, 'parent_id' => 5, 'previous_id' => null],
];

if ( is_array($arrIDs) ) {
    $arrIDs = $objTmp->sortStructureWithSorting($arrIDs);
}
//CBLog($arrIDs);

$objRoute = CBinitObject("Route");
?>

<div class="table-responsive contentarea" style="padding: 15px; margin-bottom: 15px;">


<table class="table table-sm" id="table_list">
        <thead>
        <tr>
            <th class="cb_table_first_row">ID</th>
            <th>language_id</th>
            <th>parent_id</th>
            <th>previous_id</th>
            <th>name</th>
            <th>template</th>
            <th>addressgroups</th>
            <th>visble</th>
        </tr>
        </thead>
        <?php


        if (is_array($arrIDs) && count($arrIDs) >= 1) {
            foreach ($arrIDs as $run => $arrEntry) {
                //echo "arrEntry<pre>"; print_r($arrEntry); echo "</pre>";

                $objTmp = CBinitObject("Structure",$arrEntry[$objTmp->strPrimaryKey]);
                //$objTmp = new Content($arrEntry["address_id"]);

                //echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";


                $strStyle = "";
                if ($objTmp->getAttribute('active') != "1") $strStyle = "opacity:0.25;";

                $strPaddingLeft = "padding-left:".$arrEntry["level"]."0px;";
                ?>

                <tr class="classid_entry" style="<?php echo $strStyle; ?>"
                    data-id="<?php echo $objTmp->getAttribute($objTmp->strPrimaryKey); ?>">

                    <td>
                        <div>
                            <?php
                            //echo cb_makeCheckboxForm ("save[".($strID)."]","");
                            echo $objTmp->getAttribute($objTmp->strPrimaryKey);
                            ?>
                        </div>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('language_id');
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('parent_id');
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('previous_id');
                        ?>
                    </td>

                    <td style="<?php echo $strPaddingLeft; ?>">
                        <?php
                        echo $objTmp->getAttribute('name');

                        $strRoute = $objRoute->getStructureRoute($objTmp->getAttribute($objTmp->strPrimaryKey));
                        if ( $strRoute != "" ) echo '<br><small><a href="'.BASEURL.$strRoute.'" target="_blank" onclick="event.stopPropagation();">'.$strRoute.'</a></small>';
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('template');
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('addressgroups');
                        ?>
                    </td>

                    <td>
                        <?php
                        echo $objTmp->getAttribute('visible');
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