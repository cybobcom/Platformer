<?php

//
// ini set
//

//
ini_set('max_execution_time', 60);
ini_set('memory_limit', '512M');

//
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 'on'); //2020-07-27 bob : set this to avoid warnings - disable line for develop
//error_reporting(E_ALL ^ E_NOTICE);
//error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

if ( isset($_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"]) && $_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"] != "" ) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}



//
// autoloader
//
require_once CAPPS . "modules/core/classes/CBAutoloader.php";

//
$autoloader = new \capps\modules\core\classes\CBAutoloader();
$autoloader->register();

// Füge deine Namensräume und Basispfade hinzu
//$autoloader->addNamespace('capps\\', __DIR__ . '/capps');
//$autoloader->addNamespace('Vendor\\Package\\', __DIR__ . '/vendor/package/src');
foreach(glob(CAPPS.'modules/*', GLOB_ONLYDIR) as $dir) {
    $dir = str_replace(CAPPS.'modules/', '', $dir);
    $autoloader->addNamespace('capps\\modules\\'.$dir."\\classes\\", CAPPS . 'modules/'.$dir."/classes");
}
foreach(glob(CUSTOM.'modules/*', GLOB_ONLYDIR) as $dir) {
    $dir = str_replace(CUSTOM.'modules/', '', $dir);
    $autoloader->addNamespace('custom\\modules\\'.$dir."\\classes\\", CUSTOM . 'modules/'.$dir."/classes");
}
foreach(glob(AGENT.'modules/*', GLOB_ONLYDIR) as $dir) {
    $dir = str_replace(AGENT.'modules/', '', $dir);
    $autoloader->addNamespace('agent\\modules\\'.$dir."\\classes\\", AGENT . 'modules/'.$dir."/classes");
}
//CBLog($autoloader);

//
use capps\modules\core\classes\Profiler;



//
//
//
$coreArrSystemAttributes = array();
$coreArrSystemAttributes["seo_modrewrite"] = "1";


