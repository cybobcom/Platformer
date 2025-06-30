<?php

namespace capps\modules\core\classes;

class CBParser {

    function __construct(){


    }

    public function parse($strTemplate, $objCS, $objC = NULL)
    {

        $strTemplate = $this->parseCBNavigationTag($strTemplate,$objCS,$objC);

        $strTemplate = $this->parseCBCheckTag($strTemplate,$objCS,$objC);

        return $strTemplate;

    }


    function convertTagAttributes($strTagAttributes) {
        //print_r($strTagAttributes);
        $navTagTmp = $strTagAttributes;
        $navTagTmp = str_replace("\n"," ",$navTagTmp); // for sure to have at least one space
        $navTagTmp = str_replace("\r","",$navTagTmp);
        $navTagTmp = str_replace("\t","",$navTagTmp);
        $navTagTmp = str_replace("  "," ",$navTagTmp); // if there are 2 spaces then make one

        // to make languag tag in address parsing possible
        $navTagTmp = str_replace('\\\\','"',$navTagTmp);



        //
        preg_match_all('/(.*)="(.*)"/Us',$navTagTmp,$arrTag) ;
        // info: $arrTag[0] = whole result
        // info: $arrTag[1] = attribute
        // info: $arrTag[2] = value
        //print_r($arrTag);
        $arrTmp = array();
        foreach ( $arrTag[0] as $run=>$value ) {
            $arrTag[2][$run] = str_replace("\"","",$arrTag[2][$run]); // delete " because of attribute defination
            $arrTag[2][$run] = str_replace("'","\"",$arrTag[2][$run]); // convert ' to " to make correct html-code
            $arrTmp[trim($arrTag[1][$run])] = trim($arrTag[2][$run]);
        }
        //print_r($arrTmp);


        return $arrTmp;
    }


    function parseCBNavigationTag($strTemplate, $objCS, $objC=NULL, $psIdCode = NULL) {

        //
        // navigation creation process
        //

        if(strstr($strTemplate, "<cb:navigation")){
            // get parameter
            preg_match_all('/<cb:navigation(.*) \/>/Us', $strTemplate, $arrTmp);

            // for each time
            foreach ($arrTmp[1] as $rT=>$vT ) {
                // get the attributes
                $arrAttributes = $this->convertTagAttributes($vT);
                //CBLog($arrAttributes);

                $strTmp = $this->getSystemNavigationString($arrAttributes);

                $strTmp = preg_replace('/###.*###/Us','',$strTmp);


                // finally parse
                $strTemplate = str_replace($arrTmp[0][$rT],$strTmp,$strTemplate);


            }

        }

        //
        return $strTemplate;
    }




