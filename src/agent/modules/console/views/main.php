<?php
//echo "TODO Dashboard Console";



//
global $objPlattformUser;


$objTmp = CBinitObject("Agent");
//CBLog($objTmp);


//$objTmp = new Content();
//echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";exit;

$user_email = $objPlattformUser->getAttribute("user_email");

$arrCondition = array();
$arrCondition["deleted"] = "NOT 1";


$selection = " FIND_IN_SET('$user_email', owner) OR FIND_IN_SET('$user_email', admins) OR FIND_IN_SET('$user_email', editors) ";



$arrIDs = $objTmp->getAllEntries("name","ASC",$arrCondition,$selection);
//echo "$selection<pre>"; print_r($arrIDs); echo "</pre>";exit;


?>


<div class="classid_container_openaistatus contentarea p-3 mt-4" data-path="console/agents/">
    <b>OpenAI Status</b><br>
</div>
<br>

<?php

if (is_array($arrIDs) && count($arrIDs) >= 1) {
    /*
    echo '<div class="classid_button contentarea p-3 mt-4" data-path="console/agents/">';
    echo '<b>Deine Agenten</b><br>';
    foreach ($arrIDs as $run => $arrEntry) {


        $objTmp = CBinitObject("Agent", $arrEntry[$objTmp->strPrimaryKey]);

        echo $objTmp->getAttribute("name") . "<br>";


    }
    echo '</div>';
    */

    echo '<b>Deine Agenten</b><br>';

    echo '<div class="contaDiner-fluid"><div class="row row-cols-1 row-cols-md-4 g-4">';
    foreach ($arrIDs as $run => $arrEntry) {

        $objTmp = CBinitObject("Agent", $arrEntry[$objTmp->strPrimaryKey]);

        $strImage = BASEURL."data/template/assets/placeholder.png";
        if ( $objTmp->getAttribute("media_picture") != "" ) {
            $strImage = BASEURL.$objTmp->getAttribute("media_picture");
        }

        echo '<div class="col classid_button" data-path="console/agents/detail/context/" data-id="'.$arrEntry[$objTmp->strPrimaryKey].'">
    <div class="card">
      <img src="'.$strImage.'" class="card-img-top " alt="...">
      <div class="card-body">
        <h5 class="card-title">'.$objTmp->getAttribute("name").'</h5>
        <!--p class="card-text">This is a longer card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p-->
      </div>
    </div>
    </div>
    ';
    }
    echo '</div></div>';
}

?>





<script>

    //
    $(document).off('click', '.classid_entry');
    $(document).on('click', '.classid_entry', function () {
        //editAgentOfUser($(this).attr('data-id'));
    });

    //
    $(document).off('click', '.classid_button');
    $(document).on('click', '.classid_button', function () {
        document.location.href = BASEURL+$(this).attr("data-path")+"?identifier="+$(this).attr("data-id");
    });

    function displayOpenAIStatus(){

        //
        var url = "https://www.beezlebug.com/interface/news/&mode=getFeeds&search_link=status.openai.com&limit=3";

        $.ajax({
            'url': url,
            'type': 'POST',
            //'data': tmp,
            'success': function(result){
                //process here
                //alert( "Load was performed. "+result );

                for (let i = 0; i < result.length; i++) {
                    var item = result[i];
                    var entry = "<i>"+item.date+"</i> "+item.title+"<br>";
                    $(".classid_container_openaistatus").append( entry );
                }

                //$(".classid_container_openaistatus").html( JSON.stringify(result));

            }
        });
    }
    displayOpenAIStatus();

</script>
