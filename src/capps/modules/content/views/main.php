<?php

//echo "DEV";exit;
//
// check user login
//
/*
if ( $_SESSION['aid'] == "" ) {
	exit();
}
*/


// //
// $strModuleName = "###MODULE###";
// //CBLog($strModuleName);
// 
// //
// $objTmp = CBinitObject(ucfirst($strModuleName));
// //CBLog($objTmp);

//
// js
//
?>
<script type="text/javascript">


    function listItems(){

        var url = "###BASEURL###views/content/listItems/";

        $( "#container_content" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#container_content" ).load( url, function() {
            //alert( "Load was performed. "+url );
        });

    }

    function newItem(){

        globalDetailModal.show();

        var url = "###BASEURL###views/content/newItem/";

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }

    function editItem(id){

        globalDetailModal.show();

        var url = "###BASEURL###views/content/editItem/?id="+id;

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }





</script>




<div class="position-absolute" style="right: 20px;">
    <a class="classid_entry_new"><i class="btn btn-lg bi bi-plus-lg"></i></a>
</div>

<h1>Inhalte</h1>


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