    function getSystemNavigationString( $arrAttributes, $intSID = NULL, $intEntrySID = 0 ){

        global $coreArrSystemAttributes;
        global $coreArrSortedStructure;
        global $coreArrRoute;

        //
        // init
        //
        $strTmp = "";
        $strGesamtTemp = "";


        //
        //
        //
        $arrAttributesToCheck = array("level1","level2","level3","level4","level5","level6","level7","level1_selected","level2_selected","level3_selected","level4_selected","level5_selected","level6_selected","level7_selected","level1_path","level2_path","level3_path","level4_path","level5_path","level6_path","level7_path","level1_spacer","level2_spacer","level3_spacer","level4_spacer","level5_spacer","level6_spacer","level7_spacer","addtolevel1_haschildren","addtolevel2_haschildren","addtolevel3_haschildren","addtolevel4_haschildren","addtolevel5_haschildren","addtolevel6_haschildren","addtolevel7_haschildren","addtolevel1_hasnochildren","addtolevel2_hasnochildren","addtolevel3_hasnochildren","addtolevel4_hasnochildren","addtolevel5_hasnochildren","addtolevel6_hasnochildren","addtolevel7_hasnochildren");

        //
        foreach ( $arrAttributesToCheck as $rATC=>$vATC ) {

            if ( !isset($arrAttributes[$vATC] ) || $arrAttributes[$vATC] == "" ) continue;

            if ( !preg_match("/\D/",$arrAttributes[$vATC], $match) ) {  // if template id is given
                $objT = CBinitObject("ContentTemplate",$arrAttributes[$vATC]);
                if ( $objT->getAttribute('template') != "" ) $arrAttributes[$vATC] = $objT->getAttribute('template');
            }

        }
        //CBLog($arrAttributes);



        //
        // go through the parameter
        //
        if ( is_array($coreArrSortedStructure) ) {

            $arrAlreadyChecked = array(); // for pulldown

            // MAIN FOR
            foreach($coreArrSortedStructure as $sid => $dictStructure){

                // for sure
                if ( $sid == "" ) continue;

                // self
                if ( $arrAttributes['ignore'] ?? "" == "self" ) {
                    $request_sid = "";
                    if ( isset($_REQUEST["structure_id"]) && $_REQUEST["structure_id"] != "" ) $request_sid= $_REQUEST["structure_id"];
                    if ( $sid == $request_sid ) {
                        continue;
                    }
                }

                // get common navigation ids if type is not sitemap
                if ( isset($arrAttributes['type']) && $arrAttributes['type'] != "sitemap" ) {

                    if ( $arrAttributes['type'] != "pulldown" ) {

                    } else {
                        //echo "pulldown<br>";

                        if ( is_array($sid) ) continue;
                        $arrTmpList = $dictStructure['path'];

                        $boolList = true;
                        if ( is_array($arrTmpList) ) {
                            foreach ( $arrTmpList as $runList=>$valueList ) {
                                if ( $valueList == "" ) continue;

                                if ( !in_array($valueList,$arrAlreadyChecked) ) $boolList = false;
                            }
                        }
                        if ( $boolList != true ) {
                            continue;
                        }
                    }

                } else {
                    // sitemap - only if whole path is active


                    $arrTmpList = $dictStructure['path'];

                    $boolList = true;
                    if ( is_array($arrTmpList) ) {
                        foreach ( $arrTmpList as $runList=>$valueList ) {
                            if ( $valueList == "" ) continue;

                            //if ( !in_array($valueList,$arrAlreadyChecked) ) $boolList = false;
                        }
                    }
                    if ( $boolList != true ) continue;


                    $boolList = true;
                    $entry = "";
                    if ( isset($arrAttributes['entry']) && $arrAttributes['entry'] != "" ) $entry = $arrAttributes['entry'];
                    if ( strstr($entry,"###") ) {
                        $entry = str_replace("###page_structure_id###",$_REQUEST['structure_id'],$entry);
                    }
                    //CBLog($entry);

                    if ( $entry != "" ) {

                        $arrTmpPath = $dictStructure['path'];
                        //CBLog($arrTmpPath);

                        if ( !in_array($entry,$arrTmpPath) ) {
                            $arrAlreadyChecked[] = $sid;
                            $boolList = false;

                        }
                        if ( $arrAttributes['entry_ignore_self']??"" != "1" ) {
                            if ( $sid == $entry ) $boolList = true;
                        } else {
                            $arrAlreadyChecked[] = $sid;
                        }

                        if ( $arrAttributes['ignore']??"" == "self" ) {
                            if ( $sid == $entry ) {
                                $arrAlreadyChecked[] = $sid;
                                $boolList = false;
                            }
                        }

                            // only children
                        if ( $arrAttributes['entry_only_children']??"" == "1" ) {
                            $tmp = $dictStructure['path'][0];
                            if ( $entry != $tmp ) $boolList = false;
                        }

                    }
                    if ( $boolList != true ) continue;

                }


                // get path
                $arrPathTmp = array();
                if ( isset($_REQUEST['structure_id']) ) $arrPathTmp = $coreArrSortedStructure[$_REQUEST['structure_id']]['path'];
                if ( isset($arrAttributes['type']) && $arrAttributes['type'] == "breadcrumb" ) $arrPathTmp[] = $_REQUEST['structure_id'];



                // check if breadcrumb
                if ( isset($arrAttributes['type']) && $arrAttributes['type'] == "breadcrumb" ) {
                    if (is_array($arrPathTmp) && !in_array($sid,$arrPathTmp) ) continue;
                }





                $level = $dictStructure['level']+1;

                //CBLog($sid);
                //CBLog(array_keys($coreArrSortedStructure));


                if ( in_array($sid,array_keys($coreArrSortedStructure)) ) {
                    $objCStmp = CBinitObject("Structure",$sid);
                    //CBLog($objCStmp);
                    if ( $objCStmp->getAttribute("addressgroups") != "" ) {
                        $arrCSgroups = explode(",", $objCStmp->getAttribute("addressgroups"));
                        if (count($arrCSgroups) > 0) { // only if at least one group is available, otherwise the content is global
                            $boolChecked = FALSE;
//                        if ( is_array($_SESSION['user_groups']) ) {
//                            foreach ( $arrCSgroups as $r=>$v ) {
//                                if ( !in_array($v, $_SESSION['user_groups']) && $v != "0") continue;
//                                $boolChecked = TRUE;
//                            }
//                        }
                            //
                            global $objPlattformUser;
                            $arrAddressgroups = explode(",", $objPlattformUser->getAttribute("addressgroups"));
                            if (is_array($arrAddressgroups)) {
                                foreach ($arrCSgroups as $r => $v) {
                                    if (!in_array($v, $arrAddressgroups) && $v != "0") continue;
                                    $boolChecked = TRUE;
                                }
                            }

                            if ($boolChecked == FALSE) continue;
                        }
                    }
                }




                if( (isset($dictStructure['active']) && $dictStructure['active'] == '1') || ( isset($arrAttributes['ignoreActive']) && $arrAttributes['ignoreActive'] == "1" ) ){

                    // show in navigation
                    $tmp = "";
                    $tmp = $dictStructure['visible'];
                    if ( $arrAttributes['ignoreShowInNavigation']??"" != "1" ) {
                        if ( $tmp != "1" ) continue;
                    }
                    if ( $arrAttributes['ignoreVisible']??"" != "1" ) {
                        if ( $tmp != "1" ) continue;
                    }


                    // date
                    $tmp = $dictStructure['date_start'];

                    if (!empty($tmp) && $tmp >= time()  ) continue;

                    $tmp = $dictStructure['date_end'];

                    if (!empty($tmp) && $tmp <= time()  ) continue;

                    // normal
                    if ( $arrAttributes['level'.$level]??"" != "" ) {
                        $str = $arrAttributes['level'.$level];
                    } else {
                        continue;
                    }

                    // onlyLevelAndChildren
                    if ( $arrAttributes['onlyLevelAndChildren']??"" == "1" ) {


                        $arrTmp = $coreArrSortedStructure[$_REQUEST['structure_id']]['path'];
                        if ( $sid == $_REQUEST['structure_id'] ) $arrTmp[] = $_REQUEST['structure_id'];
                        if ( $_REQUEST['structure_id'] == $coreArrSortedStructure[$sid]['parent_id'] ) $arrTmp[] = $sid;
                        if ( !in_array($sid,$arrTmp) ) continue;


                    }

                    // highlight
                    $requestID = $_REQUEST['structure_id']??"";
                    //CBLog($requestID);

                    //
                    if ( $arrAttributes['level'.$level.'_selected'] != "" && $requestID == $sid ) {
                        $str = $arrAttributes['level'.$level.'_selected'];
                    }

                    // highlightPath
                    if ( $arrAttributes['highlightPath']??"" == "1" ) {

                        if ( is_array($arrPathTmp)  && count($arrPathTmp)) {
                            if ( in_array($sid,$arrPathTmp) )  {
                                $str = $arrAttributes['level'.$level.'_selected'];
                            }

                        }
                    }

                    // followPath
                    if ( $arrAttributes['followPath']??"" == "1" ) {
                        if ( is_array($arrPathTmp) ) {
                            if ( in_array($sid,$arrPathTmp) ) $str = $arrAttributes['level'.$level.'_path'];
                        }
                    }

                    // spacer
                    if ( $arrAttributes['level'.$level.'_spacer']??"" != "" ) {

                        $str = $arrAttributes['level'.$level.'_spacer']."\n".$str;
                    }


                    // addtolevel haschildren
                    if ( $arrAttributes['addtolevel'.$level.'_haschildren']??"" != "" ) {
                        $tmpStr = $arrAttributes['addtolevel'.$level.'_haschildren']."";
                        //echo $tmpStr."---";
                        if ( is_array($coreArrSortedStructure) ) {
                            $arrChildren = array();
                            foreach ( $coreArrSortedStructure as $run=>$value ) {
                                if ( is_array($run) ) continue;
                                if ( $value['parent_id'] == $sid ) {
                                    if ( $value['show_in_navigation'] == 0 ) continue;
                                    if ( $value['active'] == 0 ) continue;
                                    $arrChildren[] = $run;
                                }
                            }
                        }

                        if ( count($arrChildren) >= 1 ) $str = str_replace("###ADDTOLEVEL".$level."_HASCHILDREN###",$tmpStr,$str); // parse
                    }

                    // addtolevel hasnochildren
                    if ( $arrAttributes['addtolevel'.$level.'_hasnochildren']??"" != "" ) {
                        $tmpStr = $arrAttributes['addtolevel'.$level.'_hasnochildren']."";
                        //echo $tmpStr."---";
                        if ( is_array($coreArrSortedStructure) ) {
                            $arrChildren = array();
                            foreach ( $coreArrSortedStructure as $run=>$value ) {
                                if ( is_array($run) ) continue;
                                if ( $value['parent_id'] == $sid ) {
                                    if ( $value['show_in_navigation'] == 0 ) continue;
                                    if ( $value['active'] == 0 ) continue;
                                    $arrChildren[] = $run;
                                }
                            }
                        }

                        if ( count($arrChildren) <= 0 ) $str = str_replace("###ADDTOLEVEL".$level."_HASNOCHILDREN###",$tmpStr,$str); // parse
                    }



                    foreach ( $coreArrSortedStructure[$sid] as $rG=>$vG ) {
                        if($rG == "data"){
                            if ( strstr($str,"###page_data_") ) { // 09-12-07 bob : performance optimization

                                preg_match_all('/\<(.*)\>\<(.*)]]\>\<\/(.*)\>/Us',$vG,$arrTreffer); // requires cdata

                                foreach ( $arrTreffer[0] as $runTreffer=>$valueTreffer ) {
                                    $name = $arrTreffer[3][$runTreffer];


                                    $valueTreffer = str_replace("<![CDATA[","",$valueTreffer);
                                    $valueTreffer = str_replace("]]>","",$valueTreffer);
                                    $valueTreffer = str_replace("<".$name.">","",$valueTreffer);
                                    $valueTreffer = str_replace("</".$name.">","",$valueTreffer);



                                    $valueTreffer = @stripslashes($valueTreffer);
                                    if ( trim($arrAttributes['modify_length']??"") != "" || trim($arrAttributes['level'.$level.'_modify_length']??"") != "") {
                                        $intLength = $arrAttributes['modify_length'];
                                        if ( $arrAttributes['level'.$level.'_modify_length'] != "") $intLength = $arrAttributes['level'.$level.'_modify_length'];
                                        if ( strlen($valueTreffer) > $intLength ) {
                                            $strTail = "...";
                                            if ( $arrAttributes['modify_length_tail'] != "" ) $strTail = $arrAttributes['modify_length_tail'];
                                            if ( $arrAttributes['level'.$level.'_modify_length_tail'] != "") $strTail = $arrAttributes['level'.$level.'_modify_length_tail'];

                                            // vika new 2010-01-06, Auf abgeschnittenen HTML-Zeichen pr�fen und diese l�schen
                                            $strNewAttribute = substr($valueTreffer,0,$intLength);
                                            // Suche &-Zeichen
                                            $strHtmlTeil = strrchr($strNewAttribute,'&');
                                            if ($strHtmlTeil && $strHtmlTeil !='' && strlen($strHtmlTeil) <=7) {
                                                $strNewAttribute = str_replace($strHtmlTeil,'',$strNewAttribute);
                                            }
                                            // vika ende 2010-01-06
                                            $valueTreffer = $strNewAttribute.$strTail;
                                        }

                                    }
                                    $str = str_replace("###page_data_".$name."###",$valueTreffer,$str);

                                }
                            }
                        }else {
                            if ( strstr($str,"###page_") ) { // 09-12-07 bob : performance optimization
                                if ( is_array($vG) ) continue;
                                $vG = stripslashes($vG);
                                if ( $arrAttributes['modify_length']??"" != "" || ( isset($arrAttributes['level'.$level.'_modify_length']) && $arrAttributes['level'.$level.'_modify_length'] != "") ) {
                                    $intLength = $arrAttributes['modify_length'];
                                    if ( $arrAttributes['level'.$level.'_modify_length'] != "") $intLength = $arrAttributes['level'.$level.'_modify_length'];
                                    if ( strlen($vG) > $intLength ) {
                                        $strTail = "...";
                                        if ( $arrAttributes['modify_length_tail'] != "" ) $strTail = $arrAttributes['modify_length_tail'];
                                        if ( $arrAttributes['level'.$level.'_modify_length_tail'] != "") $strTail = $arrAttributes['level'.$level.'_modify_length_tail'];

                                        // vika new 2010-01-06, Auf abgeschnittenen HTML-Zeichen pr�fen und diese l�schen
                                        $strNewAttribute = substr($vG,0,$intLength);
                                        // Suche &-Zeichen
                                        $strHtmlTeil = strrchr($strNewAttribute,'&');
                                        if ($strHtmlTeil && $strHtmlTeil !='' && strlen($strHtmlTeil) <=7) {
                                            $strNewAttribute = str_replace($strHtmlTeil,'',$strNewAttribute);
                                        }
                                        // vika ende 2010-01-06
                                        $vG = $strNewAttribute.$strTail;
                                    }
                                }
                                $str = str_replace("###page_".$rG."###",$vG,$str); // parse
                            }
                        }
                    }


                    // parse ###LINK###
                    $str = $this->parseLink($str,$sid,NULL,$intSID,NULL,$coreArrSortedStructure[$sid]);

                    // parse ###QUOTE###
                    if ( strstr($str,"###QUOTE") ) $str = str_replace("###QUOTE###","'",$str);

                    // parse \n
                    if ( strstr($str,'\n') ) $str = str_replace('\n',"\n",$str);

                    // parse \t
                    if ( strstr($str,'\t') ) $str = str_replace('\t',"\t",$str);



                    // put string together
                    if ( strstr($strGesamtTemp,"###LEVEL") ) {

                        // same level
                        if ( $tmpLevel == $level ) $strGesamtTemp = str_replace("###LEVEL".($level+1)."###","",$strGesamtTemp);

                        // lower level
                        if ( $tmpLevel > $level ) $strGesamtTemp = str_replace("###LEVEL".($level+1)."###","",$strGesamtTemp);
                        if ( $tmpLevel > $level ) $strGesamtTemp = str_replace("###LEVEL".($level+2)."###","",$strGesamtTemp);
                        if ( $tmpLevel > $level ) $strGesamtTemp = str_replace("###LEVEL".($level+3)."###","",$strGesamtTemp);
                        if ( $tmpLevel > $level ) $strGesamtTemp = str_replace("###LEVEL".($level+4)."###","",$strGesamtTemp);
                        if ( $tmpLevel > $level ) $strGesamtTemp = str_replace("###LEVEL".($level+5)."###","",$strGesamtTemp);
                        if ( $tmpLevel > $level ) $strGesamtTemp = str_replace("###LEVEL".($level+6)."###","",$strGesamtTemp);

                        $strGesamtTemp = str_replace("###LEVEL".$level."###",$str,$strGesamtTemp); // 09-04-28 bob : to make verschachtelte navigation passible

                    } else {
                        $strGesamtTemp .= $str; // normal
                    }

                    $tmpLevel = $level;

                    $arrAlreadyChecked[] = $sid;

                }
                $strTmp = $strGesamtTemp;

            }

        }


        return $strTmp;
    }




