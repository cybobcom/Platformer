<?php



$arrGlobalTranslation = array();
/*
// chatoverview
$arrGlobalTranslation["1"]["Themen"] = "Themen";
$arrGlobalTranslation["2"]["Themen"] = "Topics";

$arrGlobalTranslation["1"]["alle Themen"] = "alle Themen";
$arrGlobalTranslation["2"]["alle Themen"] = "all Topics";

$arrGlobalTranslation["1"]["Finden (min. 3 Zeichen, @ für Personen)"] = "Finden (min. 3 Zeichen, @ für Personen)";
$arrGlobalTranslation["2"]["Finden (min. 3 Zeichen, @ für Personen)"] = "Find (min. 3 chars, @ for person)";

$arrGlobalTranslation["1"]["erledigte Aufgaben anzeigen"] = "erledigte Aufgaben anzeigen";
$arrGlobalTranslation["2"]["erledigte Aufgaben anzeigen"] = "Show finished Tasks";

$arrGlobalTranslation["1"]["nicht-sichtbare Aufgaben anzeigen)"] = "nicht-sichtbare Aufgaben anzeigen";
$arrGlobalTranslation["2"]["nicht-sichtbare Aufgaben anzeigen"] = "Show hidden Tasks";

$arrGlobalTranslation["1"]["Teilnehmer ausblenden"] = "Teilnehmer ausblenden";
$arrGlobalTranslation["2"]["Teilnehmer ausblenden"] = "Hide Participants";

$arrGlobalTranslation["1"]["Strukturen ausblenden"] = "Strukturen ausblenden";
$arrGlobalTranslation["2"]["Strukturen ausblenden"] = "Hide Structure";

// notification
$arrGlobalTranslation["1"]["keine aktuelle Informationen"] = "keine aktuelle Informationen";
$arrGlobalTranslation["2"]["keine aktuelle Informationen"] = "No current Information";

$arrGlobalTranslation["1"]["In erweiterte Ansicht wechseln"] = "In erweiterte Ansicht wechseln";
$arrGlobalTranslation["2"]["In erweiterte Ansicht wechseln"] = "In erweiterte Ansicht wechseln";

$arrGlobalTranslation["1"]["keine aktuelle Informationen"] = "keine aktuelle Informationen";
$arrGlobalTranslation["2"]["keine aktuelle Informationen"] = "No current Information";
*/

$file = file_get_contents(CAPPS . "modules/core/localize/localization.txt");
//echo "file<pre>"; print_r($file); echo "</pre>";

$arrFile = explode("\n",$file);
if ( is_array($arrFile) && count( $arrFile) >= 1 ) {
    foreach ( $arrFile as $r=>$line ) {
        if ( $line == "" ) continue;
        if ( $line == " ") continue;
        if ( stristr($line, "//") ) continue;

        $arrTmp = explode("|",$line);
        //echo "arrTmp<pre>"; print_r($arrTmp); echo "</pre>";

        if ( is_array($arrTmp) && count( $arrTmp) >= 1 ) {
            foreach ( $arrTmp as $rT=>$vT ) {

                $l = "".($rT+1)."";
                if ( $rT == "0" ) {
                    $arrGlobalTranslation[$l][$vT] = $vT;
                } else {
                    $arrGlobalTranslation[$l][$arrTmp[0]] = $vT;
                }
            }
        }

    }
}
//CBLog($arrGlobalTranslation);




//
//
//
//if ( isset($objPlattformUser) && $objPlattformUser->getAttribute("data_frontend_language") == "en" ) {
//    $arrGlobalTranslation = $arrGlobalTranslation["lid_2"];
//} else {
//    $arrGlobalTranslation = $arrGlobalTranslation["lid_1"];
//}
$arrGlobalTranslation = $arrGlobalTranslation["2"];

define("GLOBAL_TRANSLATION", $arrGlobalTranslation);
//echo "GLOBAL_TRANSLATION<pre>"; print_r(GLOBAL_TRANSLATION); echo "</pre>";

//
//unset($arrGlobalTranslation);
//unset($arrGlobalTranslationForUser);

//
//
//
/*
if ( is_array($arrGlobalTranslation) && count($arrGlobalTranslation) >= 1 ) {
    //mail("robert.heuer@cybob.com","t",$strTemplate);
    //mail("robert.heuer@cybob.com","C",serialize($objC->arrAttributes));
    //mail("robert.heuer@cybob.com","CS",serialize($objCS->arrAttributes));
    foreach ( $arrGlobalTranslation as $rTranslation=>$vTranslation ) {
        //echo "$run,$value <br>";
        if ( $vTranslation != "" ) $strTemplate = str_replace($rTranslation,$vTranslation,$strTemplate);
    }

}
*/

if ( !function_exists("localize") ) {
    function localize($str){

        global $arrGlobalTranslation;

        if ( isset(GLOBAL_TRANSLATION[$str]) && GLOBAL_TRANSLATION[$str] != "" ) $str = GLOBAL_TRANSLATION[$str];

        if ( isset($arrGlobalTranslation[$str]) && $arrGlobalTranslation[$str] != "" ) $str = $arrGlobalTranslation[$str];

        return $str;
    }
}
?>