<?php

//
// new
//

//if ( $_REQUEST['am'] == "showNewCategory" ) {

$objTmp = CBinitObject('MediaLibrary');
//echo "<pre>"; print_r($objTmp); echo "</pre>";

?>



    <form method="post" id="modal_item_new" class="form-horizontal">

        <input type="hidden" name="do_command" value="saveNew" />

        <div class="modal-header">
            <h5 class="modal-title">neues Verzeichnis</h5>
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
            <button type="button" class="btn btn-primary classid_button_newdirectory">Speichern</button>
        </div>


    </form>

    <script>
        $(document).off('click', '.classid_button_newdirectory');
        $(document).on('click', '.classid_button_newdirectory', function () {

            var current_dir = $('.id_current_path').val();

            var tmp = $('#modal_item_new').serializeArray();

            var url = "<?php echo BASEURL; ?>controller/medialibrary/insertDirectory/";
            url += "&current_dir="+encodeURI(current_dir);

            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'dataType': 'text',  // <-- Das hier hinzufügen!
                'success': function (result) {
                    //alert(result);

                    globalDetailModal.toggle();

                    listItems();

                }
            });

            return false; // no submit of form

        });

    </script>

<?php

//}

?>