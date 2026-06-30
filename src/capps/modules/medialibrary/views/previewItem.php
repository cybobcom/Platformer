<?php

// 	echo "<pre>"; print_r($_REQUEST); echo "</pre>";

if ( $_REQUEST["file"] != "" ) {

    // repair
    $_REQUEST["file"] = str_replace(":/","://",$_REQUEST["file"]);
    $_REQUEST["file"] = str_replace(":///","://",$_REQUEST["file"]);

    $arrPathInfo = pathinfo($_REQUEST["file"]);
    //echo "<pre>"; print_r($arrPathInfo); echo "</pre>";

    $arrShowInFrame = array("pdf","mp4","mv4","mpeg","mp3");
    $arrShowNotInFrame = array("jpg","jpeg","gif","png","gif"); // less variations

    if ( !in_array($arrPathInfo["extension"],$arrShowNotInFrame) ) {

        $strImage = $_REQUEST["file"];
        $strImage = str_replace(BASEDIR, BASEURL, $strImage);

        //
        echo '<iframe id="iframe" style="border:0px solid #666CCC; " title="item" src="'.$strImage.'" frameborder="10" scrolling="auto" height="100%" width="100%" onDEVload="this.height=this.contentWindow.document.body.scrollHeight;"></iframe>';

    } else {

        $strImage = $_REQUEST["file"];

        $strImagePath = str_replace(BASEURL, BASEDIR, $strImage);

        if ( !stristr($strImagePath,BASEDIR) ) $strImagePath = BASEDIR.$strImagePath;


        $arrImageInformation = getimagesize($strImagePath);
        //echo "<pre>"; print_r($arrImageInformation); echo "</pre>";

        /*
                    [0] => 2363
                    [1] => 1771
                    [2] => 2
                    [3] => width="2363" height="1771"
                    [bits] => 8
                    [channels] => 3
                    [mime] => image/jpeg
        */


        //
        $strImage = str_replace(BASEDIR, BASEURL, $strImage);

        ?>

        <div class="row">
            <div class="col-11">
                <div style="width: 100%; height: 100%; overflow: auto">
                    <svg id="svg_preview" width="1024" height="1024" viewBox="0 0 1024 1024" style=" height:auto; width:100%; background-color: transparent;" class="">
                        <image href="<?php echo $strImage; ?>" width="1024"/>
                    </svg>
                </div>
            </div>

            <div class="col-1 text-end">
                <div class="btn btn-default bi bi-plus classid_larger"></div>
                <div class="btn btn-default bi bi-dash classid_smaller"></div>
            </div>
        </div>

        <script>
            $(".classid_larger").click(function() {
                var w = $('#svg_preview').css("width");
                w = parseInt(w) * 1.1;
                $('#svg_preview').css("width",w);
            });
            $(".classid_smaller").click(function() {
                var w = $('#svg_preview').css("width");
                w = parseInt(w) * 0.9;
                $('#svg_preview').css("width",w);
            });
        </script>


        <?php

    }

}


?>