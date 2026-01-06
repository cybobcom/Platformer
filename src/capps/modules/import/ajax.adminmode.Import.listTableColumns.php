<?php
	
	global $objDBS;
	
	//echo "DEV".$_REQUEST["table"];
	
	if ( $_REQUEST['table'] != "" ) {
		
		$arrDatbaseTableColumns = $objDBS->get("SHOW COLUMNS FROM ".$_REQUEST['table']);
		//echo "<pre>"; print_r($arrDatbaseTableColumns); echo "</pre>";
		
		if ( $arrDatbaseTableColumns[0]["Key"] == "PRI" ) {
			$strPrimaryKey = $arrDatbaseTableColumns[0]["Field"];
		}
		
		if ( !empty($arrDatbaseTableColumns) && count($arrDatbaseTableColumns) >= 1 ) {
			echo "<small><table>";
			foreach ( $arrDatbaseTableColumns as $r=>$v ) {
				echo '<tr>';
				echo '<td>'.$v["Field"].'</td>';
				echo '<td>'.$v["Type"].'</td>';
				echo '<tr>';
			}
			echo "</table></small>";
		}
		
	}
	
?>