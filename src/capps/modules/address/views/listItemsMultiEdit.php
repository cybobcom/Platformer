<?php

$arrPreset = array();

$dictEntry = array();
$dictEntry["type"] = "company";
$dictEntry["name"] = "Unternehmen";
$dictEntry["table_fields"] = array("customer_number"=>"Nummer","company"=>"Unternehmen");
$dictEntry["save_condition"] = array("customer_number");
$dictEntry["save_additional_fields"] = array("active"=>"1","type"=>"company");
$arrPreset["company"] = $dictEntry;

$dictEntry = array();
$dictEntry["type"] = "evaluator";
$dictEntry["name"] = "Prüfer";
$dictEntry["table_fields"] = array("customer_number"=>"Nummer","active"=>"aktiv","gender"=>"Geschlecht","firstname"=>"Vorname","lastname"=>"Nachname","login"=>"Login","password"=>"Password","company"=>"Unternehmen","street"=>"Straße","postcode"=>"PLZ","city"=>"Ort","phone"=>"Telefon","mobile"=>"Mobil","email"=>"E-Mail","addressgroups"=>"Nutzergruppen");
$dictEntry["save_condition"] = array("login");
$dictEntry["save_additional_fields"] = array("active"=>"1","type"=>"evaluator");
$arrPreset["evaluator"] = $dictEntry;

$dictEntry = array();
$dictEntry["type"] = "examinee";
$dictEntry["name"] = "Prüfling";
$dictEntry["table_fields"] = array("login"=>"Identnummer/Login","password"=>"Prüfungsnummer/Passwort","firstname"=>"Vorname","lastname"=>"Nachname","data_specialization"=>"Fachrichtung","data_specialization_short"=>"FR kurz","addressgroups"=>"Nutzergruppen");
$dictEntry["save_condition"] = array("login","password");
$dictEntry["save_additional_fields"] = array("active"=>"1","type"=>"company");
$arrPreset["examinee"] = $dictEntry;

//CBLog($arrPreset);

//
//
//

//CBLog($_REQUEST);exit;

$objAtmp = CBinitObject("Address");

$arrCondition = array();
$arrCondition['type'] = "NEVER";
if( isset($_REQUEST["filter_preset"]) && $_REQUEST["filter_preset"] != "" ) $arrCondition['type'] = $_REQUEST["filter_preset"];

$strOrder = "password";
$arrR = $objAtmp->getAllEntries($strOrder,"ASC",$arrCondition);
//CBLog($arrR);

//
$dictPreset = $arrPreset[$_REQUEST["filter_preset"]];
//CBLog($dictPreset);
?>





            <?php
            $strData = "";
            foreach ( $arrR as $r=>$vR ) {

                $objAtmp = CBinitObject("Address",$vR['address_uid'] ?? "");

                //if ( $objAtmp->getAttribute('data_job') == "" )  continue;

                //echo "[\"".$objAtmp->getAttribute("login")."\",\"".$objAtmp->getAttribute("password")."\",\"".$objAtmp->getAttribute("firstname")."\",\"".$objAtmp->getAttribute("lastname")."\",\"".$objAtmp->getAttribute("data_job")."\"],";

                $strLine = "";
                //CBLog($dictPreset["table_fields"]);

                if ( isset($dictPreset["table_fields"]) && is_array($dictPreset["table_fields"]) ) {
                    foreach ($dictPreset["table_fields"] as $p => $vP) {
                        if ($strLine != "") $strLine .= ",";
                        $strLine .= '"' . $objAtmp->getAttribute($p) . '"';
                    }
                }
                if ( $strLine != "" ) $strData .= '['.$strLine.'],';

            }

            // empty
            //            ["", "","","",""]
            if ( $strData == "" ) {
                $strData = '['.rtrim(str_repeat('"",', count($dictPreset["table_fields"])),",").']';
            }

            //
            // table header
            //
            $arrFieldNames = array_values($dictPreset["table_fields"] ?? array());
            //CBLog($arrFieldNames);

            $strFieldNames = "";
            foreach ( $arrFieldNames as $fieldname ) {
                if ( $strFieldNames != "" ) $strFieldNames .= ",";
                $strFieldNames .= '"'.$fieldname.'"';
            }
            if ( $strFieldNames != "" ) {
                $strFieldNames = '['.$strFieldNames.']';
            } else {
                $strFieldNames = '['.rtrim(str_repeat('"",', count($arrFieldNames)),",").']';
            }
            //CBLog($strFieldNames);

            ?>

<script>

            var data = [
                <?php echo $strData; ?>
            ];

        //
        $("#dataTable").handsontable({
            data: data,
//     colHeaders: ["Original", "Ziel","2"],
// 	colWidths: [100, 500,100],
            stretchH: 'all',
            minSpareRows: 5
        });

        //
        $("#dataTable").handsontable({
            startRows: 8,
            startCols: 6,
            //colHeaders: ["Identnummer/Login", "Prüfungsnummer/Passwort", "Vorname", "Nachname", "Fachrichtung"],
            colHeaders: <?php echo $strFieldNames; ?>,
            minSpareRows: 3,
            contextMenu: true,
            afterChange: function (change, source) {
                if (source === 'loadData') {
                    return; //don't save this change
                }
            }
        });

        //
        var handsontable = $("#dataTable").data('handsontable');

    </script>


