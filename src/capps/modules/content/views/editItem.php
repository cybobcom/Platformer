<?php

//
// edit
//
//echo "dsf".__FILE__;
//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";

use capps\modules\database\classes\CBObject;

//
$strModuleName = CBgetModuleName("###MODULE###");
//CBLog($strModuleName);



if ( $_REQUEST['id'] != "" ) {
	
	//$objTmp = CBinitObject("Address",$_REQUEST['id']);
    //$objTmp = new CBObject($_REQUEST['id'],"capps_address","address_id");
	//echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";

    //
    $objTmp = CBinitObject(ucfirst($strModuleName),$_REQUEST["id"]);
    //CBLog($objTmp);

    $strEasyAdmin = "";
    if ( $objTmp->getAttribute('template') != "" ) {

        $strTemplate = $objTmp->getAttribute('template');

        if ( is_numeric($strTemplate) ) {
            // TODO load template from database
        } else {
            $file = BASEDIR.$strTemplate;
            if ( is_file($file) ) {
                $strTemplate = file_get_contents($file);
            }
        }

        // localization
        global $arrGlobalTranslation;

        if ( stristr($strTemplate,"</cb:localization>") ) {
            preg_match_all('/<cb:localization(.*)>(.*)<\/cb:localization>/Us', $strTemplate, $arrTmp);
            if (count($arrTmp) >= 1) {
                $strTemplate = str_replace($arrTmp[0][0], "", $strTemplate);
                $strLocalization = $arrTmp[2][0];
                //echo "strLocalization<pre>"; print_r($strLocalization); echo "</pre>";

                $arrLocalization = explode("\n",$strLocalization);
                if ( is_array($arrLocalization) && count( $arrLocalization) >= 1 ) {
                    foreach ( $arrLocalization as $r=>$line ) {
                        $line = trim($line);
                        if ( $line == "" ) continue;
                        if ( $line == " ") continue;
                        if ( stristr($line, "//") ) continue;

                        $arrTmp = explode("|",$line);
                        //echo "arrTmp<pre>"; print_r($arrTmp); echo "</pre>";

                        if ( is_array($arrTmp) && count( $arrTmp) == 3 ) {

                                $l = "".$arrTmp[1]."";
                                if ( $l != "2") continue;

                                $arrGlobalTranslation[$arrTmp[0]] = $arrTmp[2];

                        }

                    }
                }



            }
        }
        //echo "arrGlobalTranslation<pre>"; print_r($arrGlobalTranslation); echo "</pre>";

        // delete easyadmin
        if ( stristr($strTemplate,"</cb:easyadmin>") ) {
            preg_match_all('/<cb:easyadmin(.*)>(.*)<\/cb:easyadmin>/Us', $strTemplate, $arrTmp);
            if (count($arrTmp) >= 1) {
                $strTemplate = str_replace($arrTmp[0][0],"",$strTemplate);
                $strEasyAdmin = $arrTmp[2][0];


                $arrEasyAdminFields = array();

                $arrEasyAdmin = explode("\n",$strEasyAdmin);
                foreach ( $arrEasyAdmin as $arrEasyAdminKey => $strEasyAdminValue ) {
                    $arrField = explode("|",trim($strEasyAdminValue));

                    $dictField = array();
                    $dictField["name"] = $arrField[0] ?? "";
                    $dictField["label"] = $arrField[1] ?? "";
                    $dictField["type"] = $arrField[2] ?? "";

                    $arrEasyAdminFields[] = $dictField;
                }
                //echo "arrEasyAdminFields<pre>"; print_r($arrEasyAdminFields); echo "</pre>";

                //echo "objTmp->arrDatabaseColumns <pre>"; print_r($objTmp->arrDatabaseColumns ); echo "</pre>";
                //
                //
                //
                foreach( $objTmp->arrDatabaseColumns as $arrDatabaseColumn ) {
                    /*
                    [Field] => addressgroup_uid
                    [Type] => int(11)
                    [Null] => NO
                    [Key] => PRI
                    [Default] =>
                    [Extra] => auto_increment
                    */

                    $dictField = array();
                    $dictField["name"] = $arrDatabaseColumn["Field"];
                    $dictField["label"] = localize($arrDatabaseColumn["Field"]);
                    $dictField["type"] = "input";

                    if ( $arrDatabaseColumn["Key"] == "PRI" ) $dictField["type"] = "hidden";

                    if ( $arrDatabaseColumn["Type"] == "text" ) $dictField["type"] = "textarea";
                    if ( $arrDatabaseColumn["Type"] == "longtext" ) $dictField["type"] = "textarea";

                    if ( $arrDatabaseColumn["Type"] == "int(1)" ) $dictField["type"] = "checkbox";

                    //if ( $arrDatabaseColumn["Type"] == "datetime" ) $dictField["type"] = "datetime";

                    $arrEasyAdminFields[] = $dictField;

                }




                //
                //
                //
                $dictEntryTmp = $objTmp->arrAttributes;

                $strForm = "";
                if (is_array($arrEasyAdminFields) && count($arrEasyAdminFields)) {
                    foreach ($arrEasyAdminFields as $dictField) {
                        //echo "dictFormField<pre>"; print_r($dictFormField); echo "</pre>";


                        //<div class="form-floating">
                        //  <input name="password" type="password" class="form-control" id="floatingPassword" placeholder="Password">
                        //  <label for="floatingPassword">Password</label>
                        //</div>

                        //
                        if ($dictField["type"] == "hidden") {
                            $strForm .= CBmakeHiddenForm(array("name" => $dictField["name"], "value" => $dictEntryTmp[$dictField["name"]] ?? "", "placeholder" => $dictField["label"]));
                        }
                        //
                        if ($dictField["type"] == "input") {
                            $strForm .= '<div class="form-floating mb-2">';
                            $strForm .= CBmakeInputForm(array("name" => $dictField["name"], "value" => $dictEntryTmp[$dictField["name"]] ?? "", "placeholder" => $dictField["label"]));
                            $strForm .= '<label for="' . $dictField["name"] . '">' . $dictField["label"] . '</label>';
                            $strForm .= '</div>';
                        }
                        //
                        if ($dictField["type"] == "textarea") {
                            $strForm .= '<div class="form-floating mb-2">';
                            $strForm .= CBmakeTextfieldForm(array("name" => $dictField["name"], "value" => $dictEntryTmp[$dictField["name"]] ?? "", "placeholder" => $dictField["label"], "string" => 'style="height: 200px"'));
                            $strForm .= '<label for="' . $dictField["name"] . '">' . $dictField["label"] . '</label>';
                            $strForm .= '</div>';
                        }
                    }
                }


            }
        }
    }

?>



<div class="modal-header">
  <h5 class="modal-title" id="exampleModalLabel">Eintrag bearbeiten</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
      
      <div class="modal-body">
          <form method="post" id="modal_item_update" class="form-horizontal">
              <?php echo cb_makeHiddenForm ("id",$objTmp->getAttribute($objTmp->strPrimaryKey),"form-control form-control-sm"); ?>

          <table class="table table-sm">
 

<?php

//echo "strEasyAdmin: ".htmlspecialchars($strEasyAdmin);
//echo "strForm: ".$strForm;
?>
  
   <tr class="back8">
  <td>language_id</td>
    <td><?php echo cb_makeInputForm ("save[language_id]",$objTmp->getAttribute('language_id'),"form-control form-control-sm"); ?></td>
  </tr>
  
  <tr class="back8">
  <td>structure_id</td>
    <td><?php echo cb_makeInputForm ("save[structure_id]",$objTmp->getAttribute('structure_id'),"form-control form-control-sm"); ?></td>
  </tr>

    <tr class="back8">
        <td>name</td>
        <td><?php echo cb_makeInputForm ("save[name]",$objTmp->getAttribute('name'),"form-control form-control-sm"); ?></td>
    </tr>

  <tr class="back8">
      <td>content</td>
      <td><?php echo cb_makeTextfieldForm("save[content]",$objTmp->getAttribute('content'),NULL,"form-control form-control-sm"); ?></td>
  </tr>
  
    <tr class="back8">
  <td>template</td>
    <td><?php echo cb_makeInputForm ("save[template]",$objTmp->getAttribute('template'),"form-control form-control-sm"); ?></td>
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
  
    <tr class="back8">
  <td>aktiv</td>
    <td><?php echo cb_makeCheckboxForm ("save[active]",$objTmp->getAttribute('active')); ?></td>	
  </tr>



     
</table>
          </form>
      </div>
      
      <div class="modal-footer">
                   
      <!-- 		<span onclick="deleteItem('<?php echo $objTmp->getAttribute($objTmp->strPrimaryKey); ?>')" class="me-auto bi bi-trash"></span> -->
      
              <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
              <button type="button" class="btn btn-primary classid_button_update" >Speichern</button>
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
            'success': function(result){
                //process here
                //alert( "Load was performed. "+url );

                if (typeof window.mountedApp !== 'undefined') {
                    // vue.js
                    globalDetailModal.hide();
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