<?php
namespace capps\modules\medialibrary\classes;
/*
class MediaLibrary extends \capps\modules\database\classes\CBObject
{

    function __construct($id = NULL)
    {

        $this->objDatabase = new \capps\modules\database\classes\CBDatabase();

        $this->strTable = 'capps_medialibrary';
        $this->strPrimaryKey = 'medialibrary_uid';


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
*/

class MediaLibrary extends \capps\modules\database\classes\CBObject
{
    /**
     * Initialize Agent object with modern CBObject features
     *
     * @param mixed $id Agent UUID to load (null for new agent)
     * @param array|null $arrDB_Data Database configuration (optional)
     * @param array $config Additional configuration options
     */
    public function __construct(
        mixed $id = null,
        ?array $arrDB_Data = null,
        array $config = []
    ) {
        // Call parent constructor with agent-specific settings
        parent::__construct(
            $id,                    // ID to load
            'capps_medialibrary',          // Table name
            'medialibrary_uid',            // Primary key
            $arrDB_Data,            // Database config
            $config                 // Additional config
        );
    }
}