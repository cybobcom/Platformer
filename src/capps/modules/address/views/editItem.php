<?php

//
// edit
//
//echo "dsf".__FILE__;
//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";

use capps\modules\database\classes\CBObject;

if ( $_REQUEST['id'] != "" ) {
	
	//$objTmp = CBinitObject("Address",$_REQUEST['id']);
    $objTmp = new CBObject($_REQUEST['id'],"capps_address","address_uid");
	//echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";

?>

<form method="post" id="modal_item_update" class="form-horizontal">

<?php echo cb_makeHiddenForm ("id",$objTmp->getAttribute('address_uid'),"form-control form-control-sm"); ?>										

<div class="modal-header">
  <h5 class="modal-title" id="exampleModalLabel">Eintrag bearbeiten</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
      
      <div class="modal-body">
								
<table class="table table-sm">
 
 
  <tr class="back8">
  <td>firstname</td>
    <td><?php echo cb_makeInputForm ("save[firstname]",$objTmp->getAttribute('firstname'),"form-control form-control-sm"); ?></td>	
  </tr>
  
   <tr class="back8">
  <td>lastname</td>
    <td><?php echo cb_makeInputForm ("save[lastname]",$objTmp->getAttribute('lastname'),"form-control form-control-sm"); ?></td>	
  </tr>
  
  <tr class="back8">
  <td>login</td>
    <td><?php echo cb_makeInputForm ("save[login]",$objTmp->getAttribute('login'),"form-control form-control-sm"); ?></td>	
  </tr>

    <tr class="back8">
        <td>login_alternative</td>
        <td><?php echo cb_makeInputForm ("save[login_alternative]",$objTmp->getAttribute('login_alternative'),"form-control form-control-sm"); ?></td>
    </tr>
  
    <tr class="back8">
  <td>password</td>
    <td><?php echo cb_makeInputForm ("save[password]",$objTmp->getAttribute('password'),"form-control form-control-sm"); ?></td>	
  </tr>
  
    <tr class="back8">
  <td>aktiv</td>
    <td><?php echo cb_makeCheckboxForm ("save[active]",$objTmp->getAttribute('active')); ?></td>	
  </tr>


    <tr class="">
        <td>Nutzergruppe</td>
        <td><?php

            $arrAddressGroups = explode(",",$objTmp->getAttribute('addressgroups'));
            
            $objAG = new CBObject(NULL,"capps_addressgroup","addressgroup_uid");
            
            //$objAG = connectClass('caddress/Addressgroup.class.php');
            $arrAG = $objAG->getAllEntries("sorting|name","ASC|ASC",NULL,NULL,"*");
            //echo "<pre>"; print_r($arrAG); echo "</pre>";
            
            if ( is_array($arrAG) && count($arrAG) >= 1 ) {
                foreach ( $arrAG as $rAG=>$vAG ) {
            
            
            
                    //
                    $tmp = "";
                    if ( in_array($vAG["entity"],$arrAddressGroups) ) $tmp = "1";
            
            
                    echo '<label for="'."addressgroups[".$vAG["entity"]."]".'" style="display:block;">';
                    echo cb_makeCheckboxForm ("addressgroups[".$vAG["entity"]."]",$tmp,NULL,NULL,NULL,NULL,"classid_checkbox_addressgroup")." ".$vAG["name"]."<br>";
                    echo '</label>';
            
                }
            }


            ?></td>
    </tr>
  
     
</table>

      </div>
      
      <div class="modal-footer">
                   
      <!-- 		<span onclick="deleteItem('<?php echo $objTmp->getAttribute('content_id'); ?>')" class="me-auto bi bi-trash"></span> -->
      
              <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
          <button type="button" class="btn btn-primary classid_button_update" ><cb:localize>Save</cb:localize></button>
            </div>
      
										
</form>

<script>
    $(document).off('click', '.classid_button_update');
    $(document).on('click', '.classid_button_update', function () {


  
  var tmp = $('#modal_item_update').serializeArray();
  // alert( "doModalCategoryEdit "+tmp );
           
  var url = "<?php echo BASEURL; ?>controller/address/updateItem/";
  
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