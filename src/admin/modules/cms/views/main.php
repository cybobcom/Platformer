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
    .sortable-ghost {
        opacity: 0.4;
        background: #f8f9fa;
    }

    .sortable-chosen {
        background: #e9ecef;
    }

    tr {
        cursor: move;
    }
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
            <i class="bi bi-arrow-left-right" @click="toggleEditMode()" :class="{'text-primary': editMode}"></i>

            <table id="table_pages" class="table table-sm table-hover">
                <tbody>
                <template v-for="(page, index) in arrPages" :key="page.structure_id">
                    <tr @click="showPage(page.structure_id)" :class="{'opacity-25': page.active != 1}">
                        <td>
                            <div :class="'ms-'+(page.level*2)">
                    <span v-if="editMode && index > 0" @click.stop="indentPage(index)">
                        <i class="bi bi-arrow-right"></i>
                    </span>
                                <span v-if="editMode && page.level > 0" @click.stop="outdentPage(index)">
                        <i class="bi bi-arrow-left"></i>
                    </span>
                                {{page.name}}
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
                                <b>{{element.name}}</b><br>
                                <small>{{ element.content?.slice(0, 300) }}{{ element.content?.length > 300 ? '…' : '' }}</small>

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
                editMode: false,

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
                        console.log(JSON.stringify(result));
                        this.arrElements = result.data;

                    })
            },

            showPage(id) {

                //alert(id);

                var url = BASEURL+"/views/cms/showPage/?id="+id;
                //alert(url);

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

                globalMediumModal.show();

                var url = BASEURL+"/views/structure/newItem/";

                $( "#mediumModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#mediumModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

            editPage(id) {

                globalMediumModal.show();

                var url = BASEURL+"/views/structure/editItem/?id="+id;
                //alert(url);

                $( "#mediumModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#mediumModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

            editElement(id) {

                globalMediumModal.show();

                var url = BASEURL+"/views/content/editItem/?id="+id;
                //alert(url);

                $( "#mediumModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#mediumModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

            newElement(structure_id) {

                globalMediumModal.show();

                var url = BASEURL+"/views/content/newItem/?structure_id="+structure_id;

                $( "#mediumModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
                $( "#mediumModalContent" ).load( url, function() {
                    //alert( "Load was performed." );
                })

            },

            initSortable: function() {

                // Sortable für Pages mit Nesting
                const tablePages = document.querySelector('#table_pages tbody');
                if (tablePages) {
                    this.sortablePages = Sortable.create(tablePages, {
                        animation: 150,
                        handle: 'tr',
                        disabled: true, // Standardmäßig deaktiviert
                        onEnd: (evt) => {
                            this.updatePagesOrder(evt);
                        }
                    });
                }

                // Sortable für Elements
                const tableElements = document.querySelector('#table_list tbody');
                if (tableElements) {
                    this.sortableElements = Sortable.create(tableElements, {
                        animation: 150,
                        handle: 'tr',
                        disabled: false,
                        onEnd: (evt) => {
                            this.saveElementsOrder(evt);
                        }
                    });
                }
            },

            toggleEditMode: function() {
                this.editMode = !this.editMode;

                // Sortable aktivieren/deaktivieren
                if (this.sortablePages) {
                    this.sortablePages.option("disabled", !this.editMode);
                }
                if (this.sortableElements) {
                    this.sortableElements.option("disabled", !this.editMode);
                }
            },

            indentPage: function(index) {
                if (index === 0) return; // Erste Seite nicht einrücken

                const page = this.arrPages[index];
                const prevPage = this.arrPages[index - 1];

                // Nur einrücken wenn vorherige Seite gleiche oder höhere Ebene
                if (prevPage.level >= page.level) {
                    page.parent_id = prevPage.structure_id;
                    page.level = parseInt(prevPage.level) + 1;
                    this.savePagesOrder();
                }
            },

            outdentPage: function(index) {
                const page = this.arrPages[index];

                if (page.level == 0) return; // Bereits auf oberster Ebene

                // Parent vom aktuellen Parent holen
                const currentParent = this.arrPages.find(p => p.structure_id === page.parent_id);
                if (currentParent) {
                    page.parent_id = currentParent.parent_id;
                    page.level = parseInt(page.level) - 1;
                    this.savePagesOrder();
                }
            },

            updatePagesOrder: function(evt) {
                // Array neu ordnen nach Drag & Drop
                const movedItem = this.arrPages.splice(evt.oldIndex, 1)[0];
                this.arrPages.splice(evt.newIndex, 0, movedItem);

                this.savePagesOrder();
            },

            savePagesOrder: function() {
                const grouped = {};

                this.arrPages.forEach(page => {
                    const parentKey = page.parent_id || '0';
                    if (!grouped[parentKey]) {
                        grouped[parentKey] = [];
                    }
                    grouped[parentKey].push(page);
                });

                const data = [];
                Object.keys(grouped).forEach(parentId => {
                    grouped[parentId].forEach((page, index) => {
                        data.push({
                            structure_id: page.structure_id,
                            parent_id: page.parent_id || '0',
                            level: page.level,
                            sorting: index + 1
                        });
                    });
                });

                const url = BASEURL + "controller/cms/sortPages";
                axios.post(url, { pages: data })
                    .then((result) => {
                        console.log('Pages order saved');
                        // KEIN this.listPages() mehr!
                    });
            },

            saveElementsOrder: function(evt) {
                const movedItem = this.arrElements.splice(evt.oldIndex, 1)[0];
                this.arrElements.splice(evt.newIndex, 0, movedItem);

                const ids = this.arrElements.map(element => element.content_id);

                const url = BASEURL + "controller/cms/sortElements";
                axios.post(url, { ids: ids })
                    .then((result) => {
                        console.log('Elements order saved');
                    });
            },

        },


        beforeMount(){
            this.listPages()
        },

        mounted() {
            this.initSortable();
        },

    })

    var mountedApp = appPages.mount('.app_pages')

</script>
