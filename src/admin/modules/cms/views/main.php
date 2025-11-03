<?php
//

$objTmp = CBinitObject("Structure");

$arrCondition = array();
$arrCondition["language_id"] = $_SESSION[PLATFORM_IDENTIFIER]["plattform_language_id"]??"1";
$arrCondition["language_id"] = "1";

$arrIDs = $objTmp->getAllEntries("parent_id|sorting","ASC|ASC",$arrCondition,NULL,"structure_id,parent_id,name");
//CBLog($arrIDs);


function sortStructureWithSorting(array $items): array {
    $sortedItems = array();

    // Arrays für Struktur und Parent IDs
    $arrStructureById = array();
    $arrParentIDs = array();

    // Aufbauen von Struktur- und Parent-IDs
    foreach ($items as $item) {
        $arrStructureById[$item["structure_id"]] = $item;
        $arrParentIDs[$item["parent_id"]][] = $item["structure_id"];
    }

    // Variable für die Struktur
    $strStructure = "";
    foreach ($arrParentIDs as $parent_id => $children) {
        $insert = "," . $parent_id . "," . implode(",", $children) . ",";
        if (stristr($strStructure, "," . $parent_id . ",")) {
            $strStructure = str_replace(",$parent_id,", $insert, $strStructure);
        } else {
            $strStructure .= $insert;
        }
    }

    // Struktur in ein Array umwandeln
    $arrStructure = explode(",", trim($strStructure, ","));

    // Wir erstellen eine Map für jedes Element mit einem Level und einem Pfad
    $itemPaths = array();

    // Initialisieren von Pfaden und Leveln
    foreach ($arrStructure as $item) {
        if ($item == "0") continue; // Ignoriere Root-Elemente

        $currentItem = $arrStructureById[$item];
        $parentId = $currentItem['parent_id'];
        $path = array();
        $level = 0;

        // Generiere den Pfad für das aktuelle Element, indem wir die Parent-IDs durchsuchen
        while ($parentId != 0) {
            if (isset($arrStructureById[$parentId])) {
                array_unshift($path, $parentId); // Eltern an den Anfang des Pfades setzen
                $parentId = $arrStructureById[$parentId]['parent_id']; // Zum nächsten Elternteil wechseln
                $level++;
            } else {
                break; // Falls keine Eltern-ID vorhanden ist, breche ab
            }
        }

        // Füge das aktuelle Element mit Pfad und Level zu der Map hinzu
        $currentItem['path'] = $path;
        $currentItem['level'] = $level;
        $itemPaths[$item] = $currentItem;
    }
    //CBLog($itemPaths);

    // Ergebnisse nach den berechneten Pfaden und Leveln sortieren
    // Wir durchlaufen jedes Element und fügen es in das Ergebnis ein
    foreach ($arrStructure as $item) {
        if ($item == "0") continue; // Ignoriere Root-Elemente
        $sortedItems[$item] = $itemPaths[$item];
    }

    // Rückgabe der sortierten und erweiterten Daten
    return $sortedItems;
}


//CBLog( sortStructureWithSorting($arrIDs) );


?>

<style>
</style>

<!--cb:navigation
    entry="###page_structure_id###"
    highlightDEVPath="1"
    level3="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'>###page_name###</a>"
    level3_selected="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'>###page_name###</a>"
 /-->


<div class="app_pages">

    <div class="container-fluid">
        <div class="row">

            <div class="col">
                <h1>###page_name###</h1>
            </div>

            <div class="col text-end">
                <div class="d-flex justify-content-end align-items-center gap-2">

                    <div class="dropdown text-end">
                        <div class=" btn-sm dropdown-toggle " type="button" data-bs-toggle="dropdown" aria-expanded="true" data-bs-auto-close="outside">
                            <div class="bi bi-three-dots-vertical" style="padding: 0px; margin-top:2px; margin-left: 0px; font-size:21px; color:#999;"></div>
                        </div>
                        <div class="dropdown-menu " style="width: 320px; padding: 12px; background-color: rgb(255, 255, 255); font-weight: 400; color: rgb(102, 102, 102); line-height: 24px; border-radius: 8px; border: 1px solid rgb(204, 204, 204); box-shadow: rgba(0, 0, 0, 0.15) 0px 0px 16px; position: absolute; inset: 0px 0px auto auto; margin: 0px; transform: translate3d(0px, 35px, 0px);" data-popper-placement="bottom-end">



                            <cb:navigation
                                    entry="###page_structure_id###"
                                    highlightDEVPath="1"
                                    level3="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'> ###page_name###</a><br>"
                                    level3_selected="<a href='###LINK###' class='###page_data_icon### ' title='###page_name###'> ###page_name###</a><br>"
                            />


                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>



