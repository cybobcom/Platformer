<?php


if ( $_REQUEST['ac'] == "Category.deleteItems" ) {


    if ( is_array($_REQUEST['save']) && count($_REQUEST['save']) >= 1 ) {
        foreach ( $_REQUEST['save'] as $id=>$value ) {
            if ( $value == "1" ) {
// 					$objCategoryTmp = connectClass('ccategory/Category.class.php',$id);
                $objCategoryTmp = new BasisDB($id,"capps_category","category_id",$arrDB_Data);
                $objCategoryTmp->deleteEntry($id);
                echo "Deleting... ".$id."<br>";
                sleep(0.1);
            }
        }
    }