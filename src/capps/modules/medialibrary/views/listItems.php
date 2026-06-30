<?php

//CBLog($_REQUEST);
//CBLog($_SESSION);

//
// init
//

global $objPlatformUser;
//CBLog($objPlatformUser);




//
// categories
//
//
/*
$objCategory = CBinitObject('Category');
$arrCategoriesAggregated = $objCategory->getAggregatedList();
//echo "<pre>"; print_r($arrCategoriesAggregated); echo "</pre>";
*/



//
// media object
//
$objMediaLibrary = CBinitObject('MediaLibrary');
//echo "<pre>"; print_r($objMediaLibrary); echo "</pre>";	exit;

// system path
$media_path = BASEDIR."data/media/"."";
if ( isset($_SESSION["medialibrary_identifier"]) && $_SESSION["medialibrary_identifier"] != "" ) {
    $media_path .= "agent/".$_SESSION["medialibrary_identifier"]."/";
}
//echo "media_path<pre>"; print_r($media_path); echo "</pre>";

// part
$media_pathpart = str_replace(BASEDIR, "/", BASEDIR."data/media/");
//echo "media_pathpart<pre>"; print_r($media_pathpart); echo "</pre>";

// path work with
$current_path = $media_path;
//echo "<pre>"; print_r($current_path); echo "</pre>";	//exit;

//
/*
if ( !file_exists($current_path) ) {
    if ( @mkdir($current_path, 0777, true) ) {
          @chmod($current_path,0777);
    }

}
*/

// frontend
if ( $objPlatformUser->getAttribute('settings_medialibrary_current_directory') != "" ) $current_path = $objPlatformUser->getAttribute('settings_medialibrary_current_directory').'/';
$current_path = str_replace("//", "/", $current_path);
//echo "objPUI current_path<pre>"; print_r($current_path); echo "</pre>"; //exit;

if ( is_dir($current_path) && stristr($current_path,$media_path) ) {
    // nothing	
} else {
    //echo "nodir";
    // restore;
    $current_path = $media_path;
}
$current_path = str_replace("//", "/", $current_path);
//echo "final current_path<pre>"; print_r($current_path); echo "</pre>";



//
//  search
//
$arrCondition = array();
$arrCondition["type"] = "NOT directory";
$arrCondition["deleted"] = "NOT 1";
//echo "<pre>"; print_r($arrCondition); echo "</pre>";exit;

//
$selection = "";
$selection .= " path LIKE '".str_replace(BASEDIR,"/",$media_path)."%' ";	//2023-08-07 bob : change

//
$boolSearch = false;
if ( $_REQUEST['search'] != "" && $_REQUEST['search'] != "undefined" ) {

    //
    $boolSearch = true;

    //
    $_REQUEST['search'] = str_replace("/"," ",$_REQUEST['search']); // search & find full path

    //
    $arrSearch = explode(" ",$_REQUEST['search']);

    //
    if ( is_array($arrSearch) && count($arrSearch) >= 1 ) {

        foreach ( $arrSearch as $strSearch ) {

            if ( $strSearch == "" ) continue;
            if ( $strSearch == " " ) continue;
            if ( strlen($strSearch) < 2 ) continue;

            if ( $selection != "" ) $selection .= " AND ";
            $selection .= " ( name LIKE '%".$strSearch."%' OR title LIKE '%".$strSearch."%' OR description LIKE '%".$strSearch."%' OR path LIKE '%".$strSearch."%' ) ";

        }

    }
}
//echo "<pre>"; print_r($selection); echo "</pre>";//exit;


//
//
//
$arrFiles = array();


$strSnippetEntryPath = "";


