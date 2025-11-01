<?php

//echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

$dictResponse = array();
$dictResponse["response"] = "error";
$dictResponse["description"] = "something went wrong";

if ( isset($_REQUEST['id']) &&$_REQUEST['id'] != "" ) {
	
	//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
	
	//if ( $_REQUEST['action'] == "saveModalNewCategory" ) {
		
		$arrSave = array();
		if ( isset( $_REQUEST['save']) ) $arrSave = $_REQUEST['save'];
 		$arrSave['date_updated'] = date("Y-m-d H:i:s");
    //echo "<pre>"; print_r($arrSave); echo "</pre>";

    //
    // addressgroups
    //
    //echo "<pre>"; print_r($_REQUEST["addressgroups"]); echo "</pre>";

    if ( isset($_REQUEST['addressgroups'])  AND is_array($_REQUEST['addressgroups'])  AND count($_REQUEST['addressgroups']) >= 1 ){

        //$arrSave["groups"] = trim(implode(",",$_REQUEST['usergroups']),",");

        $strGroups = "";

        foreach ( $_REQUEST['addressgroups'] as $identifier=>$checked ) {

            //echo "$identifier --- $checked <br>";

            if ( $identifier == '0' ) continue;

            if ( $checked == "0" ) {
                //
            } else {
                if ( $strGroups != "" ) $strGroups .= ",";
                $strGroups .= $identifier;
            }

            $arrSave["addressgroups"] = $strGroups;

        }


    }



//
    $objTmp = CBinitObject("Structure",$_REQUEST['id']);
//CBLog($objTmp);

	
		//$objTmp = CBinitObject("Address",$_REQUEST['id']);
    //$objTmp = new \capps\modules\database\classes\CBObject($_REQUEST['id'],"capps_address","address_id");

		$res = $objTmp->saveContentUpdate($_REQUEST['id'],$arrSave);
    //CBLog($res);
		//$objTmp = CBinitObject("Address",$_REQUEST['id']);
    //$objTmp = new \capps\modules\database\classes\CBObject($_REQUEST['id'],"capps_address","address_id");




    //}


    //
    // route
    //
    $objRoute = CBinitObject("Route");
    $objRoute->generateStructureRoute($objTmp->identifier);



	
}


//exit;
$output = json_encode($dictResponse, JSON_HEX_APOS);

header('Content-Type: application/json');
echo $output;

?>