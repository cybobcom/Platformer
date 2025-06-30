<?php
namespace capps\modules\content\classes;

class Content extends \capps\modules\database\classes\CBObject
{

    function __construct($id = NULL)
    {

        $this->objDatabase = new \capps\modules\database\classes\CBDatabase();

        $this->strTable = 'capps_content';
        $this->strPrimaryKey = 'content_id';


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

}