    function parseLink ($strTemplate=NULL,$sid=NULL,$cid=NULL,$objCS=NULL,$objC=NULL,$arrSID=NULL) {

        global $coreArrSystemAttributes;
        global $coreArrSortedStructure;
        global $coreArrRoute;

        //
        if ( strstr($strTemplate,"###LINK") ) { // 09-12-01 bob : performance optimization

            //
            $strTemplate = $this->parseSystemAttributes($strTemplate);

            //
            $strTemplate = $this->parseRequest($strTemplate);



            //
            // comes from navigation
            //
            if ( is_array($arrSID) ) {
                // normal
                $sid = $arrSID['structure_id'];
            }

            //
            // comes other way
            //
            if ( is_object($objCS) ) {
                // normal
                $sid = $objCS->getAttribute('structure_id')."";
            }

            if ( is_object($objC) ) {
                $cid = $objC->getAttribute('content_id')."";
            }



            //
            // parse ###LINK###
            //
            if ( strstr($strTemplate,"###LINK###") ) {

                preg_match_all('/###LINK###/Us',$strTemplate,$arrTmp);

                if ( $coreArrSystemAttributes['seo_modrewrite'] != "1" ) {
                    // normal
                    $strTemplate = str_replace('###LINK###', ''.BASEURL.'index.php?sid='.$sid, $strTemplate);
                } else {
                    // mod rewrite

                    $tmp = $sid.'::'; // goes to normal page

                    if ( isset($coreArrRoute[$tmp]) ) $strCacheName = $coreArrRoute[$tmp];

                    if( $strCacheName != "" ) {

                        $strTemplate = str_replace('###LINK###', ''.BASEURL.$strCacheName, $strTemplate);

                    } else {
                        if ( current($coreArrSortedStructure) == $sid ) {
                            // 13-03-20 bob : if root page then webiste link may be enough
                            $strTemplate = str_replace('###LINK###', ''.BASEURL, $strTemplate);
                        } else {
                            $strTemplate = str_replace('###LINK###', ''.BASEURL.'index.php?sid='.$sid, $strTemplate);
                        }
                    }



                }
            }


            //
            // LINK HOME --- only possible for main system
            //
            if ( strstr($strTemplate,"###LINK_HOME###") ) {

                $start = current($coreArrSortedStructure);
                if ( $coreArrSystemAttributes['seo_modrewrite'] != "1" ) {
                    $strTemplate = str_replace('###LINK_HOME###','index.php?sid='.$start,$strTemplate);
                } else {

                    $tmp = ':'.$start.'::::';
                    $strCacheName = $coreArrRoute[$tmp];

                    if( $strCacheName != "" ) {
                        $strTemplate = str_replace('###LINK_HOME###', ''.BASEURL.$strCacheName, $strTemplate);
                    } else {
                        $strTemplate = str_replace('###LINK_HOME###', ''.BASEURL.'index.php?sid='.$start, $strTemplate);
                    }

                }
            }



            //
            // LINK Details
            //
            if ( strstr($strTemplate,"###LINK:") ) {


                // 09-05-05 bob : this is necessary to make correct links in page if you are displaying a abo page

                $sidTmp = $sid;
                $cidTmp = $cid;



                preg_match_all('/###LINK:(.*)###/Us',$strTemplate,$arrTmp) ; // get all links and put in array

                foreach ( $arrTmp[1] as $run=>$value ) { // array 0 has the complete result // array 1 the result without search string

                    $sid = $sidTmp;
                    $cid = $cidTmp;

                    $tmp = $arrTmp[1][$run];
                    $tmp2 = $tmp;
                    $tmp = explode(":",$arrTmp[1][$run]); // explode the result : 0 = id, 1 = name



                        $sid = $tmp[0];





                    $cid = $tmp[1];





                    if ( $tmp[5] != "" ) $aid = $tmp[5]; // 09-07-21 bob : add aid


                    if ( $coreArrSystemAttributes['seo_modrewrite'] != "1" ) {
                        // normal

                        $replace = ''.BASEURL.'index.php?sid='.$sid;
                        if ( $cid != "" ) $replace .= '&cid='.$cid;

                        if ( $aid != "" ) $replace .= '&address_id='.$aid; // 09-07-21 bob : add aid

                        $strTemplate = str_replace($arrTmp[0][$run], $replace, $strTemplate);


                    } else {
                        // mod rewrite

                        $tmp = $sid.':'.$cid.':::'; // goes to element

                        if ( $aid != "" ) $tmp = $sid.':'.$cid.':::'.$aid; //  add aid



                        //echo $tmp."-----<br>";
                        $strCacheName = $coreArrRoute[$tmp];
                        //echo '<pre>'; print_r($coreArrRoute); echo '</pre>';


                        if( $strCacheName != "" ) {
                            $strTemplate = str_replace($arrTmp[0][$run], ''.BASEURL.$strCacheName, $strTemplate);
                        } else {


                            $replace = ''.BASEURL.'index.php?sid='.$sid;
                            if ( $cid != "" ) $replace .= '&cid='.$cid;

                            if ( $aid != "" ) $replace .= '&address_id='.$aid; // 09-07-21 bob : add aid

                            $strTemplate = str_replace($arrTmp[0][$run], $replace, $strTemplate);


                        }
                    }

                }

            }



        }

        //
        return $strTemplate;
    }


