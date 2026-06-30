<?php

ini_set('memory_limit','1256M');
ini_set('memory_limit', -1);

ini_set('upload_max_size','164M');
ini_set('post_max_size','164M');
ini_set('upload_max_filesize','164M');
ini_set('max_execution_time','3000');
ini_set('max_input_time','1000');



/*
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
*/



//echo "dev";


//
// init
//
//$intProjectId = $_REQUEST['id'];

global $coreArrSystemAttributes;


//
//
//

/*
$arrAdditionsDeny = array('php','sql','pl','exe','zip','rar','tar','gzip','c\+\+','gtar');

if (isset ($coreArrSystemAttributes['system_media_deny']) && $coreArrSystemAttributes['system_media_deny'] !='') {
	$arrAdditionsDeny = explode('|',$coreArrSystemAttributes['system_media_deny']);
}
*/


$arrAdditionsAllow = array('gif','png','jpg','jpeg','pdf','txt','rtf','doc','docx','xls','xlsx','csv','ppt','pptx','pps','mp4','m4v','mpg','mpeg','mp3','m4a');
/*
if (isset ($coreArrSystemAttributes['system_media_allow']) && $coreArrSystemAttributes['system_media_allow'] !='') {
	$arrAdditionsAllow = explode('|',$coreArrSystemAttributes['system_media_allow']);
}
*/

//global $arrConf;
//debug_print_r($arrConf);




// target
$target = "";
if ( $_REQUEST["target"] != "" ) $target = $_REQUEST["target"];

if ( $target != "" ) {
    if ( substr($target, -1) != '/' ) $target = $target."/";
}


// dev
$arrFiles = scandir($target);
//debug_
/*
echo "<pre>"; print_r($_REQUEST); echo "</pre>";
echo "<pre>"; print_r($arrFiles); echo "</pre>";
exit;
*/
//mail("robert.heuer@cybob.com","d",$target);


//
// file upload
//
if (isset($_FILES['uploaded_file'])) {

    $nameOriginal = $_FILES['uploaded_file']['name'];
    //mail("robert.heuer@cybob.com","nameOriginal",$nameOriginal);

    // modify filename
    $name = $_FILES['uploaded_file']['name']."";
    /*
//  	$name = urldecode($name);
// 	$name = utf8_decode($name);
// 	$name = strtolower($name);
    $name = str_replace(" ","_",$name);
    $name = str_replace(",","",$name);
    $name = str_replace("ä","ae",$name);
    $name = str_replace("Ä","ae",$name);
    $name = str_replace("ö","oe",$name);
    $name = str_replace("Ö","oe",$name);
    $name = str_replace("ü","ue",$name);
    $name = str_replace("Ü","ue",$name);
    $name = str_replace("ß","ss",$name);
    $name = strtolower($name); // IMPORTANT : first replace than lower string, otherwise string will be cut off
*/
    /*
        $nameBAK = $_FILES['uploaded_file']['name'];
        $nameBAK2 = $name;
    */

    $name = sanitizeFileName($name);

    $path_parts = pathinfo($name);
    if ( stristr($_REQUEST["configuration"], "addUT") ) {
//	if ( $_REQUEST["configuration"] == "addUT" ) {
        list($usec, $sec) = explode(" ", microtime());
        $usec = str_replace(".","",$usec);
        $name = $path_parts['filename']."_UT".date("ymdHis").$usec.".".$path_parts['extension'];
    } else {
        $name = $path_parts['filename'].".".$path_parts['extension'];
    }

    //
    if ( !in_array($path_parts['extension'], $arrAdditionsAllow) ) {
        echo "error"; exit;
    }

    //
    //mail("robert.heuer@cybob.com","d",$_FILES['uploaded_file']['tmp_name'].'---'.$target.$name);


    //
    if ( move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $target . $name) ) {

        //echo $_FILES['uploaded_file']['name']. " uploaded ...";
        //mail("robert.heuer@cybob.com","DEV",$target.$name);
        //mail("robert.heuer@cybob.com","_FILES['uploaded_file']['name']",$_FILES['uploaded_file']['name']);


        if ( is_file($target.$name) ) {

            $objM = CBinitObject('MediaLibrary');

            $arrCondition = array();
            $arrCondition['name'] = "".$name;
            //$arrCondition['path'] = "".$target.'';
            $arrCondition['path'] = str_replace(BASEDIR, "/", $target);

            $arrResult = $objM->getAllEntries(NULL,NULL,$arrCondition,NULL);
// 			echo "<pre>"; print_r($arrResult); echo "</pre>";

            if ( is_array($arrResult) && count($arrResult) >= 1 ) {
                //
                // TODO: file mod date
                //

                $intID = $arrResult[0]['medialibrary_uid'];

            } else {


                $arrSave = array();

                $arrSave['name'] = $name;
                //$arrSave['path'] = $target.'';
                $arrSave['path'] = str_replace(BASEDIR, "/", $target);
                $arrSave['title'] = $nameOriginal;
                $arrSave['date_created'] = date('Y-m-d H:i:s');
                $arrSave['address_uid'] = $_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"];

                //
                //$arrSave['medialibrary_uid'] = create_guid();

                //mail("robert.heuer@cybob.com","arrSave",serialize($arrSave));

                //$result["arrSave"] = serialize($arrSave);

                $obj= CBinitObject('MediaLibrary');

                $intID = $obj->saveContentNew($arrSave);
                //$intID = $arrSave['medialibrary_uid'];

                if ( stristr($_REQUEST["configuration"], "returnJSON") ) {
                    //
                    // json
                    //
                    $obj = CBinitObject('MediaLibrary',$intID);
                    echo json_encode($obj->arrAttributes);
                    exit;
                } else {
                    //
                    // id as plain text
                    //
                    echo $intID;
                    exit;
                }

                //$obj = connectClass('cmedia/Media.class.php',$intID);

            }

        }


        /*



                //
                // crop and scale
                //
                if ( stristr($target, "data/media/portraits/") || stristr($target, "data/media/logos/") ) {

        // 		    $file_to_convert = $target.$result["uploadName"];
                    $file_to_convert = $target.$name;

                    exec ("whereis convert",$whereis2);
                    //echo "exec (\"whereis ImageMagick\",$whereis2);<br>";
                    //echo "t<br>";
                    //echo "<pre>"; print_r($whereis2); echo "</pre>";
                    $arrConvert = explode(" ", $whereis2[0]);
                    //echo "$whereis2<pre>"; print_r($arrConvert); echo "</pre>";

                    $pathImagemagick = "";
                    if ( $arrConvert[1] != "" ) $pathImagemagick = $arrConvert[1];


                    if ( $pathImagemagick != "" ) {

                        $file = $target.$vF;
            //				  $strComado = "$path -density 72 ".$file."[0] -resize 50% ".$target.$strThumbName;
            // 		  		$strComado = "$pathImagemagick -density 72 -colorspace rgb -background white -alpha remove ".$file."[0] -resize 50% ".$target.$strThumbName;
                         $strComado = "$pathImagemagick $file_to_convert -resize 160x160^ -gravity Center -extent 160x160 $file_to_convert";
                              //echo "<pre>"; print_r( $strComado ); echo "</pre>";

                              //$IMagick =  new Imagick();
                              $return_var = shell_exec($strComado);

                    }

                }

        */



    } else {

        //echo $_FILES['uploaded_file']['name']. " NOT uploaded ...";

    }

    //exit;

} else {
    //echo "no";


    /*
        echo "no";
        exit;
    */

}




exit;




?>