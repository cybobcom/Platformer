<?php
	
	//
	//
	//
	
	ini_set('memory_limit', -1);
	
	
	global $objDBS;
		
		
		

	//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
	//echo "<pre>"; print_r($objDBS); echo "</pre>";
	
	$arrDBFields = array();
	$arrDBFieldsValue = array();
	
	
	$arrTables = $objDBS->get("SHOW TABLES");
	//echo "<pre>"; print_r($arrTables); echo "</pre>";
	
	$arrTablesPur = array();
	if ( is_array($arrTables) && count($arrTables) >= 1 ) {
		foreach ( $arrTables as $r=>$v ) {
			$arrTablesPur[] = current($v);
		}
	}
	//echo "<pre>"; print_r($arrTablesPur); echo "</pre>";
	
	//
	$arrTablesPur = array("capps_address");
	
	
	//
	// helper for data aggregation
	//
	$objAtmp = connectClass('caddress/Address.class.php');

	$arrCondition = array();
	$arrCondition["type"] = "person";
	
	$arrAddresses = $objAtmp->getAllEntries(NULL,NULL,$arrCondition,NULL,"address_id,email");
	
	$arrEmailToAdressId = array();
	if ( is_array($arrAddresses) && count($arrAddresses) >= 1 ) {
		foreach( $arrAddresses as $dictEntry ) {
			$arrEmailToAdressId[mb_strtolower($dictEntry["email"])] = $dictEntry["address_id"];
		}
	}
	//echo "<pre>"; print_r($arrEmailToAdressId); echo "</pre>";

	
	
?>
<form action="<?php echo $PHP_SELF."?sid=".$_REQUEST['sid']; ?>" method="post">

<div class="container-fluid g-3" style="">
	<div class="row">
		<div class="col-12">
			
			<h1 class="head1">Import</h1>
			<!-- <div class="page-liner"></div> -->
			
		</div>





		<div class="col-6">
			DB:
			
			<?php
			echo cb_makeSelectForm ("control[import_db_table]",$arrTablesPur,$arrTablesPur,$_REQUEST['control']['import_db_table'],"0","form-control form-control-sm id_select_table" );
			?>
		</div>
		<div class="col-6">
			Spalten:
			<div class="id_table_columns" style="height: 100px; overflow: auto; border:1px solid #F3F3F3; padding: 8px; background-color: white;"></div>
			<script>
				$(function() {
			
				$('.id_select_table').change(function(){

					//				      
					$.ajax({
						'url': '<?php echo BASEURL; ?>/ajax/&am=Import.listTableColumns&table='+$(this).val(),
						'type': 'POST',
						//'data': tmp,
						'success': function(result){
							 //process here
							 //alert( result );
							 $('.id_table_columns').html(result);
							 
							
							 
						}
					});
				      
				});
				 
			});
			
			</script>
		</div>

<?php
	if ( $_REQUEST['control']['import_db_table'] != "" ) {
		?>
		<script>
			$(function() {
				$('.id_select_table').trigger("change");
			});
		</script>
		<?php
	}
	
	// set default
	if ( !isset($_REQUEST['control']['import_type']) || $_REQUEST['control']['import_type'] == "" ) $_REQUEST['control']['import_type'] = "sales";
?>

<div class="col-6">

	<div class="container-fluid">
		<div class="row">
	
	<div class="col-12">
		DB-Felder (tab- oder komma-getrennt):<br />
		<input class="form-control formular10" name="control[import_db_fields]" type="text" id="control[import_db_fields]" value="<?php echo htmlspecialchars($_REQUEST['control']['import_db_fields']); ?>" size="100" />
	</div>
	
	<div class="col-12">
		DB-Feld f&uuml;r Update-Check:<br />
		<input class="form-control formular10" name="control[update_check]" type="text" id="control[update_check]" value="<?php echo htmlspecialchars($_REQUEST['control']['update_check']); ?>" size="50" />
	</div>
	
	<div class="col-12">
		Daten (tab- oder komma-getrennt):<br />
		<textarea class="form-control formular10" name="save[import]" cols="150" rows="25" id="save[import]" style="font-size:14px;"><?php echo htmlspecialchars(stripslashes($_REQUEST['save']['import'])); ?></textarea>
	</div>
	
	<div class="col-12">
		Adresstyp (sales[default]|address):<br />
		<input class="form-control formular10" name="control[import_type]" type="text" id="control[import_type]" value="<?php echo htmlspecialchars($_REQUEST['control']['import_type']); ?>" size="100" />
	</div>
	
		</div>
	</div>

</div>

<?php