//
$objPlattformUser = new \capps\modules\address\classes\Address($_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"] ?? "");

//
$user_email = $objPlattformUser->getAttribute("login");
if ( !validateEmail($user_email) ) {
    if ( $objPlattformUser->getAttribute("email") != "" ) {
        $user_email = $objPlattformUser->getAttribute("email");
    }
}
$objPlattformUser->setAttribute("user_email", $user_email);
//CBLog($objPlattformUser);


//
// localization
//
require_once CAPPS . "modules/core/localization.php";
//echo "GLOBAL_TRANSLATION<pre>"; print_r(GLOBAL_TRANSLATION); echo "</pre>";
//echo "arrGlobalTranslation<pre>"; print_r($arrGlobalTranslation); echo "</pre>";

//
// structure sorted
//
$objStructure = CBinitObject("Structure");
$coreArrSortedStructure = $objStructure->generateSortedStructure();
//CBLog($coreArrSortedStructure);


//$t = $objCS->generateCoreNavMap($coreArrGlobalSIDs,$coreArrGlobalSortedStructure);
//echo "<pre>"; print_r($t); echo "</pre>";
//echo microtime()."<br>";

//
// cachemap
//
$lid = 1;
$sql = "SELECT * FROM capps_route WHERE language_id = " .$lid." AND (data NOT LIKE '%<manual_link><![CDATA[1]]></manual_link>%' or data IS NULL) ORDER BY content_id ASC";

$coreArrRoute = $objStructure->get($sql);
//echo "<pre>"; print_r($coreArrCachemap); echo "</pre>";
$arrIDtmp = array();
if ( is_array($coreArrRoute) ) {
    foreach ( $coreArrRoute as $run=>$value ) {
        //echo $tmp;

        $tmp = $value['structure_id'].':'.$value['content_id'].':'.$value['address_id'];
        $arrIDtmp[$tmp] = $value['route'];

        //}
    }
}
$coreArrRoute = $arrIDtmp;
unset($arrIDtmp);
//CBLog($coreArrRoute);



//
//
//

//
$arrCBroute = array();
if ( isset($_REQUEST['CBroute']) ) {
    $arrCBroute = explode("/", $_REQUEST['CBroute']);
}
//echo "<pre>"; print_r($_SERVER); echo "</pre>";
//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
//echo "<pre>"; print_r($arrCBroute); echo "</pre>";

/*
Array
(
    [0] => interface
    [1] => ChatBot
    [2] => getContext
)
Array
(
    [0] => custom
    [1] => controller
    [2] => address
    [3] => setConfirmation
    [4] =>
)
 */
// 0: type
// 1: module
// 2: view/control/interface ...

$strType = "";
if ( isset($arrCBroute[0]) && $arrCBroute[0] != "" ) $strType = strtolower($arrCBroute[0]);

$strModule = "home";
if ( isset($_SERVER["REQUEST_URI"]) && $_SERVER["REQUEST_URI"] == "/admin/" ) {
    $strModule = "admin";
    $_REQUEST['CBroute'] = "admin";
}
if ( isset($_SERVER["REQUEST_URI"]) && $_SERVER["REQUEST_URI"] == "/console/" ) {
    $strModule = "console";
    $_REQUEST['CBroute'] = "console";
}
if ( isset($arrCBroute[1]) && $arrCBroute[1] != "" ) $strModule = strtolower($arrCBroute[1]);

//
$strOption = "";
if ( isset($arrCBroute[2]) && $arrCBroute[2] != "" ) $strOption = $arrCBroute[2];
if ( $strOption != "" ) $strOption = str_replace("_", "/", $strOption);

$strOptionCustom = "";
if ( isset($arrCBroute[3]) && $arrCBroute[3] != "" ) $strOptionCustom = $arrCBroute[3];

$strOptionAgent= "";
if ( isset($arrCBroute[3]) && $arrCBroute[3] != "" ) $strOptionAgent = $arrCBroute[3];

//
$strScript = CAPPS."modules/".$strModule."/".$strType."/".$strOption.".php";
//$strScriptCustom = SOURCEDIR."".$strType."/".$strOption."/".$strModule."/".$strOptionCustom.".php";
$strScriptCustom = SOURCEDIR."".$strType."/modules/".$strOption."/".$strModule."/".$strOptionCustom.".php";
$strScriptAgent = SOURCEDIR."".$strType."/modules/".$strOption."/".$strModule."/".$strOptionAgent.".php";

//echo "<pre>"; print_r($strScriptCustom); echo "</pre>";//exit;
//echo "<pre>"; print_r($strScriptAgent); echo "</pre>";//exit;

// check if script is available
/*
if ( $strModule == "home" ) {
    //echo "DEV home";exit;
} else
    */
if ( is_file($strScript) || is_file($strScriptCustom) || is_file($strScriptAgent) ) {

    //
    if ( is_file($strScriptCustom) ) $strScript = $strScriptCustom;
    if ( is_file($strScriptAgent) ) $strScript = $strScriptAgent;

    if ( $strOption == "main" || stristr($strOption,"main_") ) {


        $template = file_get_contents(BASEDIR."data/template/views/mastertemplate.html");
        $template = str_replace("###part_content###", file_get_contents($strScript), $template);

        $template = str_replace("###RANDOM###", time(), $template);
        $template = str_replace("###BASEURL###", BASEURL, $template);
        $template = str_replace("###BASEDIR###", BASEDIR, $template);
        $template = str_replace("###MODULE###", $strModule, $template);

        //echo $template;
        eval('?>'.$template);

        exit;
    }

    if ( $strOption == "admin" || stristr($strOption,"admin_") ) {


        $template = file_get_contents(BASEDIR."data/template/views/mastertemplate_admin_V1.html");
        $template = str_replace("###part_content###", file_get_contents($strScript), $template);

        $template = str_replace("###RANDOM###", time(), $template);
        $template = str_replace("###BASEURL###", BASEURL, $template);
        $template = str_replace("###BASEDIR###", BASEDIR, $template);
        $template = str_replace("###MODULE###", $strModule, $template);

        //echo $template;
        eval('?>'.$template);

        exit;
    }


    //
    // partials / comands
    //

//            include($strScript);
//            exit();

    $template = "###part_content###";
    $template = str_replace("###part_content###", file_get_contents($strScript), $template);

    $template = str_replace("###RANDOM###", time(), $template);
    $template = str_replace("###BASEURL###", BASEURL, $template);
    $template = str_replace("###BASEDIR###", BASEDIR, $template);
    $template = str_replace("###MODULE###", $strModule, $template);

    $translations = GLOBAL_TRANSLATION;
    // Use a regular expression to find and replace the localized string
    $template = preg_replace_callback(
        '/<cb:localize>(.*?)<\/cb:localize>/',
        function ($matches) use ($translations) {
            // Return the translation if it exists, otherwise return the original string
            return $translations[$matches[1]] ?? $matches[1];
        },
        $template
    );


    if ( defined('CAPPS') ) $template = str_replace("###capps###",CAPPS,$template);
    if ( defined('CAPPS') ) $template = str_replace("###CAPPS###",CAPPS,$template);

    //echo $template;
    eval('?>'.$template);

    exit;



} else {

    //
    //
    //
    //CBLog($_REQUEST);exit;

    // start
    $structure_id = current($coreArrSortedStructure)["structure_id"] ?? "1";
    //CBLog($structure_id);exit;

    //
    $objRoute = CBinitObject("Route");
    if ( isset($_REQUEST['CBroute']) && $_REQUEST['CBroute'] != "" ) {
        $objRoute = $objRoute->getObjectFromRoute(trim($_REQUEST['CBroute'] ?? "", "/") . "/");
    }
    //CBLog($objRoute);exit;

    if ( $objRoute ) {
        if ($objRoute->getAttribute("structure_id") != "") $structure_id = $objRoute->getAttribute("structure_id");

        // set request
        if ($objRoute->getAttribute("language_id") != "") $_REQUEST["language_id"] = $objRoute->getAttribute("language_id");
        if ($objRoute->getAttribute("structure_id") != "") $_REQUEST["structure_id"] = $objRoute->getAttribute("structure_id");
        if ($objRoute->getAttribute("content_id") != "") $_REQUEST["content_id"] = $objRoute->getAttribute("content_id");
    } else {

        //
        // check agent
        //

        $strRoute = $_REQUEST['CBroute'];
        if ( strstr($_REQUEST['CBroute'],"/api") ) {
            $arrRoute = explode("/api",$_REQUEST['CBroute']);
            $strRoute = $arrRoute[0];
        }
        $strRoute = trim($strRoute,"/");

        $objAgent = CBinitObject("Agent","deeplink:".$strRoute ?? "");
        //CBLog($objAgent);exit;

        if ( $objAgent->getAttribute("agent_uid") != "") {
            //CBLog($objAgent);

            // agent type
            $objAgentType = CBinitObject("AgentType");
            if ( $objAgent->getAttribute("agent_type_uid") != "") {
                $objAgentType = CBinitObject("AgentType",$objAgent->getAttribute("agent_type_uid"));
            }
            //CBLog($objAgentType);

            // get agent configuration
            $objAgentConfiguration = CBinitObject("AgentConfiguration");
            if ( $objAgent->getAttribute("agent_configuration_uid") != "") {
                $objAgentConfiguration = CBinitObject("AgentConfiguration",$objAgent->getAttribute("agent_configuration_uid"));
            }
            //  agent configuration of user (for development reason)
            $boolIsUsersAgentConfiguration = false;
            $settings = "settings_agent_".$objAgent->getAttribute("agent_uid")."_configuration";
            if ( $objPlattformUser->getAttribute($settings) != "") {
                $objAgentConfiguration = CBinitObject("AgentConfiguration",$objPlattformUser->getAttribute($settings));
                $boolIsUsersAgentConfiguration = true;
            }
            //CBLog($objAgentConfiguration);

            // load main
            $boolIsApiCall = false;
            $path = BASEDIR."../src/agent/".$objAgentType->getAttribute("entity")."/".$objAgentType->getAttribute("version")."/views/main.php";
            // api
            if ( strstr($_REQUEST['CBroute'],"/api") ) {
                $boolIsApiCall = true;
                $path = BASEDIR."../src/agent/".$objAgentType->getAttribute("entity")."/".$objAgentType->getAttribute("version")."/api/api.php";
            }
            //echo $path;

            if ( is_file($path) ) {
                include($path);
            } else {
                echo "Path not found";
                exit;
            }

            if ( !$boolIsApiCall ) {
                //
                $template = "";

                $path = BASEDIR . "agent/" . $objAgentType->getAttribute("entity") . "/" . $objAgentType->getAttribute("version") . "/views/mastertemplate.php";
                //echo $path;
                if (is_file($path)) {
                    $template = file_get_contents($path);
                }

                //
                //$template = str_replace("###part_content###", file_get_contents($strScript), $template);
                $template = str_replace("###RANDOM###", time(), $template);
                $template = str_replace("###BASEURL###", BASEURL, $template);
                $template = str_replace("###BASEDIR###", BASEDIR, $template);
                $template = str_replace("###MODULE###", $strModule, $template);

                //echo $template;
                eval('?>' . $template);

            }

            //
            exit;

        }



        //
        // check tool
        //
        //    [CBroute] => tool/graburl
        if (strpos($_REQUEST['CBroute'] ?? "", "tool/") === 0) {
            //
            $strTool = str_replace("tool/", "", $_REQUEST['CBroute'] ?? "");

            $objAgentTool = CBinitObject("AgentTool","entity:".$strTool);

            if ( $objAgentTool->getAttribute("agent_tool_uid") != "") {

                //
                $path = CAPPS."modules/agenttool/tools/".$objAgentTool->getAttribute("entity")."/".$objAgentTool->getAttribute("version")."/controller/".$objAgentTool->getAttribute("entity").".php";
                //CBLog($path);

                //
                if ( is_file($path) ) {
                    include($path);
                }

                //
                exit;

            }

        }



    }

    $objStructure = CBinitObject("Structure",$structure_id);
    if ( !isset($_REQUEST['structure_id']) ) {
        $_REQUEST['structure_id'] = $structure_id;
    }
    //CBLog($objStructure);
    //CBLog($_SESSION);

    //
    $boolCheckIntersection = true;
    if ( $objStructure->getAttribute("addressgroups") != "" ) {
        $boolCheckIntersection = checkIntersection($objPlattformUser->getAttribute("addressgroups"), $objStructure->getAttribute("addressgroups"));
    }
    //CBLog($boolCheckIntersection);

    //
    if ( !$boolCheckIntersection ) {

        // standard
        $strUrl = BASEURL;

        // special pages
        if (
            (isset($_REQUEST['CBroute']) && strpos($_REQUEST['CBroute'], "admin/") === 0) ||
            (isset($_REQUEST['CBroute']) && strpos($_REQUEST['CBroute'], "console/") === 0)
        ) {
            if (strpos($_REQUEST['CBroute'], "admin/") === 0) {
                $strUrl .= "admin/";
            }
            if (strpos($_REQUEST['CBroute'], "console/") === 0) {
                $strUrl .= "console/";
            }
        }


        //
        //CBLog("strUrl: ".$strUrl);exit;
        header( 'Location: '.$strUrl );
        exit;

    }

    //
    if ( $objStructure->getAttribute("active") == "1" ) {

        $objParser = new \capps\modules\core\classes\CBParser();

        //
        $template = "###part_content###";
        $partial = "";

        // page
        if ( $objStructure->getAttribute("template") != "" ) {

            if ( is_numeric($objStructure->getAttribute("template")) ) {
                // TODO load template from database
            } else {
                $file = BASEDIR.$objStructure->getAttribute("template");
                if ( is_file($file) ) {
                    $template = file_get_contents($file);
                }
            }

            // delete easyadmin
            if ( stristr($template,"</cb:easyadmin>") ) {
                preg_match_all('/<cb:easyadmin(.*)>(.*)<\/cb:easyadmin>/Us', $template, $arrTmp);
                if (count($arrTmp) >= 1) {
                    $template = str_replace($arrTmp[0][0],"",$template);
                }
            }
            //echo htmlspecialchars($template);exit();

            //
            $template = $objParser->parse($template,$objStructure);

            $template = parseTemplate($template, $objStructure->arrAttributes,"page_|structure_",false);
            //echo htmlspecialchars($template);exit();

            //
            // elements
            //
            $objContent = CBinitObject("Content");

            $arrConditions = array();
            $arrConditions["language_id"] = "1";
            $arrConditions["structure_id"] = $structure_id;
            $arrConditions["active"] = "1";
            //CBLog($arrConditions);
            $arrEntries = $objContent->getAllEntries("sorting","ASC",$arrConditions);
            //CBLog($arrEntries); exit();
            if ( is_array($arrEntries) && count($arrEntries) > 0 ) {

                foreach ( $arrEntries as $entry ) {
                    $objContentTmp = CBinitObject("Content",$entry["content_id"]);
                    //CBLog($objContentTmp); exit();

                    //
                    $boolCheckIntersection = true;
                    if ( $objContentTmp->getAttribute("addressgroups") != "" ) {
                        $boolCheckIntersection = checkIntersection($objPlattformUser->getAttribute("addressgroups"), $objContentTmp->getAttribute("addressgroups"));
                    }
                    //$boolCheckIntersection = true;
                    if ( !$boolCheckIntersection ) continue;

                    //
                    $partial_tmp = "";
                    if ( $objContentTmp->getAttribute("template") != "" ) {
                        if (is_numeric($objContentTmp->getAttribute("template"))) {
                            // TODO load template from database
                        } else {
                            $file = BASEDIR . $objContentTmp->getAttribute("template");
                            //CBLog($file); exit();
                            if (is_file($file)) {

                                $partial_tmp = file_get_contents($file);
                                //echo htmlspecialchars($partial_tmp);exit;

                                // delete easyadmin
                                if ( stristr($partial_tmp,"</cb:easyadmin>") ) {
                                    preg_match_all('/<cb:easyadmin(.*)>(.*)<\/cb:easyadmin>/Us', $partial_tmp, $arrTmp);
                                    if (count($arrTmp) >= 1) {
                                        $partial_tmp = str_replace($arrTmp[0][0],"",$partial_tmp);
                                    }
                                }

                                $partial_tmp = parseTemplate($partial_tmp, $objContentTmp->arrAttributes, "element_|content_",false);

                                $partial_tmp = $objParser->parse($partial_tmp,$objStructure,$objContentTmp);

                                if ( strstr($partial_tmp, "###page_") || strstr($partial_tmp, "###structure_") ) {
                                    $partial_tmp = parseTemplate($partial_tmp, $objStructure->arrAttributes,"page_|structure_",false);
                                }
                            } else {
                                $partial_tmp = "template not found: ".BASEDIR.$objContentTmp->getAttribute("template");
                            }
                        }
                    }
                    //echo htmlspecialchars($partial_tmp);exit;
                    $partial .= $partial_tmp;

                }
            }





        }

        //$partial = $objParser->parse($partial,$objStructure);
        //$partial = $objParser->parseCBNavigationTag($partial,$objStructure);

        //echo htmlspecialchars($template);exit();
        //echo htmlspecialchars($partial);exit();



        $template = str_replace("###part_content###", $partial, $template);

        $template = str_replace("###RANDOM###", time(), $template);
        $template = str_replace("###BASEURL###", BASEURL, $template);
        $template = str_replace("###BASEDIR###", BASEDIR, $template);
        $template = str_replace("###MODULE###", $strModule, $template);

        // echo"<pre>";
        // echo htmlspecialchars($template);
        // echo "</pre>";
        //echo htmlspecialchars($template);exit();
        eval('?>'.$template);

        exit;

    }

    // TODO 404/403 page
    if ( !$boolCheckIntersection ) {
        echo $_REQUEST['CBroute'] . " forbidden ";

    } else {
        echo $_REQUEST['CBroute'] . " unknown ";

    }

    //
    $objScheduler = new \capps\modules\scheduler\classes\Scheduler();
    $objScheduler->runDueTasks(5);

    //
    exit();
}