if ( $boolSearch ) {

    // 
    // search
    //

    // get
    $arrIDsAll = $objMediaLibrary->getAllEntries('name','ASC',$arrCondition,$selection,"medialibrary_uid");
    $arrIDs = $objMediaLibrary->getAllEntries('name','ASC',$arrCondition,$selection,"medialibrary_uid","100");
    //debug_
    print_r($arrIDs);


    $arrFiles = $arrIDs;
    //echo "<pre>"; print_r($arrFiles); echo "</pre>";

    $strSnippetEntryPath .= count($arrIDsAll)." Einträge";
    $strSnippetEntryPath .= ' <small>(Limit: 100)</small>';

} else {

    // 
    // list / grid - path
    //

    if ( $current_path != "" ) {

        //echo "<pre>"; print_r($current_path); echo "</pre>";

        //
        $strBack = rtrim($current_path,"/");
        $strBack = str_replace(BASEDIR,"/",$strBack);
        $arrBack = explode("/", $strBack);
        //echo "<pre>"; print_r($arrBack); echo "</pre>";
        $arrBackTmp = array_pop($arrBack);
        //echo "<pre>"; print_r($arrBack); echo "</pre>";
        $strBack = implode("/", $arrBack)."/";
        //echo "strBack<pre>"; print_r($strBack); echo "</pre>";//exit;

        if ( isset($_SESSION["medialibrary_identifier"]) && $_SESSION["medialibrary_identifier"] != "" ) {
            if ( !stristr($strBack,$_SESSION["medialibrary_identifier"]) ) {
                $strBack .= $_SESSION["medialibrary_identifier"]."/";
            }
        }

        //$objTmp = checkIfDirectoryIsInDatabase($strPath,$strFile);
        //echo "<pre>"; print_r($objTmp); echo "</pre>";
        //CBLog("strBack:".$strBack);

        $strSnippetEntryPath .= '<span onclick="gotoDirectory(\''.$strBack.'\')" class="bi bi-chevron-left"></span>';

        //echo "<pre>"; print_r($current_path); echo "</pre>";
        $strSnippetEntryPath .= '<span onclick="gotoDirectory(\''.$strBack.'\')">';
        //$tmp = str_replace($media_path, "", $current_path);
        //echo $tmp;

        // frontend
        $arrTmp = explode("/", $objPlatformUser->getAttribute('settings_medialibrary_current_directory'));
        //echo "<pre>"; print_r($arrTmp); echo "</pre>";

        $strPathWithName = "";
        $strPathWithTitle = "";
        if ( is_array($arrTmp) && count($arrTmp) >= 1 ) {
            foreach ($arrTmp as $rT=>$vT) {
                //echo "<pre>"; print_r($vT); echo "</pre>";

                //
                if ($vT == "") continue;

                //
                $arrCondition = array();
                $arrCondition["type"] = "directory";
                $arrCondition["path"] = "" . $media_pathpart . $strPathWithName;
                $arrCondition["name"] = "" . $vT;
                //echo "<pre>"; print_r($arrCondition); echo "</pre>";

                $arrMediaTmp = $objMediaLibrary->getAllEntries(NULL, NULL, $arrCondition, NULL, "*");
                //echo "<pre>"; print_r($arrMediaTmp); echo "</pre>";

                //
                if (is_array($arrMediaTmp) && count($arrMediaTmp) >= 1) {
                    if ($arrMediaTmp[0]["name"] != "") $strPathWithName .= $arrMediaTmp[0]["name"] . "/";
                    if ($arrMediaTmp[0]["title"] != "") $strPathWithTitle .= $arrMediaTmp[0]["title"] . "/";
                }

                //
                if ( isset($_SESSION["medialibrary_identifier"]) && $_SESSION["medialibrary_identifier"] != "" ) {

                    if ($_SESSION["medialibrary_identifier"] == $vT) {
                        $obAgent = CBinitObject("Agent",$_SESSION["medialibrary_identifier"]);
                        $strPathWithName .= $vT . "/";
                        $strPathWithTitle .= "".$obAgent->getAttribute('name')."/";
                    }
                }

            }



        }

        //if ( $strPathWithTitle == "" ) $strPathWithTitle = "data/media";

        $strBase = "data/media/";
        if ( isset($_SESSION["medialibrary_identifier"]) && $_SESSION["medialibrary_identifier"] != "" ) {
            $strBase = "";
        }

        $strSnippetEntryPath .= $strBase.$strPathWithTitle;

        $strSnippetEntryPath .= '</span>';
        $strSnippetEntryPath .= '<br><br>';

    } else {

        //echo "<pre>"; print_r($current_path); echo "</pre>";
        $strSnippetEntryPath .= str_replace($media_path, "", $current_path);

    }

    //
    // get files
    //
    $arrFiles = array_diff(scandir($current_path), array('..', '.'));
    //echo "<pre>"; print_r($arrFiles); echo "</pre>";

}
//echo "arrFiles<pre>"; print_r($arrFiles); echo "</pre>";	exit;

//
echo cb_makeHiddenForm ("current_path",$current_path,"form-control formular4 id_current_path");



//
// display as list
//