    function parseSystemAttributes ($strTemplate) {

        if ( strstr($strTemplate,"###") ) {

            //
            global $coreArrSystemAttributes;

            //
            if ( is_array($coreArrSystemAttributes) ) {
                foreach ( $coreArrSystemAttributes as $run=>$value ) {
                    $strTemplate = str_replace('###'.strtolower($run).'###',$value,$strTemplate);
                }
            }

        }

        //
        return $strTemplate;
    }



    function parseRequest ($strTemplate) {

        if ( strstr($strTemplate,"###request_") ) {

            foreach ( $_REQUEST as $run=>$value ) {

                //
                if ( function_exists("protect_form") ) $value = protect_form($value);

                // frontend_message
                if ( $run == "frontend_message" ) $value = stripslashes($value);

                //
                $strTemplate = str_replace('###request_'.$run.'###',$value,$strTemplate);

            }


        }

        //
        return $strTemplate;
    }

    function parseCBCheckTag($strTemplate, $objCS, $objC=NULL,$objA=NULL) {
        if ( strstr($strTemplate,"<cb:check") ) {
            //include('parser_check_tag.php');


            $regex = "/<cb:check(\b[^>]*)>(?>(?:[^<]+|<(?!\/?cb:check\b[^>]*>))+|(?R))*<\/cb:check>/is";

            preg_match_all($regex,$strTemplate,$arrTmp);
            //CBLog($arrTmp);

            $arrTagFull = $arrTmp[0]; // complete result
            $arrTagAttribute = $arrTmp[1]; // attribute


            foreach ( $arrTagFull as $run=>$value ) {

                //echo "##########<pre>"; print_r($value); echo "</pre>++++++++++++++++";

                preg_match_all('/<cb:check (.*?)<\/cb:check>/Us', $value, $arrTmpFull); // get all if and put in array
                //echo "##########<pre>"; print_r($arrTmpFull); echo "</pre>++++++++++++++++";

                $arrTagContent = $arrTmpFull[1][0]; // content
                $arrTagContentTmp = strstr($arrTagContent, ">");
                $arrTagContentTmp = substr($arrTagContentTmp, 1);
                $arrTagContent = $arrTagContentTmp;
                //echo "<strong>arrTagContent:</strong><pre>"; print_r($arrTagContent); echo "</pre><br /><br />";


                $strSearch = $arrTagFull[$run];
                //$strReplace = $arrTagContent[$run];
                $strReplace = $arrTagContent;
                //CBLog($strReplace);

                // get the attributes
                $arrTagAttributes = $this->convertTagAttributes($arrTagAttribute[$run]);
                //echo "<pre>"; print_r($arrTagAttributes); echo "</pre>";
                //CBLog($_SESSION);
                /*
                 *  [7dbb40f08035f29d4e79dddc3969bdeb] => Array
                    (
                        [login_verified] => 1
                        [login_user_identifier] => 1
                    )
                 */


                //
                // check loggedin
                //
                if ( $arrTagAttributes['loggedin'] == "1" || $arrTagAttributes['loggedin'] == "on" || $arrTagAttributes['loggedin'] == "true" ) {
                    //echo "login: ".$_SESSION[PLATTFORM_IDENTIFIER]['login_verified']."-". $_SESSION[PLATTFORM_IDENTIFIER]['login_user_identifier'];
                    if ( !isset($_SESSION[PLATTFORM_IDENTIFIER]['login_verified'] ) || $_SESSION[PLATTFORM_IDENTIFIER]['login_verified'] == "" || !isset($_SESSION[PLATTFORM_IDENTIFIER]['login_user_identifier']) || $_SESSION[PLATTFORM_IDENTIFIER]['login_user_identifier'] == "" )  {
                        $strReplace = "";
                        //$boolParse = true;
                        //echo "DEV empty";
                    } else {
                        //echo "DEV full";
                    }
                }

                if ( $arrTagAttributes['loggedin'] == "0" || $arrTagAttributes['loggedin'] == "off" || $arrTagAttributes['loggedin'] == "false" ) {
                    //echo "login".$_SESSION[PLATTFORM_IDENTIFIER]['login_verified']."-".$_SESSION['aid'];
                    if ( ( isset($_SESSION[PLATTFORM_IDENTIFIER]['login_verified']) && $_SESSION[PLATTFORM_IDENTIFIER]['login_verified'] != "" ) || ( isset($_SESSION[PLATTFORM_IDENTIFIER]['login_user_identifier']) && $_SESSION[PLATTFORM_IDENTIFIER]['login_user_identifier'] != "" ) )  {
                        $strReplace = "";
                        //$boolParse = true;
                        //echo "DEV2";
                    }
                }



                // rekursiv
                if ( $strReplace != "" ) {
                    //echo "rekursiv";
                    //$strReplace = $this->parseCBCheckTag($strReplace,$objCS,$objC,$objA);
                }

                //if ( $boolParse == true )
                $strTemplate = str_replace($strSearch,$strReplace,$strTemplate);


            }

        }
        return $strTemplate;
    }


}