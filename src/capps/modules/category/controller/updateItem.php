<?php
//echo "<pre>"; print_r($_REQUEST); echo "</pre>";exit;

if ( $_REQUEST['id'] != "" ) {
	
	//
		$arrSave = array();
		$arrSave = $_REQUEST['save'];
 		$arrSave['date_updated'] = date("Y-m-d H:i:s");


//
    $objTmp = CBinitObject("Category",$_REQUEST['id']);
//CBLog($objTmp);

		$res = $objTmp->saveContentUpdate($_REQUEST['id'],$arrSave);


	
}


?>