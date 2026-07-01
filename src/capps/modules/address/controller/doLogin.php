<?php

CBAuth('public');

$dictResponse = array();
$dictResponse["response"] = "error";
$dictResponse["description"] = "something went wrong";

// Rate limiting: max 5 attempts per 15 minutes per IP
$rateKey = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateWindow = 15 * 60; // 15 minutes
$rateMax = 5;

if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'time' => time()];
}
if (time() - $_SESSION[$rateKey]['time'] > $rateWindow) {
    $_SESSION[$rateKey] = ['count' => 0, 'time' => time()];
}
if ($_SESSION[$rateKey]['count'] >= $rateMax) {
    $dictResponse["description"] = "Too many login attempts. Please try again in 15 minutes.";
    header('Content-Type: application/json');
    echo json_encode($dictResponse, JSON_HEX_APOS);
    exit;
}

//
if ( isset($_REQUEST["login"]) && $_REQUEST["login"] != "" && isset($_REQUEST["password"]) && $_REQUEST["password"] != "") {

    $login = $_REQUEST["login"];
    $password = $_REQUEST["password"];

    //
    $objTmp = CBinitObject("Address");

    $arrCondition = array();
    $arrCondition["active"] = "1";
    $arrCondition["login"] = $_REQUEST["login"];

    $arrResult = $objTmp->getAllEntries(NULL,NULL,$arrCondition);

    // Compare password - supports both plaintext (legacy) and encrypted (new)
    if ( is_array($arrResult) && count($arrResult) > 0 && $arrResult[0]["address_uid"] != "" ) {
        $objUser = CBinitObject("Address", $arrResult[0]["address_uid"]);
        $storedPassword = $objUser->getAttribute("password");

        $passwordMatch = false;

        if ( $objUser->isEncrypted($storedPassword) ) {
            // New: encrypted → decryptValue already done by getAttribute
            $passwordMatch = ( $storedPassword === $password );
        } else {
            // Legacy: plaintext → compare directly, then migrate to encrypted
            $passwordMatch = ( $storedPassword === $password );
            if ( $passwordMatch ) {
                $objUser->saveContentUpdate($arrResult[0]["address_uid"], ["password" => $password]);
            }
        }

        if ( !$passwordMatch ) {
            $arrResult = [];
        }
    }

    if ( is_array($arrResult) && count($arrResult) > 0 && $arrResult[0]["address_uid"] != "" ) {

        // Reset rate limit on successful login
        $_SESSION[$rateKey] = ['count' => 0, 'time' => time()];

        $_SESSION[PLATFORM_IDENTIFIER]["login_verified"] = "1";
        $_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"] = $arrResult[0]["address_uid"];
        $_SESSION[PLATFORM_IDENTIFIER]["last_activity"] = time();

        $dictResponse["response"] = "success";
        $dictResponse["description"] = "login successful";

        $objTmp = CBinitObject("Address",$arrResult[0]["address_uid"]);
        $arrSave = array();
        $arrSave["date_lastlogin"] = date("Y-m-d H:i:s");
        $objTmp->saveContentUpdate($arrResult[0]["address_uid"],$arrSave);

    } else {
        // Increment rate limit counter on failed attempt
        $_SESSION[$rateKey]['count']++;
        $dictResponse["description"] = "Please check login and password";
    }

} else {
    $dictResponse["description"] = "Please enter login and password";
}

$output = json_encode($dictResponse, JSON_HEX_APOS);

header('Content-Type: application/json');
echo $output;

exit;