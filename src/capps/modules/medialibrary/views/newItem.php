<?php

//
// new
//

//if ( $_REQUEST['am'] == "showNewCategory" ) {

$objTmp = connectClass('cmedia/MediaLibrary.class.php');
//echo "<pre>"; print_r($objTmp); echo "</pre>";

?>

    <script>
        function doModalInsert(){

            var tmp = $('#modal_item_new').serializeArray();
            // alert( "doModalCategoryNew "+tmp );

            var url = '<?php echo BASEURL; ?>/ajax/&action=MediaLibrary.insertItem';

            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'dataType': 'text',  // <-- Das hier hinzufügen!
                'success': function(result){
                    //process here
                    //alert( "Load was performed. "+url );
                    $('#myModalDetails').html(result);
                }
            });

            return false; // no submit of form
        }
    </script>

    <form method="post" id="modal_item_new" class="form-horizontal">

        <input type="hidden" name="do_command" value="saveNew" />
        <?php //echo cb_makeHiddenForm ("save[address_id]",$_SESSION["aid"],"form-control formular4"); ?>
        <?php echo cb_makeHiddenForm ("save[active]","1","form-control formular4"); ?>

        <div class="modal-header">
            <h5 class="modal-title">neuer Eintrag</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">

            <table width="100%" border="0" cellspacing="1" cellpadding="5" class="table table-condensed">

                <tr class="back8">
                    <td>Name</td>
                    <td><?php echo cb_makeInputForm ("save[name]",$objTmp->getAttribute('name'),"form-control formular4"); ?></td>
                </tr>



            </table>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
            <button type="button" class="btn btn-primary" onclick="doModalInsert()">Speichern</button>
        </div>


    </form>

<?php

//}

?>