<?php




$dictResponse = array();
$dictResponse["response"] = "error";
$dictResponse["description"] = "something went wrong";

//
if ( isset($_REQUEST["login"]) && $_REQUEST["login"] != "" && isset($_REQUEST["password"]) && $_REQUEST["password"] != "") {

    $login = $_REQUEST["login"];
    $password = $_REQUEST["password"];

    //
    $objTmp = CBinitObject("Address");
    //CBLog($objTmp);


    $arrCondition = array();
    $arrCondition["active"] = "1";
    $arrCondition["login"] = $_REQUEST["login"];
    $arrCondition["password"] = $_REQUEST["password"];

    $arrResult = $objTmp->getAllEntries(NULL,NULL,$arrCondition);


    /*
    $sql  = "SELECT address_uid, customer_number FROM capps_address WHERE login = '".mysqli_real_escape_string($objTmp->objDatabase->intDBHandler,$login)."' AND password = '".mysqli_real_escape_string($objTmp->objDatabase->intDBHandler,$password)."' AND active = 1";
    $arrResult = $objTmp->query($sql);
    */

    if ( is_array($arrResult) && count($arrResult) > 0 && $arrResult[0]["address_uid"] != "" ) {

        //
        $_SESSION[PLATFORM_IDENTIFIER]["login_verified"] = "1";
        $_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"] = $arrResult[0]["address_uid"];

        //
        $dictResponse["response"] = "success";
        $dictResponse["description"] = "login successful";

        //
        $objTmp = CBinitObject("Address",$arrResult[0]["address_uid"]);

        $arrSave = array();
        $arrSave["date_lastlogin"] = date("Y-m-d H:i:s");

        $objTmp->saveContentUpdate($arrResult[0]["address_uid"],$arrSave);

    } else {
        $dictResponse["description"] = "Please check login and password";
    }


} else {
    $dictResponse["description"] = "Please enter login and password";
}

$output = json_encode($dictResponse, JSON_HEX_APOS);

header('Content-Type: application/json');
echo $output;


//
exit;

?>