<?php

//
// new
//

//
$objTmp = CBinitObject("Category");
//CBLog($objTmp);

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
                    <td><?php echo cb_makeInputForm("save[name]", $objTmp->getAttribute('name')); ?></td>
                </tr>

            </table>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Schlie&szlig;en</button>
            <button type="button" class="btn btn-primary classid_button_insert">Speichern</button>
        </div>


    </form>


    <script>
        // This code sets up a click event handler for elements with the class 'classid_button_insert'
        $(document).off('click', '.classid_button_insert');
        $(document).on('click', '.classid_button_insert', function () {
            // Serialize the form data
            const tmp = $('#modal_item_new').serializeArray();

            // Construct the URL for the AJAX request
            const url = "<?php echo BASEURL; ?>controller/category/insertItem/";

            // Perform an AJAX POST request
            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'success': function (result) {
                    // If the response is successful, call editItem with the returned ID
                    if (result.response === "success") {
                        editItem(result.id);
                    } else {
                        // Error handling could be implemented here
                        // Example: $("#container_login").html(result.description);
                    }
                }
            });

            // Prevent the default form submission
            return false;
        });

    </script>

<?php


?>