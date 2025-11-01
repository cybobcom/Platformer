<?php

//echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

if ( $_REQUEST['id'] != "" ) {
	
	//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
	
	//if ( $_REQUEST['action'] == "saveModalNewCategory" ) {
		
		$arrSave = array();
		if ( isset($_REQUEST['save']) ) $arrSave = $_REQUEST['save'];
 		$arrSave['date_updated'] = date("Y-m-d H:i:s");


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
    $objTmp = CBinitObject("Content",$_REQUEST['id']);
//CBLog($objTmp);
	
		//$objTmp = CBinitObject("Address",$_REQUEST['id']);
    //$objTmp = new \capps\modules\database\classes\CBObject($_REQUEST['id'],"capps_address","address_id");

		$res = $objTmp->saveContentUpdate($_REQUEST['id'],$arrSave);

		//$objTmp = CBinitObject("Address",$_REQUEST['id']);
    //$objTmp = new \capps\modules\database\classes\CBObject($_REQUEST['id'],"capps_address","address_id");




    //}
	
}


?>