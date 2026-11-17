<?php

CBAuth('public');

$_SESSION[PLATFORM_IDENTIFIER]["login_verified"] = "";
unset($_SESSION[PLATFORM_IDENTIFIER]["login_verified"]);

$_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"] = "";
unset($_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"]);

// SECURITY: new session id after logout
session_regenerate_id(true);

//
exit;

?>