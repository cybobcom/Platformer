<?php

global $objPlatformUser;


?>



<cb:navigation
        entry="###page_structure_id###"
        highlightDEVPath="1"
        level3="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'>###page_name###</a>"
        level3_selected="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'>###page_name###</a>"
 />


<div class="position-absolute" style="right: 60px; width:200px;padding-top:5px;">
    <div style=" position: absolute;">
        <span style="flo2at:right; position:absolute; right:10px; top:9px; visibility:hidden; color: gray; font-size: 12px;" class="id_search_reset bi bi-x-lg"></span>
        <input name="search" type="text" class="form-control foDEVrmular4 form-control-sm" value="" placeholder="Finden" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"  id="filter_search" v-model="searchText" />
    </div>
</div>
<script>
    /*
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
*/
</script>

<div id="app_main">

    <div class="position-absolute" style="right: 20px;">
        <a class="classid_entry_new"><i class="btn btn-lg bi bi-plus-lg"></i></a>
    </div>

    <h1>Nutzer</h1>


    <div id="container_content">
        ...
    </div>

    <div class="table-responsive contentarea" style="padding: 15px; margin-bottom: 15px;">

        <table class="table table-sm" id="table_list">
            <thead>
            <tr>
                <th class="cb_table_first_row"></th>
                <th>Name</th>
                <th>Login</th>
                <th>Addressgroups</th>
                <th>letzter Login</th>
            </tr>
            </thead>

            <tbody>
            <template v-for="(item, id) in arrItems">

                <tr @click="editItem(item.address_uid)" class="" style="">

                    <td>
                        <div>

                        </div>
                    </td>

                    <td>
                            {{item.firstname}} {{item.lastname}}
                    </td>

                    <td>
                        {{item.login}}<br>
                        {{item.login_alternative}}
                    </td>

                    <td>
                        <?php
                        /*
                        //echo $objTmp->getAttribute('addressgroups');
                        $arrAddressGroups = explode(",", $objTmp->getAttribute('addressgroups'));
                        asort($arrAddressGroups);
                        echo '<small>';
                        foreach ($arrAddressGroups as $entity) {
                            $objAddressGroupTmp = CBinitObject("AddressGroup","entity:".$entity);
                            echo $entity.": ".$objAddressGroupTmp->getAttribute('name')."<br>" ;
                        }
                        echo '</small>';
                        */
                        ?>
                    </td>

                    <td>
                        {{item.date_lastlogin}}
                    </td>

                </tr>
            </template>
            </tbody>

        </table>

</div>



<script type="text/javascript">

    //
    var BASEURL = '<?php echo BASEURL; ?>';

    //
    var appMain = Vue.createApp({

        data() {
            return {

                BASEURL: window.BASEURL,
                hello: 'hi',
                arrItems: [],

                searchText: '<?php echo $objPlatformUser->getAttribute("settings_address_search"); ?>',

            }
        },
        watch: {
            searchText: function (text) {
                if (text.length < 3) {
                    //this.results = [];
                    return;
                }
                this.listItems();
            }
        },
        methods: {

            listItems: function () {

                var url = BASEURL+"views/address/listItems/";
                url += "?format=json";
                url += "&search="+encodeURI(this.searchText);
                //alert(url);

                axios.get(url)
                    .then((response) => {
                        //alert(JSON.stringify(response.data));
                        this.arrItems = response.data;
                    })
                    .catch(error => {
                        console.error(error);
                    });

            },

            editItem(id) {

                globalDetailModal.show();

                var url = BASEURL+"/views/address/editItem/?id="+id;
                //alert(url);

                $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#detailModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },



        },

        beforeMount(){

        },

        mounted(){
            this.listItems()
        },

    })

    //
    var mountedAppMain = appMain.mount('#app_main');

/*
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
    */





</script>
<script type="text/javascript">
/*
    //
    listItems();

    //
    $(document).on('click', '.classid_entry_new', function () {
        newItem();
    });
*/
</script>
