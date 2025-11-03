<?php

	//
	// new
	//
    $objTmp = CBinitObject("Address");
    //CBLog($objTmp);
?>
<div id="app_newItem">


    <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Neuer Eintrag</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body">

        <form method="post" id="modal_item_new" class="form-horizontal" ref="form_new">

            <div class="container-fluid">
                <div class="row g-2">

                    <div class="form-floating mb-2">
                        <input type="text" name="save[firstname]" v-model="dictItem.firstname" placeholder="<cb:localize>Firstname</cb:localize>" class="form-control form-control-sm">
                        <label for="save[firstname]"><cb:localize>Firstname</cb:localize></label>
                    </div>

                </div>
            </div>

        </form>

    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-default" data-bs-dismiss="modal"><cb:localize>Schlie&szlig;en<</cb:localize>/button>
        <button type="button" class="btn btn-primary" @click="insertItem"><cb:localize>Speichern</cb:localize></button>
    </div>

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
                identifier: '<?php echo $_REQUEST["id"]??""; ?>',
                dictItem: <?php echo json_encode($objTmp->arrAttributes); ?>,

                searchText: '',

            }
        },
        watch: {

        },
        methods: {

            insertItem: function () {

                //
                const formEl = this.$refs.form_new;
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
                //CBLog( JSON.stringify(obj) );

                var url = BASEURL+"controller/address/insertItem/";

                axios.post(url, formData)
                    .then(response => {
                        //alert(JSON.stringify(response));

                        //
                        if ( response.data.id ) {
                            mountedAppMain.listItems();
                            mountedAppMain.editItem(response.data.id );
                        } else {
                            alert(response.data.description);
                        }

                    })
                    .catch(error => {
                        console.error(error);
                    });



            },



        },

        beforeMount(){

        },

    })

    //
    var mountedAppNewItem = appItem.mount('#app_newItem');

</script>
