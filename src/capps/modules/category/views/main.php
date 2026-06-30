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
$objTmp = CBinitObject("Category");
//CBLog($objTmp);

//
// js
//
?>
<script type="text/javascript">


    function listItems(){

        var url = "###BASEURL###views/category/listItems/";

        $( "#container_content" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#container_content" ).load( url, function() {
            //alert( "Load was performed. "+url );
        });

    }

    function newItem(){

        globalDetailModal.show();

        var url = "###BASEURL###views/category/newItem/";

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }

    function editItem(id){

        globalDetailModal.show();

        var url = "###BASEURL###views/category/editItem/?id="+id;

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }





</script>




<div class="text-end">
    <a class="classid_entry_new"><i class="bi bi-plus-lg"></i></a>
</div>

<h1>Category</h1>


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
