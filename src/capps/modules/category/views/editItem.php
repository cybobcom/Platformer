<?php

//
// edit
//
//echo "dsf".__FILE__;
//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";

if ( $_REQUEST['id'] != "" ) {

    //
    $objTmp = CBinitObject("Category",$_REQUEST["id"]);
//CBLog($objTmp);

?>

<form method="post" id="modal_item_update" class="form-horizontal">

<?php echo cb_makeHiddenForm ("id",$objTmp->getAttribute($objTmp->strPrimaryKey),"form-control form-control-sm"); ?>

<div class="modal-header">
  <h5 class="modal-title" id="exampleModalLabel">Eintrag bearbeiten</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
      
      <div class="modal-body">
								
<table class="table table-sm">
 
 
  <tr class="back8">
  <td>name</td>
    <td><?php echo cb_makeInputForm ("save[name]",$objTmp->getAttribute('name'),"form-control form-control-sm"); ?></td>
  </tr>
  

  
    <tr class="back8">
  <td>aktiv</td>
    <td><?php echo cb_makeCheckboxForm ("save[active]",$objTmp->getAttribute('active')); ?></td>	
  </tr>



     
</table>

      </div>
      
      <div class="modal-footer">
                   
      <!-- 		<span onclick="deleteItem('<?php echo $objTmp->getAttribute($objTmp->strPrimaryKey); ?>')" class="me-auto bi bi-trash"></span> -->
      
              <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
              <button type="button" class="btn btn-primary classid_button_update" >Speichern</button>
            </div>
      
										
</form>

<script>
    $(document).off('click', '.classid_button_update');
$(document).on('click', '.classid_button_update', function () {


  
  var tmp = $('#modal_item_update').serializeArray();
  // alert( "doModalCategoryEdit "+tmp );
           
  var url = "<?php echo BASEURL; ?>controller/category/updateItem/";
  
  $.ajax({
    'url': url,
    'type': 'POST',
    'data': tmp,
    'success': function(result){
       //process here
       //alert( "Load was performed. "+url );
       
       //
        globalDetailModal.hide();
       
       //
       listItems();
    }
  });
  
  return false; // no submit of form
  
});

</script>
<?php
	
}

?>