// frontend
if ( $objPlatformUser->getAttribute('settings_medialibrary_toggle_gridlist') != "grid" ) {

    ?>

    <div class="table-responsive contentarea" style="padding: 15px; margin-bottom: 15px;">

        <div class="row">

            <div class="col text-start">
                <?php
                echo $strSnippetEntryPath;
                ?>
            </div>

        </div>


        <table class="table table-sm table-hover" id="table_list">
            <thead>
            <tr>
                <th width="60px"></th>
                <th>Name</th>
                <th>Kategorien</th>
                <th>Größe</th>
                <th>Format</th>
                <th width="50"></th>
            </tr>
            </thead>


            <?php

            //}




            if ( is_array($arrFiles) && count($arrFiles) >= 1 ) {
                foreach ($arrFiles as $run=>$strFile){
                    //echo "<pre>"; print_r($strFile); echo "</pre>";	continue;


                    if ( $boolSearch ) {

                        $strUID = trim($strFile["medialibrary_uid"]);
                        $objTmp = CBinitObject('MediaLibrary',$strUID."");

                        $strPath = $objTmp->getAttribute("path") ;
                        $strFile = $objTmp->getAttribute("name") ;

                    } else {

// 			$objTmp = checkIfFileIsInDatabase($current_path,$strFile);

                        $strPath = $current_path;

                    }
                    //echo "<pre>"; print_r($strPath); echo "</pre>";
                    //echo "<pre>"; print_r($strFile); echo "</pre>";



                    //
                    // ignore
                    //
                    if ( stringStartsWith($strFile,".") ) continue;
                    if ( stringStartsWith($strFile,"_") ) continue;

                    if ( stringStartsWith($strFile,"thumbpdf_") ) continue;
                    if ( stringStartsWith($strFile,"_thumb_") ) continue;
                    if ( stringStartsWith($strFile,"_deleted_") ) continue;



                    //
                    // list directories
                    //

                    $checkPathFile = $strPath.$strFile;
                    if ( !stristr($checkPathFile, trim(BASEDIR,"/") ) ) $checkPathFile = BASEDIR.$checkPathFile;

                    if ( is_dir($checkPathFile) ) {

                        $objTmp = checkIfDirectoryIsInDatabase($strPath,$strFile);
                        //echo "<pre>"; print_r($objTmp); echo "</pre>";

                        //$strPath = str_replace($media_path, "", $strPath.$strFile);
                        $strPathAndFile = $strPath.$strFile;

                        $strStyle = "";
                        ?>

                        <tr class="cbDEV_pointer" style="<?php echo $strStyle; ?>">

                            <td data-type="directory" data-path="<?php echo $strPathAndFile; ?>">
                                <div class="" style="width:40px; height: 40px; line-height: 24px; font-size: 36px; color: gray;" align="center"><span class="bi bi-folder"></span></div>
                            </td>

                            <td data-type="directory" data-path="<?php echo $strPathAndFile; ?>">
                                <?php
                                echo '<b>'.$objTmp->getAttribute('title').'</b>';
                                ?>
                            </td>

                            <td data-type="file" data-directory="<?php echo $strPath; ?>" data-file="<?php echo $strFile; ?>" data-id="<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>" data-action="<?php echo $strAcion; ?>">
                                <small>
                                    <?php
/*
                                    if ( $objTmp->getAttribute("categories") != "" ) {
                                        $arrUserCategories = explode(",",$objTmp->getAttribute("categories"));
                                        //echo "<pre>"; print_r($arrUserCategories); echo "</pre>";

                                        if ( is_array($arrUserCategories) && count($arrUserCategories) >= 1 ) {
                                            foreach ( $arrUserCategories as $rAG=>$vAG ) {
                                                //if ( !is_array($arrCategoriesAggregated[$vAG]) ) continue;
                                                if ( $rAG >= 1 ) echo ", ";
                                                echo $arrCategoriesAggregated[$vAG]["name"]."";
                                            }
                                        }

                                    }
                                    */
                                    ?>
                                </small>
                            </td>

                            <td></td>

                            <td></td>

                            <td align="right">
                                <span onclick="editDirectory('<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>')" class="hideUntilHover bi bi-pencil"></span>
                            </td>


                        </tr>
                        <?php

                    }



                    //
                    // list files
                    //
                    //echo "<pre>"; print_r($checkPathFile); echo "</pre>";

                    if ( is_file($checkPathFile) ) {

                        //
                        $objTmp = checkIfFileIsInDatabase($strPath,$strFile);
                        //echo "<pre>"; print_r($objTmp); echo "</pre>";

                        //
                        $arrPathInfo = pathinfo($strPath.$strFile);

                        $boolShowPreview = false;
                        if ( strtolower($arrPathInfo['extension']) == "jpg" ) $boolShowPreview = true;
                        if ( strtolower($arrPathInfo['extension']) == "jpeg" ) $boolShowPreview = true;
                        if ( strtolower($arrPathInfo['extension']) == "gif" ) $boolShowPreview = true;
                        if ( strtolower($arrPathInfo['extension']) == "png" ) $boolShowPreview = true;
                        if ( strtolower($arrPathInfo['extension']) == "pdf" ) $boolShowPreview = true;
                        if ( strtolower($arrPathInfo['extension']) == "mp4" ) $boolShowPreview = true;
                        if ( strtolower($arrPathInfo['extension']) == "m4v" ) $boolShowPreview = true;
                        if ( strtolower($arrPathInfo['extension']) == "mpg" ) $boolShowPreview = true;
                        if ( strtolower($arrPathInfo['extension']) == "mpeg" ) $boolShowPreview = true;

                        //
                        //
                        //
                        $path = $strPath;
                        $file = $strFile;

                        //
                        $strPath = str_replace(BASEDIR."data/media/", BASEURL."data/media/", $path);
                        //echo "<pre>"; print_r($strPath); echo "</pre>";	


                        //
                        $strStyle = "";

                        //
                        $strAction = "download";
                        if ( $boolShowPreview ) $strAction = "show";
                        ?>

                        <tr class="cbDEV_pointer" style="<?php echo $strStyle; ?>">

                            <td data-type="file" data-directory="<?php echo $strPath; ?>" data-file="<?php echo $strFile; ?>" data-id="<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>" data-action="<?php echo $strAction; ?>">
                                <?php
                                $strIcon = "bi bi-file";
                                if ( stristr(strtolower($strFile), ".jpg") ) $strIcon = "bi bi-image";
                                if ( strtolower($arrPathInfo['extension']) == "csv" ) $strIcon = "bi bi-filetype-csv";
                                if ( strtolower($arrPathInfo['extension']) == "xls" ) $strIcon = "bi bi-filetype-xls";
                                if ( strtolower($arrPathInfo['extension']) == "xlsx" ) $strIcon = "bi bi-filetype-xlsx";
                                if ( strtolower($arrPathInfo['extension']) == "txt" ) $strIcon = "bi bi-filetype-txt";
                                if ( strtolower($arrPathInfo['extension']) == "doc" ) $strIcon = "bi bi-filetype-doc";
                                if ( strtolower($arrPathInfo['extension']) == "docx" ) $strIcon = "bi bi-filetype-docx";
                                if ( strtolower($arrPathInfo['extension']) == "ppt" ) $strIcon = "bi bi-filetype-ppt";
                                if ( strtolower($arrPathInfo['extension']) == "pptx" ) $strIcon = "bi bi-filetype-pptx";
                                //echo '<span class="'.$strIcon.'"></span>';


                                // icon
                                // 			 	$strImageTmp = '<div onclick="downloadPrivateFile('.$objTmp->getAttribute('medialibrary_uid').')" style="width:80px;height:80px; font-size:40px; padding:20px;" class="'.$strIcon.'"></div>';
                                $strImageTmp = '<div class="'.$strIcon.'"></div>';

                                // or preview
                                if ( $boolShowPreview ) {

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

                                    //echo "<pre>"; print_r($path); echo "</pre>";
                                    //echo "<pre>"; print_r($file); echo "</pre>";
                                    //echo "<pre>"; print_r($path."_thumb_".$file); echo "</pre>";

                                    if ( !stristr($path,BASEDIR) ) $path = BASEDIR.$path;

                                    $data = "";
                                    if ( is_file($path."_thumb_".$file) ) {
                                        $data = file_get_contents($path."_thumb_".$file);
                                    } else {
                                        $data = generatePreview($path,$strFile);
                                        file_put_contents($path."_thumb_".$file, $data);
                                    }


//			 		$strImageTmp = '<img onclick="showPrivateFile('.$objTmp->getAttribute('medialibrary_uid').')" src="data:image/png;base64,'.base64_encode($data).'" class="img-thumbDEVnail" title="'.$file.' | '.getFilesizeWithPath($path.$file).'" width="80" height="80" style=" margin-right:10px;" /><br> ';//.$strTitle;	
// 			 		$strImageTmp = '<img onclick="showFile(\''.str_replace(BASEDIR,BASEURL,$objTmp->getAttribute('path').$objTmp->getAttribute('name')).'\')" src="data:image/png;base64,'.base64_encode($data).'" class="img-thumbDEVnail" title="'.$file.' | '.getFilesizeWithPath($path.$file).'" width="80" height="80" style=" margin-right:10px;" /><br> ';//.$strTitle;	
                                    $strImageTmp = '<img src="'.$strPath."_thumb_".$file.'?r='.time().'" class="img-thumbDEVnail" title="'.$file.' | '.getFilesizeWithPath($path.$file).'" width="80" height="80" style=" margin-right:10px;" /><br> ';//.$strTitle;	

                                }

                                echo $strImageTmp;

                                //
                                //$arrPathInfo = pathinfo($strPath.$strFile);
                                //echo "<pre>"; print_r($arrPathInfo); echo "</pre>";

                                ?>
                            </td>

                            <td data-type="file" data-directory="<?php echo $strPath; ?>" data-file="<?php echo $strFile; ?>" data-id="<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>" data-action="<?php echo $strAcion; ?>">
                                <?php
                                $strTitle = $objTmp->getAttribute('title');
                                if ( $strTitle == "" ) $strTitle = $objTmp->getAttribute('name');
                                echo '<b>'.$strTitle.'</b><br>';
                                if ( $boolSearch ) {
                                    echo '<small>'.$objTmp->getAttribute('path').'</small><br>';
                                }

                                //
                                if ( $objTmp->getAttribute('description') != "" ) {
                                    echo ''.$objTmp->getAttribute('description').'<br>';
                                }

                                //
                                if ( $objTmp->getAttribute('keywords') != "" ) {
                                    echo '<i>'.$objTmp->getAttribute('keywords').'</i><br>';
                                }
                                ?>
                            </td>

                            <td data-type="file" data-directory="<?php echo $strPath; ?>" data-file="<?php echo $strFile; ?>" data-id="<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>" data-action="<?php echo $strAcion; ?>">
                                <small>
                                    <?php
/*
                                    if ( $objTmp->getAttribute("categories") != "" ) {
                                        $arrUserCategories = explode(",",$objTmp->getAttribute("categories"));
                                        //echo "<pre>"; print_r($arrUserCategories); echo "</pre>";

                                        if ( is_array($arrUserCategories) && count($arrUserCategories) >= 1 ) {
                                            foreach ( $arrUserCategories as $rAG=>$vAG ) {
                                                //if ( !is_array($arrCategoriesAggregated[$vAG]) ) continue;
                                                if ( $rAG >= 1 ) echo ", ";
                                                echo $arrCategoriesAggregated[$vAG]["name"]."";
                                            }
                                        }

                                    }
                                    */
                                    ?>
                                </small>
                            </td>

                            <td data-type="file" data-directory="<?php echo $strPath; ?>" data-file="<?php echo $strFile; ?>" data-id="<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>" data-action="<?php echo $strAcion; ?>">
                                <?php
                                echo getFilesizeWithPath(BASEDIR.$objTmp->getAttribute('path').$objTmp->getAttribute('name'));
                                ?>
                            </td>

                            <td data-type="file" data-directory="<?php echo $strPath; ?>" data-file="<?php echo $strFile; ?>" data-id="<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>" data-action="<?php echo $strAcion; ?>">
                                <?php

                                $pathCheck = BASEDIR.$objTmp->getAttribute('path').$objTmp->getAttribute('name');
                                ///CBLog($pathCheck);
                                if ( is_file($pathCheck) ) {
                                    $arrImageInformation = getimagesize($pathCheck);
                                    //echo "<pre>"; print_r($arrImageInformation); echo "</pre>";

                                    /*
                                                [0] => 1920
                                                [1] => 1080
                                                [2] => 2
                                                [3] => width="1920" height="1080"
                                                [bits] => 8
                                                [channels] => 3
                                                [mime] => image/jpeg
                                    */

                                    if ( is_array($arrImageInformation) && count($arrImageInformation) >= 1 ) {
                                        echo $arrImageInformation[0]." x ".$arrImageInformation[1];
                                    }
                                }


                                ?>
                            </td>

                            <td align="right">
                                <span onclick="editItem('<?php echo $objTmp->getAttribute('medialibrary_uid'); ?>')" class="hideUntilHover bi bi-pencil"></span>
                            </td>


                        </tr>
                        <?php

                    }


                }
            }




            ?>

        </table>
    </div>

    <script>

        $('.classid_toggle_gridlist_grid').removeClass('button_toggle_selected');

        $('.classid_toggle_gridlist_list').addClass('button_toggle_selected');

    </script>



    <?php


} else {


    //
    // grid
    //

// 		echo '<hr>';


    echo "<br>";
    echo $strSnippetEntryPath;

    echo '<div class="container-fluid g-0">
	<div class="row g-0 ">
		';




    if ( is_array($arrFiles) && count($arrFiles) >= 1 ) {
        foreach ($arrFiles as $run=>$strFile){
            //echo "<pre>"; print_r($strFile); echo "</pre>";	continue;


            if ( $boolSearch ) {

                $strUID = trim($strFile["medialibrary_uid"]);
                $objTmp = CBinitObject('MediaLibrary',$strUID."");

                $strPath = $objTmp->getAttribute("path") ;
                $strFile = $objTmp->getAttribute("name") ;

            } else {

                // 			$objTmp = checkIfFileIsInDatabase($current_path,$strFile);

                $strPath = $current_path;

            }
            // echo "<pre>"; print_r($strPath); echo "</pre>";	
            // echo "<pre>"; print_r($strFile); echo "</pre>";	
            // continue;

            //
            // ignore
            //
            if ( stringStartsWith($strFile,".") ) continue;
            if ( stringStartsWith($strFile,"_") ) continue;

            if ( stringStartsWith($strFile,"_thumb_") ) continue;
            if ( stringStartsWith($strFile,"_deleted_") ) continue;




            $strImage = "";


            //
            // dir
            //

            $checkPathFile = $strPath.$strFile;
            if ( !stristr($checkPathFile, BASEDIR) ) $checkPathFile = BASEDIR.$checkPathFile;

            // echo "<pre>"; print_r($checkPathFile); echo "</pre>";	
            // continue;

            if ( is_dir($checkPathFile) ) {

                $objTmp = checkIfDirectoryIsInDatabase($strPath,$strFile);

                //$strPath = str_replace(BASEDIR."data/media/", "", $strPath.$strFile);
                //$strPath = str_replace($media_path, "", $strPath.$strFile);

                $strType = "directory";

                //$strName = $strFile;
                $strName = substr( htmlspecialchars($objTmp->getAttribute('title')) , 0,30);


                $strMediaID = "".$strPath.$strFile;

// 					$strImage = '<div class="" style="widDEVth:60px; height: 60px; line-height: 24px; font-size: 36px; color: gray;" align="center"><span class="ti-folder"></span></div>';

                $strImage = '<div onDEVclick="" style="widDEVth:120px;height:120px; font-size:40px; padding:40px; border:0px solid #E3E3E3; border-radius:2px; " class="bi bi-folder"></div>';

            }



            //
            // file
            //
            $boolShowPreview = false;
            $path = "";
            if ( is_file($checkPathFile) ) {

                //
                $objTmp = checkIfFileIsInDatabase($strPath,$strFile);
                //echo "<pre>"; print_r($objTmp); echo "</pre>";

                $arrPathInfo = pathinfo($strPath.$strFile);

                // 			$strPath = str_replace(BASEDIR."data/media/", "", $strPath);

                $strIcon = "bi bi-file";
                if ( stristr(strtolower($strFile), ".jpg") ) $strIcon = "bi bi-image";
                if ( strtolower($arrPathInfo['extension']) == "csv" ) $strIcon = "bi bi-filetype-csv";
                if ( strtolower($arrPathInfo['extension']) == "xls" ) $strIcon = "bi bi-filetype-xls";
                if ( strtolower($arrPathInfo['extension']) == "xlsx" ) $strIcon = "bi bi-filetype-xlsx";
                if ( strtolower($arrPathInfo['extension']) == "txt" ) $strIcon = "bi bi-filetype-txt";
                if ( strtolower($arrPathInfo['extension']) == "doc" ) $strIcon = "bi bi-filetype-doc";
                if ( strtolower($arrPathInfo['extension']) == "docx" ) $strIcon = "bi bi-filetype-docx";
                if ( strtolower($arrPathInfo['extension']) == "ppt" ) $strIcon = "bi bi-filetype-ppt";
                if ( strtolower($arrPathInfo['extension']) == "pptx" ) $strIcon = "bi bi-filetype-pptx";

                //echo '<span class="'.$strIcon.'"></span>';

                //
                //
                //
                $path = $strPath;
                $file = $strFile;
                // 			  	echo "<pre>"; print_r($path); echo "</pre>";
                // 			  	echo "<pre>"; print_r($file); echo "</pre>";

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



                // icon
                $strImage = '<div onDEVclick="downloadPrivateFile('.$objTmp->getAttribute('medialibrary_uid').')" style="widDEVth:160px;height:160px; font-size:40px; padding:40px; border:0px solid #E3E3E3; border-radius:2px; " class="'.$strIcon.'"></div>';


//                $boolShowPreview = false;
                if ( strtolower($arrPathInfo['extension']) == "jpg" ) $boolShowPreview = true;
                if ( strtolower($arrPathInfo['extension']) == "jpeg" ) $boolShowPreview = true;
                if ( strtolower($arrPathInfo['extension']) == "gif" ) $boolShowPreview = true;
                if ( strtolower($arrPathInfo['extension']) == "png" ) $boolShowPreview = true;
                if ( strtolower($arrPathInfo['extension']) == "pdf" ) $boolShowPreview = true;
                if ( strtolower($arrPathInfo['extension']) == "mp4" ) $boolShowPreview = true;
                if ( strtolower($arrPathInfo['extension']) == "m4v" ) $boolShowPreview = true;
                if ( strtolower($arrPathInfo['extension']) == "mpg" ) $boolShowPreview = true;
                if ( strtolower($arrPathInfo['extension']) == "mpeg" ) $boolShowPreview = true;

                // or preview
                if ( $boolShowPreview ) {

                    $data = "";
                    if ( is_file($path."_thumb_".$file) ) {

                        $data = file_get_contents($path."_thumb_".$file);
                        //echo $data;


                    } else {

                        $data = generatePreview($path,$strFile);

                        file_put_contents($path."_thumb_".$file, $data);

                        // 					 	unset($data);

                    }

                    // 				 	$strPath = str_replace(BASEDIR."data/media/", BASEURL."data/media/", $path);

                    /*
                                         if ( $arrPathInfo['extension'] == "mp4" ) {
                                            $file = str_replace(".mp4",".jpg",$file);
                                        }
                    */
                    //echo $strPath."_thumb_".$file;
                    // 				 	$strImage = '<img class="img-thumbnail" src="'.$strPath."_thumb_".$file.'" >';


//					 	$strImage = '<img src="data:image/png;base64,'.base64_encode($data).'" class="img-thumbDEVnail" title="'.$file.' | '.getFilesizeWithPath($path.$file).'" width="120" height="120" style=" margin-right:10px; border:0px solid #E3E3E3; border-radius:2px; padding:4px;" /><br> ';//.$strTitle;			 	

                    $strPathTmp = str_replace(BASEDIR."data/media/", BASEURL."data/media/", $path);


                    $strImage = '<img src="'.$strPathTmp.'_thumb_'.$file.'" class="img-thumbDEVnail" title="'.$file.' | '.getFilesizeWithPath($path.$file).'" width="160" height="160" style=" margin-right:10px; border:0px solid #E3E3E3; border-radius:2px; padding:4px;" /><br> ';//.$strTitle;

                }



                $arrPathInfo = pathinfo($strPath.$strFile);
                //echo "<pre>"; print_r($arrPathInfo); echo "</pre>";



                $strName = substr( htmlspecialchars($objTmp->getAttribute('title')) , 0,30);

                $strMediaID = base64_encode($objTmp->getAttribute('medialibrary_uid'));

                $strType = "file";


            }




            //	 		echo "<pre>"; print_r($objTmp); echo "</pre>";

            $strStyle = "";
// 				if ( $objTmp->getAttribute("active") != "1" ) $strStyle = "opacity:0.4;";

//
            //
            $strAction = "download";
            if ( $boolShowPreview ) $strAction = "show";

            //
            //echo "<pre>"; print_r($path); echo "</pre>";
            $strPath = str_replace(BASEDIR."data/media/", BASEURL."data/media/", $path);
            //echo "<pre>"; print_r($strPath); echo "</pre>";

            //
            //$strPathAndFile = $strPath.$strFile;


            //
            //
            //
            echo '<div class="col-md-2 classid_grid2 g-0" style="position:relative;">';

            echo '<div class="classid_grid" style="margin-bottom:10px; '.$strStyle.' "  align="center">';

            echo '<div class="grid_media contentarea" data-id="'.$strMediaID.'" data-type="'.$strType.'" data-directory="'.$strPath.'" data-file="'.$strFile.'" data-action="'.$strAction.'" align="center">';

            echo $strImage;
            echo '<small>'.$strName.'</small><br>';
            //echo htmlspecialchars($objTmp->getAttribute('data_subtitle')).'<br>';
            echo '</div>';
            echo '</div>';

            echo '<div class="classid_grid_edit bi bi-pencil xyz" data-id="'.$objTmp->getAttribute('medialibrary_uid').'" data-type="'.$strType.'" ></div>';

            echo '</div>';





        }
    }



    echo '</div></div>';


    ?>



    <script>
        $(document).off('click', '.grid_media');
        $(document).on('click', '.grid_media', function () {

            if ( $(this).attr('data-type') == "file" ) {
                //editItem($(this).attr('data-id'));
// 						showPrivateFile($(this).attr('data-id'))

                //showFile($(this).attr("data-directory")+$(this).attr("data-file"));

                if ( $(this).attr("data-action") == "show" ) {
                    showFile($(this).attr("data-directory")+$(this).attr("data-file"));
                }

                if ( $(this).attr("data-action") == "download" ) {
                    downloadFile($(this).attr("data-id"));
                }

            } else {
                gotoDirectory($(this).attr("data-id"));
            }

        });

        $(document).off('click', '.xyz');
        $(document).on('click', '.xyz', function () {
            //alert($(this).attr('data-id'));
            if ( $(this).attr('data-type') == "file" ) {
                editItem($(this).attr('data-id'));
            } else {
                editDirectory($(this).attr('data-id'));
            }

        });

        $('.classid_toggle_gridlist_list').removeClass('button_toggle_selected');

        $('.classid_toggle_gridlist_grid').addClass('button_toggle_selected');

    </script>

    <style>
        .classid_grid {
            z-index: 10;
        }
        .classid_grid_edit {
            /* 				display: none; */
            opacity: 0.0;
            position: absolute;
            top:10px;
            right:30px;
            z-index: 1000000;
        }
        .classid_grid:hover + .classid_grid_edit{
            /* 				display: block; */
            opacity: 1.0;
        }
        .grid_media:hover {
            box-shadow: 0 0 8px #DDD;
        }
        .classid_grid_edit:hover{
            opacity: 1.0;
        }
        .classid_grid_edit:hover + .grid_media{
            box-shadow: 0 0 8px #DDD;
        }

    </style>



    <?php







}