<dic class="container-fluid">
    <div class="row">
        <div class="col-3">

            <div class="input-group">

                <input name="search"
                       type="text"
                       class="form-control foDEVrmular4 form_search"
                       value="" placeholder="Finden"
                       autocomplete="off"
                       autocorrect="off"
                       autocapitalize="off"
                       spellcheck="false"
                       id="filter_search"
                       v-model="searchText"

                />

                <button v-if="searchText" class="btn btn-outline-secondary" @click="clearSearch">X</button>
            </div>

            <i class="bi bi-plus-lg" @click="addPage()"></i>


            <table id="" class=" table table-sm table-hover">

                <tbody>
                <template v-for="(page, id) in arrPages">
                    <tr @click="showPage(page.structure_id)" :class="{'opacity-25': page.active != 1}" :style="dictPage.structure_id == page.structure_id ? 'background-color:rgba(0,0,0,0.1);' : ''" >

                        <td >
                            <div :class="'ms-'+(page.level*2)">
                                {{page.name}}<br>
                            </div>

                        </td>
                    </tr>
                </template>
                </tbody>

            </table>

        </div>

        <div class="col-9">

            <div v-if="typeof dictPage.structure_id !== 'undefined'">

                <h1>{{dictPage.name}}
                    <i class="bi bi-pencil me-3" style="font-size: 0.5em;" @click="editPage(dictPage.structure_id)"></i>

                    <small><a :href="BASEURL+dictPage.route" target="_blank" onclick="event.stopPropagation()">../{{dictPage.route}}</a></small>
                </h1>

                <table id="table_list" class=" table table-sm">

                    <tbody>
                    <template v-for="(element, id) in arrElements">
                        <tr @click="editElement(element.content_id)" :class="{'opacity-25': element.active != 1}">

                            <td >
                                <span>{{element.name}}</span>
                            </td>

                        </tr>
                    </template>
                    </tbody>

                </table>

                <i class="bi bi-plus-lg" @click="newElement(dictPage.structure_id)"> neues Element</i>

            </div>

            <div v-if="typeof dictPage.structure_id === 'undefined'">
                <i>Bitte eine Seite auswählen.</i>
            </div>


            </div>
    </div>
</dic>



</div>

<script>

    var BASEURL = '<?php echo BASEURL; ?>';

    var appPages = Vue.createApp({

        data() {
            return {

                BASEURL: window.BASEURL,
                hello: 'hi',
                arrPages: [],
                dictPage: {},
                arrElements: [],

                searchText: '',

            }
        },
        watch: {
            searchText: function (text) {
                if (text.length < 3) {
                    this.results = [];
                    return;
                }
                this.listPages();
            }
        },
        methods: {

            listPages: function() {


                var url = BASEURL+"views/cms/listPages";
                //url += "&date="+date;
                url += "&search="+encodeURI(this.searchText);

                axios.get(url)
                    .then((result) => {
                        //this.users = result.data
                        console.log(JSON.stringify(result));
                        this.arrPages = result.data;

                    })
            },

            //
            listElements: function(structure_id) {


                var url = BASEURL+"views/cms/listElements";
                url += "&structure_id="+structure_id;
                url += "&search="+encodeURI(this.searchText);
                //alert(url);

                axios.get(url)
                    .then((result) => {
                        //this.users = result.data
                        //alert(JSON.stringify(result));
                        this.arrElements = result.data;

                    })
            },

            showPage(id) {

                //alert(id);

                var url = BASEURL+"/views/cms/showPage/?id="+id;

                axios.get(url)
                    .then((result) => {
                        //this.users = result.data
                        console.log(JSON.stringify(result));
                        this.dictPage = result.data;

                        //
                        this.listElements(id);

                    })

            },

            addPage() {

                globalDetailModal.show();

                var url = BASEURL+"/views/structure/newItem/";

                $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#detailModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

            editPage(id) {

                globalDetailModal.show();

                var url = BASEURL+"/views/structure/editItem/?id="+id;
                //alert(url);

                $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#detailModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

            editElement(id) {

                globalDetailModal.show();

                var url = BASEURL+"/views/content/editItem/?id="+id;
                //alert(url);

                $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#detailModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

            newElement(structure_id) {

                globalDetailModal.show();

                var url = BASEURL+"/views/content/newItem/?structure_id="+structure_id;

                $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#detailModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

        },


        beforeMount(){
            this.listPages()
        },

    })

    var mountedApp = appPages.mount('.app_pages')

</script>
