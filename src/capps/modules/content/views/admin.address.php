<?php
	
//
// check user login
//
/*
if ( $_SESSION['aid'] == "" ) {
	exit();
}
*/

//
// js
//
?>
<script type="text/javascript">
	
	
	function listItems(){
		
		var url = "###BASEURL###address/ajax/partial/listItems";
		
		$( "#container_content" ).html('<div align="center"><img src="###BASEURL###/data/template/assets/ajax_loader.gif" class="ajax_loader" /></div>');
		$( "#container_content" ).load( url, function() {
			//alert( "Load was performed. "+url );
		});

	}

function newItem(){
	
	globalModal.show();
		
	var url = "###BASEURL###address/ajax/partial/newItem";
	
	$( "#myModalDetails" ).html('<div align="center"><img src="###BASEURL###/data/template/assets/ajax_loader.gif" class="ajax_loader" /></div>');
	$( "#myModalDetails" ).load( url, function() {
	  //alert( "Load was performed." );
	});
	
}

function editItem(id){
	
	globalModal.show();
	
	var url = "###BASEURL###address/ajax/partial/editItem/?id="+id;
	
	$( "#myModalDetails" ).html('<div align="center"><img src="###BASEURL###/data/template/assets/ajax_loader.gif" class="ajax_loader" /></div>');
	$( "#myModalDetails" ).load( url, function() {
	  //alert( "Load was performed." );
	});
	
}





</script>




<div class="">
	<a class="classid_entry_new"><i class="bi bi-plus-lg"></i></a>
</div>

<h1>Nutzer</h1>


<div id="container_content">
...
</div>


<script type="text/javascript">
	
	//
	listItems();
	
	//
	$(document).on('click', '.classid_entry_new', function () {
		newItem();
	});
	
</script>