function stringStartsWith($string, $startString) {
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}

function checkIfFileIsInDatabase($current_path,$strFile) {
    //echo "current_path<pre>"; print_r($current_path); echo "</pre>";
    //echo "strFile<pre>"; print_r($strFile); echo "</pre>";
    //echo "BASEDIR<pre>"; print_r(BASEDIR); echo "</pre>";

    $strPathPart = str_replace(BASEDIR, "/", $current_path);
    //echo "strPathPart<pre>"; print_r($strPathPart); echo "</pre>";

    $objM = CBinitObject('MediaLibrary');

    if ( stringStartsWith($strFile,"thumbpdf_") ) return $objM;;
    if ( stringStartsWith($strFile,"_thumb_") ) return $objM;;
    if ( stringStartsWith($strFile,"_deleted_") ) return $objM;;

    $checkPathFile = $current_path.$strFile;
    //echo "checkPathFile1<pre>"; print_r($checkPathFile); echo "</pre>";
    if ( !stristr($checkPathFile, BASEDIR) ) $checkPathFile = BASEDIR.$checkPathFile;
    //echo "checkPathFile2<pre>"; print_r($checkPathFile); echo "</pre>";


    if ( is_file($checkPathFile) ) {


        $arrCondition = array();
        $arrCondition['type'] = "NOT directory";
        $arrCondition['name'] = "".$strFile;
        $arrCondition['path'] = "".$strPathPart.'';
        //echo "arrCondition<pre>"; print_r($arrCondition); echo "</pre>";

        $arrResult = $objM->getAllEntries(NULL,NULL,$arrCondition,NULL);
        //echo "arrResult<pre>"; print_r($arrResult); echo "</pre>";

        if ( is_array($arrResult) && count($arrResult) >= 1 ) {
            //
            // TODO: file mod date
            //

            $intID = $arrResult[0]['medialibrary_uid'];

        } else {
            //echo "new";

            $arrSave = array();

            $arrSave['name'] = $strFile;
            $arrSave['path'] = $strPathPart.'';
            $arrSave['title'] = $strFile;
            $arrSave['date_created'] = date('Y-m-d H:i:s');
            $arrSave['address_uid'] = $_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"];

            //
            //$arrSave['medialibrary_uid'] = create_guid();

            //				
            $intID = $objM->saveContentNew($arrSave);
            //$intID = $arrSave['medialibrary_uid'];
        }


        //
        $objM = CBinitObject('MediaLibrary',$intID);


    } else {
        echo "no file: ".$current_path.$strFile."<br>";
    }

    return $objM;

}

