<?php

$arrPreset = array();

$dictEntry = array();
$dictEntry["type"] = "company";
$dictEntry["name"] = "Unternehmen";
$dictEntry["table_fields"] = array("customer_number"=>"Nummer","company"=>"Unternehmen");
$dictEntry["save_condition"] = array("customer_number");
$dictEntry["save_additional_fields"] = array("active"=>"1","type"=>"company");
$arrPreset["company"] = $dictEntry;

$dictEntry = array();
$dictEntry["type"] = "evaluator";
$dictEntry["name"] = "Prüfer";
$dictEntry["table_fields"] = array("customer_number"=>"Nummer","active"=>"aktiv","gender"=>"Geschlecht","firstname"=>"Vorname","lastname"=>"Nachname","login"=>"Login","password"=>"Password","company"=>"Unternehmen","street"=>"Straße","postcode"=>"PLZ","city"=>"Ort","phone"=>"Telefon","mobile"=>"Mobil","email"=>"E-Mail","addressgroups"=>"Nutzergruppen");
$dictEntry["save_condition"] = array("login");
$dictEntry["save_additional_fields"] = array("active"=>"1","type"=>"evaluator");
$arrPreset["evaluator"] = $dictEntry;

$dictEntry = array();
$dictEntry["type"] = "examinee";
$dictEntry["name"] = "Prüfling";
$dictEntry["table_fields"] = array("login"=>"Identnummer/Login","password"=>"Prüfungsnummer/Passwort","firstname"=>"Vorname","lastname"=>"Nachname","data_specialization"=>"Fachrichtung","data_specialization_short"=>"FR kurz","addressgroups"=>"Nutzergruppen");
$dictEntry["save_condition"] = array("login","password");
$dictEntry["save_additional_fields"] = array("active"=>"1","type"=>"company");
$arrPreset["examinee"] = $dictEntry;

//CBLog($arrPreset);


?>
<div class="container-fluid">
    <div class="row">
        <div class="col">
            <h1>###element_name###</h1>

        </div>
        <div class="col-3">

            <form method="post" action="" id="myForm">

                <input type="hidden" name="private_evaluator_id" value="<?php echo $_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"]; ?>">
                <textarea name="multiedit" id="multiedit" cols="100" rows="10" class="span6" style="display:none"></textarea>

            <?php

            if ( !isset($_REQUEST["filter_preset"]) || $_REQUEST["filter_preset"] == "" ) $_REQUEST["filter_preset"] = "company";

            $arrSelect = array();
            //$arrSelect["company"] = "Unternehmen";
            //$arrSelect["evaluator"] = "Prüfer";
            //$arrSelect["examinee"] = "Prüfling";
            foreach ($arrPreset as $key => $value) {
                $arrSelect[$key] = $value["name"];
            }

            $arrParameter = array();
            $arrParameter["name"] = "filter_preset";
            $arrParameter["selected"] = $_REQUEST["filter_preset"] ?? "";
            $arrParameter["placeholder"] = "Preset";
            $arrParameter["keys"] = array_keys($arrSelect);
            $arrParameter["values"] = array_values($arrSelect);
            echo CBmakeSelectForm($arrParameter);

            ?>
            </form>

        </div>
    </div>
</div>


<div id="dataTableconsole" style="width:100%"></div>
<div id="dataTable" style="width:100%" class="handsontable"></div>

<div id="container_content">
    ...
</div>



<script>

    //
    $(document).on('change', '[name="filter_preset"]', function () {
        listItems();
    });

    function listItems(){

        var url = "###BASEURL###views/address/listItemsMultiEdit/";
        url += "?filter_preset=" + $('[name="filter_preset"]').val();

        $( "#container_content" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#container_content" ).load( url, function() {
            //alert( "Load was performed. "+url );
        });

    }

    listItems();




        function save(){

            $("#multiedit").val(handsontable.getCopyableText(0,0,100,100));


            var tmp = $('#myForm').serializeArray();
            //alert( "save "+ JSON.stringify(tmp) );

            var url = "<?php echo BASEURL; ?>controller/address/doMultiEdit/";

            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'success': function(result){
                    //process here
                    //alert( "Load was performed. "+url );

                    //
                    //globalDetailModal.hide();

                    //
                    listItems();
                }
            });

        }

    </script>
    <br><br>
    <span class="btn btn-default btn-outline-primary" onclick="save()">Speichern</span><br>
    <br>




