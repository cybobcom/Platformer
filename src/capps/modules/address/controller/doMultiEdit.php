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


$dictPreset = $arrPreset[$_REQUEST["filter_preset"]];
$arrTableFieldKeys = array_keys($dictPreset["table_fields"]);



    //
    // init
    //
    $objA = CBinitObject("Address");



    //
    // actions
    //

    if ( isset($_REQUEST['multiedit']) && $_REQUEST['multiedit'] != "" && isset($_REQUEST['filter_preset']) && $_REQUEST['filter_preset'] != "" ) {

        //debug_print_r($_REQUEST);
        //echo "<pre>"; print_r($_REQUEST); echo "</pre>";

        $arrLines = explode("\n",$_REQUEST['multiedit']);

        if ( is_array($arrLines) && count($arrLines) >= 1 ) {
            foreach ( $arrLines as $rL=>$vL ) {

                // do not edit user with demo
                if ( stristr($vL, "demo") ) continue;

                //
                $arrRow = explode("\t", $vL);

                if ( is_array($arrRow) && count($arrRow) >= 1 ) {

                    if ( $arrRow[0] == "" ) continue;

                    //echo "<pre>"; print_r($arrRow); echo "</pre>";

//                    $strLogin = $arrRow[0];
//                    $strPassword = $arrRow[1];
//                    $strFirstname = $arrRow[2];
//                    $strLastname = $arrRow[3];
//                    $strDataJob = $arrRow[4];

                    //

                    //
                    $arrSave = array();

                    // additional fields
                    foreach($dictPreset["save_additional_fields"] as $k=>$v) {
                        $arrSave[$k] = $dictPreset[$v];
                    }

//                    $arrSave['login'] = $strLogin;
//                    $arrSave['password'] = $strPassword;
//                    $arrSave['firstname'] = $strFirstname;
//                    $arrSave['lastname'] = $strLastname;
//                    $arrSave['data_job'] = $strDataJob;

                    // columns
                    $c = 0;
                    foreach($dictPreset["table_fields"] as $k=>$v) {
                    $arrSave[$k] = $arrRow[$c] ?? "";
                        $c += 1;
                    }

                    // general
                    $arrSave['type'] = $dictPreset["type"];
                    $arrSave['source'] = "multiedit_".date("Ymd");
                    if ( !in_array("active",$arrSave) ) $arrSave['active'] = "1";

                    //echo "<pre>"; print_r($arrSave); echo "</pre>";
                    //mail("robert.heuer@cybob.com","D",print_r($arrSave,true)); exit;


                    // get address_uid
                    $arrCondition = array();
                    //$arrCondition['login'] = $strLogin;
                    //$arrCondition['password'] = $strPassword;
                    foreach($dictPreset["save_condition"] as $k=>$v){
                        $arrCondition[$v] = "NEVER";
                        if ( $arrSave[$v] != "" ) $arrCondition[$v] = $arrSave[$v];
                    }
                    //mail("robert.heuer@cybob.com","Dc",print_r($arrCondition,true)); exit;

                    // condition should not to be NEVER (endless new entries)
                    if ( in_array("NEVER",array_values($arrCondition)) ) continue;

                    //
                    $arrAddress = $objA->getAllEntries(NULL,NULL,$arrCondition);

                    if ( is_array($arrAddress) && count($arrAddress) >= 1 ) {
                        echo "update<br />";

                        $arrSave["date_updated"] = date("Y-m-d H:i:s");

                        $address_uid = $arrAddress[0]['address_uid'];

                        //
                        $objAtmp = CBinitObject("Address",$address_uid);
                        $objAtmp->saveContentUpdate($objAtmp->getAttribute('address_uid'),$arrSave);


                    } else {
                        $arrSave["date_created"] = date("Y-m-d H:i:s");

                        echo "new<br />";
                        $id = $objA->saveContentNew($arrSave);

                    }

                }

            }
        }



    }



?>