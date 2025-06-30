<?php


if ( isset($_REQUEST['save']) ) {
	
	//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
	//exit();
	if ( $_REQUEST['ac'] == "Category.deleteItems" ) {
		
				
		if ( is_array($_REQUEST['save']) && count($_REQUEST['save']) >= 1 ) {
			foreach ( $_REQUEST['save'] as $id=>$value ) {
				if ( $value == "1" ) {
// 					$objCategoryTmp = connectClass('ccategory/Category.class.php',$id);
					$objCategoryTmp = new BasisDB($id,"capps_category","category_id",$arrDB_Data);
					$objCategoryTmp->deleteEntry($id);
					echo "Deleting... ".$id."<br>";
					sleep(0.1);
				}
			}
		}
		//exit();
		
		?>
			  <script>
			  
				$('#myModal').modal('toggle');
			  
				var url = "?am=Category.showCategoryList";
				
				$( "#content_container" ).html('<div align="center"><img src="/data/media/ajax_loader.gif" class="ajax_loader" /></div>');
				$( "#content_container" ).load( url, function() {
				  //alert( "Load was performed." );
				});
				
			</script>

		<?php


	}
	
}





?>