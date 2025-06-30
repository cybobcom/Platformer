<?php

/**
 * Improved core.php using CBAutoloader and CoreBootstrap
 * 
 * This file replaces the original core.php with:
 * - Clean separation of concerns
 * - Security improvements (NO eval!)
 * - Performance optimizations
 * - Maintainable code structure
 * - CBAutoloader integration
 */

 
 //
 // Autoloader setup (KORRIGIERTE VERSION)
 //
 require_once CAPPS . "modules/core/classes/CBAutoloader.php";
 
 $autoloader = new \capps\modules\core\classes\CBAutoloader();
 $autoloader->register();
 
 // KORRIGIERT: Register both lowercase AND uppercase namespaces
 foreach(glob(CAPPS.'modules/*', GLOB_ONLYDIR) as $dir) {
	 $dirName = str_replace(CAPPS.'modules/', '', $dir);
	 
	 // Original lowercase (für Backward Compatibility)
	 $autoloader->addNamespace('capps\\modules\\'.$dirName."\\classes\\", CAPPS . 'modules/'.$dirName."/classes");
	 
	 // Neue uppercase Namespaces
	 $autoloader->addNamespace('Capps\\Modules\\'.ucfirst($dirName)."\\Classes\\", CAPPS . 'modules/'.$dirName."/classes");
 }
 
 foreach(glob(CUSTOM.'modules/*', GLOB_ONLYDIR) as $dir) {
	 $dirName = str_replace(CUSTOM.'modules/', '', $dir);
	 
	 // Original lowercase  
	 $autoloader->addNamespace('custom\\modules\\'.$dirName."\\classes\\", CUSTOM . 'modules/'.$dirName."/classes");
	 
	 // Neue uppercase
	 $autoloader->addNamespace('Custom\\Modules\\'.ucfirst($dirName)."\\Classes\\", CUSTOM . 'modules/'.$dirName."/classes");
 }
 
 // Explizite Registrierung für die wichtigsten Module
 $autoloader->addNamespace('Capps\\Modules\\Database\\Classes\\', CAPPS . 'modules/database/classes/');
 $autoloader->addNamespace('Capps\\Modules\\Core\\Classes\\', CAPPS . 'modules/core/classes/');

//
// Import required classes
//
use capps\modules\core\classes\{CoreBootstrap, Profiler};
use capps\modules\database\classes\{CBDatabase, CBObject};
use capps\modules\address\classes\Address;

//
// System attributes (preserved from original)
//
$coreArrSystemAttributes = array();
$coreArrSystemAttributes["seo_modrewrite"] = "1";

//
// User initialization (preserved logic, cleaned up)
//
$objPlattformUser = new Address($_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"] ?? "");

// Email validation and assignment (from original core.php)
$user_email = $objPlattformUser->getAttribute("login");
if (!validateEmail($user_email)) {
	if ($objPlattformUser->getAttribute("email") != "") {
		$user_email = $objPlattformUser->getAttribute("email");
	}
}
$objPlattformUser->setAttribute("user_email", $user_email);

//
// Localization (preserved from original)
//
require_once CAPPS . "modules/core/localization.php";



//
// Structure initialization (simplified and cached)
//
$objStructure = CBinitObject("Structure");
//print_r($objStructure);exit;
//$objStructure = new CBObject(NULL,"capps_content_structure","structure_id");
$coreArrSortedStructure = $objStructure->generateSortedStructure();

//
// Route cache generation (optimized from original)
//
$lid = 1;
$sql = "SELECT * FROM capps_route WHERE language_id = " . $lid . " AND (data NOT LIKE '%<manual_link><![CDATA[1]]></manual_link>%' or data IS NULL) ORDER BY content_id ASC";

$coreArrRoute = $objStructure->get($sql);
$arrIDtmp = array();
if (is_array($coreArrRoute)) {
	foreach ($coreArrRoute as $run => $value) {
		$tmp = $value['structure_id'] . ':' . $value['content_id'] . ':' . $value['address_uid'];
		$arrIDtmp[$tmp] = $value['route'];
	}
}
$coreArrRoute = $arrIDtmp;
unset($arrIDtmp);

//
// MAIN APPLICATION EXECUTION
// This replaces the complex routing logic with clean, secure bootstrap
//
try {
	// Initialize and run the improved core bootstrap
	$coreBootstrap = new CoreBootstrap();
	
	// Set global variables for backward compatibility
	$GLOBALS['coreArrSystemAttributes'] = $coreArrSystemAttributes;
	$GLOBALS['objPlattformUser'] = $objPlattformUser;
	$GLOBALS['coreArrSortedStructure'] = $coreArrSortedStructure;
	$GLOBALS['coreArrRoute'] = $coreArrRoute;
	
	// Run the application (replaces all the complex if/else logic)
	$coreBootstrap->run();
	
} catch (\Throwable $e) {
	// Global error handler
	error_log('Critical error in core.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
	
	if (defined('DEBUG_MODE') && DEBUG_MODE) {
		echo '<h1>Critical Error</h1>';
		echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
		echo '<p><strong>File:</strong> ' . $e->getFile() . ' (Line: ' . $e->getLine() . ')</p>';
		echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
	} else {
		http_response_code(500);
		echo 'Internal Server Error';
	}
	
	exit;
}




/**
 * Performance Monitoring (optional)
 */
if (defined('ENABLE_PROFILING') && ENABLE_PROFILING) {
	register_shutdown_function(function() {
		$profiler = new Profiler();
		$profiler->recordExecutionEnd();
		
		if (defined('DEBUG_MODE') && DEBUG_MODE) {
			$stats = $profiler->getStats();
			error_log('Page execution: ' . $stats['execution_time'] . 's, Memory: ' . $stats['memory_usage']);
		}
	});
}