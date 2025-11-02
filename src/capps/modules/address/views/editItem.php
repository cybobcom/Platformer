<?php

//
if ( $_REQUEST['id'] != "" ) {
	
	$objTmp = CBinitObject("Address",$_REQUEST['id']);
	//CBLog($objTmp);

    //
?>
<div class="app_item">

    <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel"><cb:localize>Edit item</cb:localize></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body">
        <form method="post" id="modal_item_update" class="form-horizontal" ref="item_update">

            <input name="id" type="hidden" class="form-control form-control-sm" :value="identifier">

            <div class="container-fluid">
                <div class="row g-2">

                    <div class="form-floating mb-2">
                        <input type="text" name="save[firstname]" v-model="dictItem.firstname" placeholder="<cb:localize>Firstname</cb:localize>" class="form-control form-control-sm">
                        <label for="save[firstname]"><cb:localize>Firstname</cb:localize></label>
                    </div>

                    <div class="form-floating mb-2">
                        <input type="text" name="save[lastname]" v-model="dictItem.lastname" placeholder="<cb:localize>Lastname</cb:localize>" class="form-control form-control-sm">
                        <label for="save[lastname]"><cb:localize>Lastname</cb:localize></label>
                    </div>

                    <div class="form-floating mb-2">
                        <input type="text" name="save[login]" v-model="dictItem.login" placeholder="<cb:localize>Login</cb:localize>" class="form-control form-control-sm">
                        <label for="save[login]"><cb:localize>Login</cb:localize></label>
                    </div>

                    <div class="form-floating mb-2">
                        <input type="text" name="save[password]" v-model="dictItem.password" placeholder="<cb:localize>Password</cb:localize>" class="form-control form-control-sm">
                        <label for="save[password]"><cb:localize>Password</cb:localize></label>
                    </div>

                    <div class="form-check m-2 my-3">
                        {{dictItem.active}}
<!--                        <input name="save[active]" type="hidden" value="0">-->
                        <input name="save[active]" type="checkbox" id="save[active]" value="1" v-model="dictItem.active" true-value="1" false-value="0"  class="form-check-input">
                        <label for="save[active]" class="form-check-label"><cb:localize>active</cb:localize></label>
                    </div>

                    <div class="form-floating mb-2">
                        <div class="container-fluid">
                            <div class="row">

                        <span><cb:localize>Nutzergruppe</cb:localize></span>

                        <?php

                        $arrAddressGroups = explode(",",$objTmp->getAttribute('addressgroups'));

                        $objAG = CBinitObject("Addressgroup");

                        $arrAG = $objAG->getAllEntries("sorting|name","ASC|ASC",NULL,NULL,"*");

                        if ( is_array($arrAG) && count($arrAG) >= 1 ) {
                            foreach ( $arrAG as $rAG=>$vAG ) {

                                //
                                $tmp = "";
                                if ( in_array($vAG["entity"],$arrAddressGroups) ) $tmp = "1";

                                echo '<div class="col-4">';
                                echo '<label for="'."addressgroups[".$vAG["entity"]."]".'" style="display:block;">';
                                //echo cb_makeCheckboxForm ("addressgroups[".$vAG["entity"]."]",$tmp,NULL,NULL,NULL,NULL,"classid_checkbox_addressgroup")." ".$vAG["name"]."<br>";
                                echo '<input type="hidden" name="addressgroups['.$vAG["entity"].']" value="0" />';
                                $checked = "";
                                if ( $tmp == "1" ) $checked = 'checked="checked"';
                                echo $tmp.'<input name="addressgroups['.$vAG["entity"].']" id="addressgroups['.$vAG["entity"].']" type="checkbox" '.$checked.' value="1" class="form-check-input">'." ".$vAG["name"]."<br>";
                                echo '</label>';
                                echo '</div>';

                            }
                        }


                        ?>
                            </div>
                        </div>

                    </div>
            </div>

        </form>

    </div>

    <div class="modal-footer">
        <span @click="deleteItem()" class="me-auto bi bi-trash"></span>
        <button type="button" class="btn btn-default" data-bs-dismiss="modal"><cb:localize>Close</cb:localize></button>
        <button type="button" class="btn btn-primary" @click="updateItem" ><cb:localize>Save</cb:localize></button>
    </div>



    <script>

    //
    var BASEURL = '<?php echo BASEURL; ?>';

    //
    var appItem = Vue.createApp({

        data() {
            return {

                BASEURL: window.BASEURL,
                hello: 'hi',
                identifier: '<?php echo $_REQUEST["id"]; ?>',
                dictItem: <?php echo json_encode($objTmp->arrAttributes); ?>,

                searchText: '',

            }
        },
        watch: {

        },
        methods: {

            updateItem: function () {

                //
                const formEl = this.$refs.item_update;
                const formData = new FormData(formEl);
/*
                // NUR die Felder aus dem Form senden
                for (const el of formEl.elements) {
                    if (!el.name) continue;       // skip unnamed elements
                    if (el.type === 'checkbox') {
                        // checkbox → nimm den Wert aus v-model, nicht el.checked
                        formData.append(el.name, this.dictItem[el.name.match(/\[([^\]]+)\]/)[1]]);
                    } else {
                        formData.append(el.name, el.value);
                    }
                }
                */
                const obj = {};
                for (const [key, value] of formData.entries()) {
                    obj[key] = value;
                }
                CBLog( JSON.stringify(obj) );

                var url = BASEURL+"controller/address/updateItem/";

                axios.post(url, formData)
                    .then(response => {

                        //
                        //alert(JSON.stringify(response));

                        //
                        globalDetailModal.hide();

                        //
                        listItems();

                    })
                    .catch(error => {
                        console.error(error);
                    });



            },

            deleteItem: function () {
                alert(this.identifier);
            },

        },

        beforeMount(){

        },

    })

    //
    var mountedApp = appItem.mount('.app_item');

</script>

<?php
}
?>