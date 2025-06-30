<?php

global $objPlattformUser;

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


    function listItems(uid=""){

        var strSearch = $('#filter_search').val();

        var url = "###BASEURL###views/address/listItems/";
        url += "?search="+encodeURI(strSearch);

        if ( uid == "" ) {

            $( "#container_content" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
            $( "#container_content" ).load( url, function() {
                //alert( "Load was performed. "+url );
            });

        } else {

            $.ajax({
                'url': url,
                'type': 'POST',
                'success': function(result){
                    //alert(result);
                    if ( result != "" ) {
                        $( 'tr[data-uid="'+uid+'"]' ).replaceWith(result);
                    }
                }
            });

        }

    }

    function newItem(){

        globalDetailModal.show();

        var url = "###BASEURL###views/address/newItem/";

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }

    function editItem(id){

        globalDetailModal.show();

        var url = "###BASEURL###views/address/editItem/?id="+id;

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }





</script>


<cb:navigation
        entry="###page_structure_id###"
        highlightDEVPath="1"
        level3="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'>###page_name###</a>"
        level3_selected="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'>###page_name###</a>"
 />


<div class="position-absolute" style="right: 60px; width:200px;padding-top:5px;">
    <div style=" position: absolute;">
        <span style="flo2at:right; position:absolute; right:10px; top:9px; visibility:hidden; color: gray; font-size: 12px;" class="id_search_reset bi bi-x-lg"></span>
        <input name="search" type="text" class="form-control foDEVrmular4 form-control-sm" value="<?php echo $objPlattformUser->getAttribute("settings_address_search"); ?>" placeholder="Finden" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"  id="filter_search" />
    </div>
</div>
<script>
    $('#filter_search').off('input chaDEVnge paste');
    $('#filter_search').on('input chaDEVnge paste', function(){

        $('.id_search_reset').css('visibility', 'visible');

        var str = $('#filter_search').val();
        if ( str.length >= 2 ) {
            listItems();
        }

    });
    $(document).off('click', '.id_search_reset')
    $(document).on('click', '.id_search_reset', function () {

        $('#filter_search').val("");
        $('.id_search_reset').css('visibility', 'hidden');
        listItems();

    });
    if ( $('#filter_search').val() != "" ) {
        $('.id_search_reset').css('visibility', 'visible');
    }

</script>



<div class="position-absolute" style="right: 20px;">
    <a class="classid_entry_new"><i class="btn btn-lg bi bi-plus-lg"></i></a>
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
