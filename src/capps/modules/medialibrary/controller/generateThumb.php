<?php


if ( $_REQUEST['url_to_file'] != "" ) {

    // repair
    $_REQUEST['url_to_file'] = str_replace(":/","://",$_REQUEST['url_to_file']);
    $_REQUEST['url_to_file'] = str_replace(":///","://",$_REQUEST['url_to_file']);

    // dev
    /*
        echo "<pre>"; print_r($_REQUEST); echo "</pre>";
        echo "<pre>"; print_r(BASEURL); echo "</pre>";
        echo "<pre>"; print_r(BASEDIR); echo "</pre>";
        exit;
    */

    $arrPathInfo = pathinfo($_REQUEST['url_to_file']);
    //echo "<pre>"; print_r($arrPathInfo); echo "</pre>";


    //
    $objMediaTmp = CBinitObject('MediaLibrary',$_REQUEST['medialibrary_uid']);
    //echo "<pre>"; print_r($objMediaTmp); echo "</pre>";exit;


    //
    //$data = generatePreview("",$_REQUEST['url_to_file']);
//  	$data = generatePreview("",str_replace(BASEURL,BASEDIR,$_REQUEST['url_to_file']));
// 	echo str_replace(BASEURL,BASEDIR,$_REQUEST['url_to_file'])."---data<pre>"; print_r($data); echo "</pre>";exit;

    $strPath = $objMediaTmp->getAttribute('path');
    if ( !stristr($strPath,BASEDIR) ) $strPath = BASEDIR.$strPath;

    $_REQUEST['force_generation'] = "1";
    $data = generatePreview($strPath,$objMediaTmp->getAttribute('name'));
    //echo "<pre>data"; print_r($data); echo "</pre>";exit;

    //
    $path = $arrPathInfo['dirname'];
    $path = str_replace(BASEURL,BASEDIR,$path)."/";

    $file = $arrPathInfo['basename'];
    if ( $arrPathInfo['extension'] == "mp4" ) {
        $file = str_replace(".mp4",".mp4.jpg",$file);
    }
    if ( $arrPathInfo['extension'] == "m4v" ) {
        $file = str_replace(".m4v",".m4v.jpg",$file);
    }
    if ( $arrPathInfo['extension'] == "mpg" ) {
        $file = str_replace(".mpg",".mpg.jpg",$file);
    }
    if ( $arrPathInfo['extension'] == "mpeg" ) {
        $file = str_replace(".mpeg",".mpeg.jpg",$file);
    }
    if ( $arrPathInfo['extension'] == "pdf" ) {
        $file = str_replace(".pdf",".pdf.jpg",$file);
    }


    // echo "<pre>"; print_r($path); echo "</pre>";
    // echo "<pre>"; print_r($file); echo "</pre>";
    // exit;


    if ( is_file($path."_thumb_".$file) ) {
        //echo "DEV";
        unlink($path."_thumb_".$file);
    }

    file_put_contents($path."_thumb_".$file, $data);

    unset($data);

    ?>
    <script>

        editItem('<?php echo $_REQUEST['medialibrary_uid']; ?>');

    </script>
    <?php

}





?>