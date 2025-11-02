<?php

//echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

if ( $_REQUEST['id'] != "" ) {
	
	//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
	
	//if ( $_REQUEST['action'] == "saveModalNewCategory" ) {
		
		$arrSave = array();
		$arrSave = $_REQUEST['save'];
 		$arrSave['date_updated'] = date("Y-m-d H:i:s");
	
		//$objTmp = CBinitObject("Address",$_REQUEST['id']);
    $objTmp = new \capps\modules\database\classes\CBObject($_REQUEST['id'],"capps_addressgroup","addressgroup_uid");

		$res = $objTmp->saveContentUpdate($_REQUEST['id'],$arrSave);

		//$objTmp = CBinitObject("Address",$_REQUEST['id']);
    $objTmp = new \capps\modules\database\classes\CBObject($_REQUEST['id'],"capps_addressgroup","addressgroup_uid");




    //}
	
}


?>