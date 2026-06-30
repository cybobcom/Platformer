<?php
namespace capps\modules\category\classes;

use capps\modules\database\classes\CBObject;

class Category extends \capps\modules\database\classes\CBObject
{

    function __construct($id = NULL)
    {

        $this->objDatabase = new \capps\modules\database\classes\CBDatabase();

        $this->strTable = 'capps_category';
        $this->strPrimaryKey = 'category_id';


        //
        $arrDatabaseColumns = $this->objDatabase->get("SHOW COLUMNS FROM " . $this->strTable);
        //echo "<pre>";print_r($arrDatabaseColumns);echo "-</pre>";
        if (is_array($arrDatabaseColumns) && count($arrDatabaseColumns) >= 1) {
            foreach ($arrDatabaseColumns as $run => $arrAttribute) {
                $this->arrAttributes[$arrAttribute['Field']] = '';
            }
        }

        //
        $this->arrDatabaseColumns = $arrDatabaseColumns;

        $this->identifier = $id;

        if ($this->identifier != NULL) $this->load($this->identifier);


    }


    function getAggregatedList($_search="") {

        //
        //
        //
        $arrCondition = array();
        //echo "<pre>"; print_r($arrCondition); echo "</pre>";

        $selection = "";

        if ( $_search != "" && $_search != "undefined" ) {
            if ( $selection != "" ) $selection .= " AND ";
            $selection .= " ( name LIKE '%".$_search."%' OR description LIKE '%".$_search."%' OR data LIKE '%".$_search."%' OR media LIKE '%".$_search."%' ) ";
        }


        $arrIDsAll = $this->getAllEntries('parent_id|sorting|name','ASC|ASC|ASC',$arrCondition,$selection,NULL);
        $arrIDs = $this->getAllEntries('parent_id|sorting|name','ASC|ASC|ASC',$arrCondition,$selection,"category_id,parent_id,name,sorting,value",NULL); // auch kein Limit, damit man alles berücksichtigen kann
        //debug_print_r($arrIDs);
        //echo "<pre>"; print_r($arrIDs); echo "</pre>";
        //echo "$selection<pre>"; print_r($arrIDs); echo "</pre>";



        $arrIDsStructure = array();
        $arrIDsData = array();
        $str = ",";
        if ( is_array($arrIDs) && count($arrIDs) >= 1 ) {
            foreach ( $arrIDs as $rID=>$vID ) {
                //$arrIDsStructure[$vID['parent_id']][$vID['category_id']] = $vID;

                $strParent = "root";
                if ( $vID['parent_id'] != "" ) $strParent = $vID['parent_id'];
                if ( !isset($arrIDsStructure[$strParent]) ) $arrIDsStructure[$strParent] = "";
                $arrIDsStructure[$strParent] .= $vID['category_id'].",";

                //
                if ( !isset($arrIDsData[$strParent]) ) $arrIDsData[$strParent] = array();
                if ( !isset($arrIDsData[$strParent]["children"]) ) $arrIDsData[$strParent]["children"] = "";
                if ( !isset($arrIDsData[$vID['category_id']]) ) $arrIDsData[$vID['category_id']] = "";

                if ( $arrIDsData[$strParent]["children"] != "" ) $arrIDsData[$strParent]["children"] .= ",";
                $arrIDsData[$strParent]["children"] .= $vID['category_id']."";
                $arrIDsData[$vID['category_id']] = $vID;
            }
        }
        //debug_
        //echo "<pre>"; print_r($arrIDsStructure); echo "</pre>";
        //echo "<pre>"; print_r($arrIDsData); echo "</pre>";



        foreach ( $arrIDsData as $rID=>$vID ) {
            list($path,$valuepath,$level) = $this->privateGetLevelPath($vID["category_id"] ?? "",$arrIDsData);
            $arrIDsData[$rID]["path"] = $path;
            $arrIDsData[$rID]["valuepath"] = $valuepath."/".($vID["value"] ?? "");
            $arrIDsData[$rID]["level"] = $level;
        }
        //echo "<pre>"; print_r($arrIDsData); echo "</pre>";




        $strIdList = ",";
        if ( is_array($arrIDsStructure) && count($arrIDsStructure) >= 1 ) {
            foreach ( $arrIDsStructure as $rID=>$vID ) {

                // first
                if ( $rID == "root" ) {
                    $strIdList .= "".$vID.",";
                    unset($arrIDsStructure[$rID]);
                    continue;
                }

                if ( strstr($strIdList,",".$rID.",") ) {
                    $search = ",".$rID.",";
                    $replace = ",".$rID.",".$vID."";
                    $strIdList = str_replace($search,$replace,$strIdList);
                    unset($arrIDsStructure[$rID]);
                } else {
                    //$strIdList .= "".$vID.",";
                }
            }
        }
        //echo "<pre>"; print_r($arrIDsStructure); echo "</pre>";

        // second run for sure
        if ( is_array($arrIDsStructure) && count($arrIDsStructure) >= 1 ) {
            foreach ( $arrIDsStructure as $rID=>$vID ) {

                if ( strstr($strIdList,",".$rID.",") ) {
                    $search = ",".$rID.",";
                    $replace = ",".$rID.",".$vID."";
                    $strIdList = str_replace($search,$replace,$strIdList);
                    unset($arrIDsStructure[$rID]);
                } else {
                    //$strIdList .= "".$vID.",";
                }
            }
        }

        //debug_print_r($strIdList);
        //echo "<pre>"; print_r($strIdList); echo "</pre>";
        //echo "<pre>"; print_r($arrIDsStructure); echo "</pre>";

        $strIdList = str_replace(",,",",",$strIdList);
        $strIdList = str_replace(",,",",",$strIdList);
        $strIdList = str_replace(",,",",",$strIdList);

        //if (str_starts_with($strIdList, ",")) $strIdList[0] = "";
        //if (str_ends_with($strIdList, ",")) $strIdList[strlen($strIdList)-1] = "";
        //debug_print_r($strIdList);

        $strIdList = trim($strIdList,",");
        $arrIDs = explode(",",$strIdList);
        //debug_print_r($arrIDs);
        //echo "<pre>"; print_r($arrIDs); echo "</pre>";


        // echo count($arrIDsAll)." Einträge";
        // echo ' <small>(Limit: 100)</small>';



        $arrCategoriesAggregated = array();
        //CBLog($arrIDs);
        if ( is_array($arrIDs) && count($arrIDs) >= 1 ) {
            foreach ($arrIDs as $run=>$strID){

                if ( $strID == "" ) continue;

                $strUID = trim($strID);
                $objTmp = new CBObject($strUID,"capps_category","category_id");
                //echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";

                // add aggreated info
                $objTmp->setAttribute("level",$arrIDsData[$strUID]["level"]);
                $objTmp->setAttribute("path",$arrIDsData[$strUID]["path"]);
                $objTmp->setAttribute("valuepath",$arrIDsData[$strUID]["valuepath"]);

                $arrCategoriesAggregated[$strUID] = $objTmp->arrAttributes;


            }

            /*
                //
                //echo "<pre>"; print_r($arrOrganizationAggregated); echo "</pre>";
                $json = json_encode($arrOrganizationAggregated);
                file_put_contents(BASEDIR."data/organization_aggregated.json", $json);
            */

        }
        //echo "<pre>"; print_r($arrCategoriesAggregated); echo "</pre>";

        //
        return $arrCategoriesAggregated;

    }

    function privateGetLevelPath($id,$arrIDs) {

        if ( is_array($arrIDs) ) {
            $path = ",";
            $valuepath = "/";
            $level = 0;

            if ( $arrIDs[$id]['parent_id']??"" == "" || $arrIDs[$id]['parent_id']??"" == "root" ) {
                $path = "root";
                $valuepath = "";
                $level = 0;
            } else {
                $path = "".$arrIDs[$id]['parent_id']."";
                $valuepath = "".$arrIDs[$path]['value']."";
                $level = $level + 1;

                // 				if ( !strstr($path,"".$arrIDs[$id]['parent_uid']."") ) { // to avoid endless recursion
                //$path .= privateGetlevelPath($arrIDs[$id]['parent_uid'],$arrIDs);
                // 					$path = privateGetlevelPath($arrIDs[$id]['parent_uid'],$arrIDs).",".$path;

                list($path2,$valuepath2,$level2) = $this->privateGetlevelPath($arrIDs[$id]['parent_id'],$arrIDs);
                $path = $path2.",".$path;
                $valuepath = $valuepath2."/".$valuepath;
                $level = $level2 + $level;

                // 				}


            }

        }

        return array($path,$valuepath,$level);
    }

}