function checkIfDirectoryIsInDatabase($current_path,$strFile) {
    //echo "<pre>"; print_r($current_path); echo "</pre>";
    //echo "<pre>"; print_r($strFile); echo "</pre>";

    $strPathPart = str_replace(BASEDIR, "/", $current_path);
    //echo "<pre>"; print_r($strPathPart); echo "</pre>";

    $objM = CBinitObject('MediaLibrary');

    if ( stringStartsWith($strFile,"thumbpdf_") ) return $objM;;
    if ( stringStartsWith($strFile,"_thumb_") ) return $objM;;
    if ( stringStartsWith($strFile,"_deleted_") ) return $objM;;

    $checkPathFile = $current_path.$strFile;
    if ( !stristr($checkPathFile, BASEDIR) ) $checkPathFile = BASEDIR.$checkPathFile;
    //echo "<pre>"; print_r($checkPathFile); echo "</pre>";

    if ( is_dir($checkPathFile) ) {


        $arrCondition = array();
        $arrCondition['type'] = "directory";
        $arrCondition['name'] = "".$strFile;
        $arrCondition['path'] = "".$strPathPart.'';
        //echo "<pre>"; print_r($arrCondition); echo "</pre>";

        $arrResult = $objM->getAllEntries(NULL,NULL,$arrCondition,NULL);
        //echo "<pre>"; print_r($arrResult); echo "</pre>";

        if ( is_array($arrResult) && count($arrResult) >= 1 ) {
            //
            // TODO: file mod date
            //

            $intID = $arrResult[0]['medialibrary_uid'];

        } else {
            //echo "new";

            $arrSave = array();

            $arrSave['type'] = "directory";
            $arrSave['name'] = $strFile;
            $arrSave['path'] = $strPathPart.'';
            $arrSave['title'] = $strFile;
            $arrSave['date_created'] = date('Y-m-d H:i:s');
            $arrSave['address_uid'] = $_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"];

            //
            //$arrSave['medialibrary_uid'] = create_guid();

            //				
            $intID = $objM->saveContentNew($arrSave);
            //$intID = $arrSave['medialibrary_uid'];
        }

        //
        $objM = CBinitObject('MediaLibrary',$intID);

    } else {
        echo "no dir";
    }

    return $objM;

}




?>


<script>

    //$("#table_list td").click(function() {

    $(document).off('click', '#table_list td');
    $(document).on('click', '#table_list td', function () {

        //
        // directory
        //

        if ( $(this).attr("data-type") == "directory" ) {
// 		alert( $(this).attr("data-path") );
            gotoDirectory($(this).attr("data-path"));
        }

        //
        // file
        //
        if ( $(this).attr("data-type") == "file" ) {
            //alert( $(this).attr("data-action") );
//		gotoDirectory($(this).attr("data-value"));

            if ( $(this).attr("data-action") == "show" ) {
                showFile($(this).attr("data-directory")+$(this).attr("data-file"));
            }

            if ( $(this).attr("data-action") == "download" ) {
                downloadFile($(this).attr("data-id"));
            }

        }
    });

</script>

