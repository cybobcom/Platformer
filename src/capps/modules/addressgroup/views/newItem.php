<?php

	//
	// new
	//

/*
	$strModuleName = CBgetModuleName(__FILE__);
	//echo "strModuleName<pre>"; print_r($strModuleName); echo "</pre>";


	
	$objTmp = CBinitObject(ucfirst($strModuleName));
    */

$objTmp = new \capps\modules\database\classes\CBObject(NULL,"capps_addressgroup","addressgroup_uid");
	//echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";
	
?>


    
<form method="post" id="modal_item_new" class="form-horizontal">


										
<div class="modal-header">
	<h5 class="modal-title" id="exampleModalLabel">Neuer Eintrag</h5>
	<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>
      
      <div class="modal-body">
								
<table class="table table-sm">
 
 <tr class="back8">
  <td>name</td>
    <td><?php echo cb_makeInputForm ("save[name]",$objTmp->getAttribute('name'),"form-control form-control-sm"); ?></td>
  </tr>
  
      
    </td> 
  </tr>
    
</table>

      </div>
      
      <div class="modal-footer">
		  <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
		  <button type="button" class="btn btn-primary classid_button_insert">Speichern</button>
		</div>
      
										
</form>


<script>
    $(document).off('click', '.classid_button_insert');
$(document).on('click', '.classid_button_insert', function () {


	
	var tmp = $('#modal_item_new').serializeArray();
					 
	var url = "<?php echo BASEURL; ?>controller/addressgroup/insertItem/";

	$.ajax({
		'url': url,
		'type': 'POST',
		'data': tmp,
		'success': function(result){
			 //process here
			 //alert( "Load was performed. "+url );
			 //$('#myModalDetails').html(result);

            if (result.response == "success") {
                editItem(result.id);
            } else {
                //$("#container_login").html(result.description);
            }

		}
	});
	
	return false; // no submit of form
	
});

</script>

<?php
	


?>