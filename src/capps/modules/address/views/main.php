<?php

global $objPlatformUser;


?>






<div id="app_main">

    <div class="container-fluid">
        <div class="row">

            <div class="col">
                <h1><cb:localize>Address</cb:localize></h1>
            </div>

            <div class="col text-end">

<div class="d-flex justify-content-end align-items-center gap-2">
                <div class="positionDEV-absolute" styDEVle="right: 60px; width:200px;padding-top:5px;">
                    <div styleDEV=" position: absolute;">
                        <span style="position:absolute; right:10px; top:9px; visibility:hidden; color: gray; font-size: 12px;" :style="searchText.length > 2 ? 'visibility:visible;' : ''" class="id_search_reset bi bi-x-lg" @click="searchText = '';listItems()"></span>
                        <input name="search" type="text" class="form-control foDEVrmular4 form-control-sm"  placeholder="Finden" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"  id="filter_search" v-model="searchText" />
                    </div>
                </div>

                <div class="positionDEV-absolute" styDEVle="right: 20px;">
                    <a @click="newItem()"><i class="btn btn-lg bi bi-plus-lg"></i></a>
                </div>

                <div class="dropdown text-end">
                    <div class=" btn-sm dropdown-toggle " type="button" data-bs-toggle="dropdown" aria-expanded="true" data-bs-auto-close="outside">
                        <div class="bi bi-three-dots-vertical" style="padding: 0px; margin-top:2px; margin-left: 0px; font-size:21px; color:#999;"></div>
                    </div>
                    <div class="dropdown-menu " style="width: 320px; padding: 12px; background-color: rgb(255, 255, 255); font-weight: 400; color: rgb(102, 102, 102); line-height: 24px; border-radius: 8px; border: 1px solid rgb(204, 204, 204); box-shadow: rgba(0, 0, 0, 0.15) 0px 0px 16px; position: absolute; inset: 0px 0px auto auto; margin: 0px; transform: translate3d(0px, 35px, 0px);" data-popper-placement="bottom-end">



                        <cb:navigation
                                entry="###page_structure_id###"
                                highlightDEVPath="1"
                                level3="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'> ###page_name###</a>"
                                level3_selected="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'> ###page_name###</a>"
                        />


                    </div>
                </div>

</div>

            </div>

        </div>
    </div>



    <div class="table-responsive contentarea" style="padding: 15px; margin-bottom: 15px;">

        <table class="table table-sm" id="table_list">
            <thead>
            <tr>
                <th class="cb_table_first_row"></th>
                <th>Name</th>
                <th>Login</th>
                <th>Addressgroups</th>
                <th>Last Login</th>
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
                //console.log(text)
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

            newItem() {

                globalDetailModal.show();

                var url = BASEURL+"/views/address/newItem/";

                $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#detailModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

            deleteItem: function (id) {

                var url = BASEURL+"controller/address/deleteItem/";
                url += "?id="+id;
                //alert(url);

                axios.get(url)
                    .then((response) => {
                        //alert(JSON.stringify(response.data));
                        globalDetailModal.hide();
                        this.listItems();
                    })
                    .catch(error => {
                        console.error(error);
                    });
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

</script>