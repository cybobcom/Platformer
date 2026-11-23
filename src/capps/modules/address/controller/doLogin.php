<?php

CBAuth('public');

$dictResponse = array();
$dictResponse["response"] = "error";
$dictResponse["description"] = "something went wrong";

// Rate limiting: max 5 failed attempts per 15 minutes per IP + login
// SECURITY: stored in a file (not in the session: deleting the cookie would reset it)
$rateWindow = 15 * 60; // 15 minutes
$rateMax = 5;
$rateFile = dirname(SOURCEDIR) . '/cache/login/' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower((string)($_REQUEST['login'] ?? ''))) . '.json';
$rate = is_file($rateFile) ? (array)json_decode((string)file_get_contents($rateFile), true) : [];
if (time() - ($rate['time'] ?? 0) > $rateWindow) {
    $rate = ['count' => 0, 'time' => time()];
}
if ($rate['count'] >= $rateMax) {
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

    // SECURITY: "NULL" and "NOT ..." have a special meaning in findAll() conditions: never accept them as login
    $isPlainLogin = is_string($login) && $login !== "NULL" && !str_starts_with($login, "NOT ");
    $arrResult = $isPlainLogin ? $objTmp->getAllEntries(NULL,NULL,$arrCondition) : [];

    // Compare password - supports both plaintext (legacy) and encrypted (new)
    if ( is_array($arrResult) && count($arrResult) > 0 && $arrResult[0]["address_uid"] != "" ) {
        $objUser = CBinitObject("Address", $arrResult[0]["address_uid"]);
        $storedPassword = (string)$objUser->getAttribute("password"); // already decrypted on load

        // SECURITY: timing-safe compare
        $passwordMatch = hash_equals($storedPassword, (string)$password);

        // Legacy: plaintext in DB (raw row from findAll) → store encrypted once
        if ( $passwordMatch && !$objUser->isEncrypted((string)$arrResult[0]["password"]) ) {
            $objUser->saveContentUpdate($arrResult[0]["address_uid"], ["password" => $password]);
        }

        if ( !$passwordMatch ) {
            $arrResult = [];
        }
    }

    if ( is_array($arrResult) && count($arrResult) > 0 && $arrResult[0]["address_uid"] != "" ) {

        // Reset rate limit on successful login
        @unlink($rateFile);

        // SECURITY: new session id after login (session fixation)
        session_regenerate_id(true);

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
        $rate['count']++;
        if (!is_dir(dirname($rateFile))) @mkdir(dirname($rateFile), 0775, true);
        file_put_contents($rateFile, json_encode($rate));
        $dictResponse["description"] = "Please check login and password";
    }

} else {
    $dictResponse["description"] = "Please enter login and password";
}

$output = json_encode($dictResponse, JSON_HEX_APOS);

header('Content-Type: application/json');
echo $output;

exit;