if ( $_REQUEST['save']['import'] != "" ) {
	
	//
	// primary key
	//
	
	$strPrimaryKey = "";
	
	//
	if ( $_REQUEST['control']['import_db_table'] != "" ) {
		
		$arrDatbaseTableColumns = $objDBS->get("SHOW COLUMNS FROM ".$_REQUEST['control']['import_db_table']);
		//echo "<pre>"; print_r($arrDatbaseTableColumns); echo "</pre>";
		
		if ( $arrDatbaseTableColumns[0]["Key"] == "PRI" ) {
			$strPrimaryKey = $arrDatbaseTableColumns[0]["Field"];
		}
		
	}
	//echo "<pre>"; print_r($strPrimaryKey); echo "</pre>";
	
	
	
	//
	// get database columns to import
	//
	if ( $_REQUEST['control']['import_db_fields'] != "" ) {
		
		//
		$arrTmp = array();
		
		// option comma
		if ( strstr($_REQUEST['control']['import_db_fields'],",") ) $arrTmp = explode(",",$_REQUEST['control']['import_db_fields']);
		
		// option tab
		if ( strstr($_REQUEST['control']['import_db_fields'],"\t") ) $arrTmp = explode("\t",$_REQUEST['control']['import_db_fields']);
		
		//
		if ( is_array($arrTmp) ) {
			
			$arrDBFields = array();
			
			foreach ( $arrTmp as $run=>$value ) {
				if ( $value == "" ) continue;
				$arrDBFields[] = trim($value);
			}
		}
	}
	//echo "<pre>"; print_r($arrDBFields); echo "</pre>";
	
	
	
	//
	// data to import
	//
	
	// convert linebreaks to <br>
	//$_REQUEST['save']['import'] = str_replace("\n","<br>",$_REQUEST['save']['import']);
	
	// lines
	$arrTmp = explode("\n",$_REQUEST['save']['import']);
	
	$arrSave = array();
	if ( is_array($arrTmp) ) {
		foreach ( $arrTmp as $run=>$value ) {
			
			if ( $value == "" ) continue;
			
			$arrContentTmp = array();
			
			// option comma
			if ( strstr($value,",") ) $arrContentTmp = explode(",",$value);
			
			// option tab
			if ( strstr($value,"\t") ) $arrContentTmp = explode("\t",$value);
			//echo "<pre>"; print_r($arrContentTmp); echo "</pre>";
			
			$arrSaveTmp = array();
			if ( is_array($arrDBFields) && count($arrDBFields) >= 1 ) {
				foreach ( $arrDBFields as $r=>$v ) {
					
					if ( $arrContentTmp[$r] == "" ) continue;

					$arrSaveTmp[$arrDBFields[$r]] = trim($arrContentTmp[$r]);
				}
			}
			
			//
			if ( is_array($arrSaveTmp) && count($arrSaveTmp) >= 1 )  $arrSave[] = $arrSaveTmp;
			
		}
	}
	//echo '<pre>'; print_r($arrSave); echo '</pre>';
	//echo "----------------------------<br><br>";
	
	
	if ( !in_array($_REQUEST['control']['update_check'],$arrDBFields) ) echo "<br> Update-Check nicht möglich, da Feld nicht in der Datenbank<br><br>";
	
	
	//
	// aggregate data
	//
	
	// db fields
	if ( $_REQUEST['control']['import_db_table'] == "capps_address" ) {

		if ( $_REQUEST['control']['import_type'] == "sales" ) {
			$arrDBFields[] = "active";
			$arrDBFields[] = "type";
			$arrDBFields[] = "source";
			$arrDBFields[] = "media_picture";
		}
		if ( $_REQUEST['control']['import_type'] == "address" ) {
			$arrDBFields[] = "active";
			$arrDBFields[] = "type";
			$arrDBFields[] = "source";
			$arrDBFields[] = "data_accountmanager";
			$arrDBFields[] = "data_salessupport";
		}
	}
	
	// content
	foreach ( $arrSave as $run=>$dictEntry ) {
		
		//
		//
		//
		if ( $_REQUEST['control']['import_db_table'] == "capps_address" ) {
			
			//
			if ( $_REQUEST['control']['import_type'] == "sales" ) {
				$dictEntry["active"] = "1";
				$dictEntry["type"] = "person";
				$dictEntry["source"] = "import_sales_".date("Ymd");
				if ( $dictEntry["media_picture"] != "" ) $dictEntry["media_picture"] = "data/media/portraits/".$dictEntry["media_picture"];
			}
			if ( $_REQUEST['control']['import_type'] == "address" ) {
				$dictEntry["active"] = "1";
				$dictEntry["type"] = "company";
				$dictEntry["source"] = "import_address_".date("Ymd");
				
				if ( $arrEmailToAdressId[mb_strtolower($dictEntry["data_accountmanager_email"])] != "" ) $dictEntry["data_accountmanager"] = $arrEmailToAdressId[mb_strtolower($dictEntry["data_accountmanager_email"])];
		
				if ( $arrEmailToAdressId[mb_strtolower($dictEntry["data_salessupport_email"])] != "" ) $dictEntry["data_salessupport"] = $arrEmailToAdressId[mb_strtolower($dictEntry["data_salessupport_email"])];				
			}
			
			$arrSave[$run] = $dictEntry;
		}
		
	}
	?>


<div class="col-6 table-responsive contentarea">
<table width="100%" class="table table-sm table-hover">                
                   
	<thead><tr>
    
    	<th> </th>
        <th> </th>
        <th> </th> 
        
    	<?php
		foreach ( $arrDBFields as $run=>$value ) {
			?>
	    	<th><?php echo $value; ?></th>
	        <?php
		}
		?>
		
		
        
	</tr></thead>
	
    <?php
		

		foreach ( $arrSave as $run=>$dictEntry ) {
			
			//echo '<pre>'; print_r($dictEntry); echo '</pre>';
			
			//
			$todo = "save";
			
			//
							
				if ( in_array($_REQUEST['control']['update_check'],$arrDBFields) ) {
					
					if ( $dictEntry[$_REQUEST['control']['update_check']] != "" ) {
						
						//$sql = "SELECT * FROM ".$_REQUEST['control']['import_db_table']." WHERE ".$_REQUEST['control']['update_check']." = '".$dictEntry[$_REQUEST['control']['update_check']]."'";
						//$arrR = $objDBS->get($sql);
						
						$objTmp = new BasisDB(NULL, $_REQUEST['control']['import_db_table'], $strPrimaryKey);
						
						$arrCondition = array();
						$arrCondition[$_REQUEST['control']['update_check']] = $dictEntry[$_REQUEST['control']['update_check']]."";
						
						$arrR = $objTmp->getAllEntries(NULL,NULL,$arrCondition);
						
						//echo $sql.'<pre>'; print_r($arrR); echo '</pre>';
						
						//
						if ( !empty($arrR) && count($arrR) == 1 ) $todo = "update";
						if ( !empty($arrR) && count($arrR) >= 2 ) $todo = "nicht eindeutig";
						
						// for update
						if ( !empty($arrR) && count($arrR) == 1 ) {
							$dictEntry[$strPrimaryKey] = $arrR[0][$strPrimaryKey];
						}
						
					}
				}
					
			//			
			//echo $strPrimaryKey.'<pre>'; print_r($dictEntry); echo '</pre>';
			
			
			
			
			//
			//
			//
			if ( $_REQUEST['doSave'] == "1" && $strPrimaryKey != "" ) {
			
				if ( $todo == "save" ) {
					//
					// new
					//
					
					//$objTmp = connectClass('caddress/Address.class.php');
					$objTmp = new BasisDB(NULL, $_REQUEST['control']['import_db_table'], $strPrimaryKey);

					$arrS  = array();
					if ( is_array($dictEntry) ) $arrS = $dictEntry;
					//echo 'arrS<pre>'; print_r($arrS); echo '</pre>';
					

					

					
					$newID = "";
					$newID = $objTmp->saveContentNew($arrS);
					
					
					
					if ( $_REQUEST['control']['import_db_table'] == "capps_address" ) {
						
						//
						// add group
						//
						if ( $_REQUEST['control']['import_type'] == "sales" ) {
							$gid = "1";
							$strQ = "INSERT INTO capps_address_to_group (addressgroup_id,address_id) values(".$gid.",".$newID.")";
							$objTmp->privateFreeQuery($strQ) ;
						}
						if ( $_REQUEST['control']['import_type'] == "address" ) {
							$gid = "2";
							$strQ = "INSERT INTO capps_address_to_group (addressgroup_id,address_id) values(".$gid.",".$newID.")";
							$objTmp->privateFreeQuery($strQ) ;
						}
					
					} 
					

				}
				
				if ( $todo == "update" ) {
					//
					// update
					//
					
					//$objTmp = connectClass('caddress/Address.class.php',$dictEntry[$_REQUEST['control']['update_check']]);
					$objTmp = new BasisDB($dictEntry[$strPrimaryKey], $_REQUEST['control']['import_db_table'], $strPrimaryKey);
					
					$arrS  = array();
					if ( is_array($dictEntry) ) $arrS = $dictEntry;
					
					$newID = "";
					if ( $dictEntry[$strPrimaryKey] != "" ) {
						$newID = $objTmp->saveContentUpdate($dictEntry[$strPrimaryKey],$arrS);
					}
					
				}
				
			}
		
		
		?>
    <tr valign="middle" class="back8">
    	
        <td><?php echo $run+1; ?></td>
        <td><?php echo $newID; ?></td> 
        <td><?php 		
			echo $todo; 
		?></td>
        
		<?php
		foreach ($arrDBFields as $r=>$v ) {			
		?>
        <td><?php echo $dictEntry[$v]; ?></td>
        <?php
		}
		?>
        
	</tr>
    <?php
	}
	?>

</table>
</div>
    
    <div class="col-12 text-end">
    <label>
    <input name="doSave" type="checkbox" id="doSave" value="1" /> 
    Ja, in die Datenbank übernehmen
    </label>
    </div>
    <?php
	
	
}

?>

<div class="col-12 text-end">
  <label>
  <input type="submit" class="btn btn-primary span2" name="button" id="button" value="Senden" />
  </label>




		</div>
	</div>
</div>
</form>