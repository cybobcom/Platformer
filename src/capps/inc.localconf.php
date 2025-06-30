<?php

//
//
//

//
ini_set('max_execution_time', 60);
ini_set('memory_limit', '512M');

//
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 'on'); //2020-07-27 bob : set this to avoid warnings - disable line for develop
error_reporting(E_ALL ^ E_NOTICE);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);


//
//
//

$arrConf = array();
$arrConf['plattform_name'] = "Admin";
$arrConf['plattform_login'] = "dev25elop";
$arrConf['plattform_password'] = "de06velop";
$strPlattformIdentitier = realpath(dirname(__FILE__)) . "/";
$arrConf['plattform_identifier'] = md5($strPlattformIdentitier);

$arrConf['baseurl'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "";
$arrConf['baseurl'] = rtrim($arrConf['baseurl'], '/') . '/';
$arrConf['baseurl'] = str_replace("admin/", "", $arrConf['baseurl']);
$arrConf['baseurl'] = str_replace("console/", "", $arrConf['baseurl']);
//$arrConf['basedir'] = $_SERVER["DOCUMENT_ROOT"] . "/" . explode('/', $_SERVER["SCRIPT_URL"])[1] . "/";
$arrConf['basedir'] = $_SERVER["DOCUMENT_ROOT"] . "/";
$arrConf['sourcedir'] = str_replace("capps", "", realpath(dirname(__FILE__))) . "";
$arrConf['securedir'] = str_replace("/src/","/websecure/",$arrConf['sourcedir']);
$arrConf["capps"] = $arrConf['sourcedir'] . "capps/";
$arrConf["custom"] = $arrConf['sourcedir'] . "custom/";

$arrConf["storage_identifier"] = "id";
//$arrConf["storage_filename"] = "data/data_admin.xml";
$arrConf["storage_configuration"] = "plattform_name,chat_intro,chat_overflow,chat_question_placeholder,chat_prewritten_questions,chat_disclaimer,role_system,tonality_serios,tonality_sophisticated,tonality_youthful,openai_api_key,modal_privacy";
$arrConf["storage_configuration_filename"] = "data/configuration.xml";
//CBLog($arrConf);

//
//
//
if ($arrConf["storage_configuration"] != "" && $arrConf["storage_configuration_filename"] != "") {

	//
	if (is_file($arrConf['basedir'] . "" . $arrConf["storage_configuration_filename"])) {
		$file = file_get_contents($arrConf['basedir'] . "" . $arrConf["storage_configuration_filename"]);

		//
		$arrEntries = array();
		if ($file != "") {
			$arrEntries = parseCBXML($file);

			if (is_array($arrEntries) && count($arrEntries)) {

				$dictEntry = $arrEntries[0];
				foreach ($dictEntry as $key => $value) {
					$arrConf[$key] = $value;
				}
			}
		}
	}

}
//echo "arrConf<pre>"; print_r($arrConf); echo "</pre>";



// conf to constant
foreach ($arrConf as $key => $value) {
	if (!is_array($value)) {
		define(strtoupper($key), $value);
	}
}
//echo "BASEURL: ".BASEURL."<br>"; echo "BASEDIR: ".BASEDIR; exit;


// define configuration as constant with array to make isset() possible
define(strtoupper("configuration"), $arrConf);
//echo "<pre>-"; print_r(CONFIGURATION); echo "-</pre>";//exit;




// Vereinfachte Konfiguration
global $CBINIT_CONFIG;
$CBINIT_CONFIG = [
	'vendors' => [
		'capps' => [
			'path' => SOURCEDIR . 'capps/',
			'priority' => 100,
			'enabled' => true
		],
		'custom' => [
			'path' =>  SOURCEDIR . 'custom/', 
			'priority' => 200,
			'enabled' => true
		]
	],
	'validate_input' => true,
	'enable_cache' => true,
	'enable_logging' => true,
	'fallback_enabled' => true,
	'fallback_class' => 'capps\\modules\\database\\classes\\CBObject',  // Angepasst!
	'strict_mode' => false
];
echo "<pre>"; print_r($CBINIT_CONFIG); echo "</pre>";

global $CBINIT_CACHE, $CBINIT_STATS;
$CBINIT_CACHE = [];
$CBINIT_STATS = ['hits' => 0, 'misses' => 0, 'fallbacks' => 0, 'vendor_usage' => []];



//
// encryption	
//
define("ENCRYPTION_KEY32", "platf202506300000000000000000000");



//
// db
//
$arrDatabaseConfiguration = array();
$arrDatabaseConfiguration['DB_HOST'] = "localhost";
$arrDatabaseConfiguration['DB_USER'] = "root";
$arrDatabaseConfiguration['DB_PASSWORD'] = "root";
$arrDatabaseConfiguration['DB_DATABASE'] = "platformer";
//$arrDatabaseConfiguration['DB_PORT'] = "";
$arrDatabaseConfiguration['DB_CHARSET'] = "utf8";
define("DATABASE", $arrDatabaseConfiguration);
//echo "DATABASE<pre>"; print_r(DATABASE); echo "</pre>";


//
// mail
//
//
$arrMailConfiguration = array();
$arrMailConfiguration['name'] = "";
$arrMailConfiguration['login'] = "";
$arrMailConfiguration['password'] = "";
$arrMailConfiguration['email'] = "";
$arrMailConfiguration['server'] = "";
$arrMailConfiguration['port'] = "110";
define("MAIL", $arrMailConfiguration);


//
// debug mail
//
define("DEBUG_MAIL", "robert.heuer@cybob.com");



//
//
//
// for jquery
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=utf-8');


		
		
	
