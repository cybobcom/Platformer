<?php

declare(strict_types=1);

namespace capps\modules\addressgroup\classes;

use capps\modules\database\classes\CBObject;
use capps\modules\database\classes\CBDatabase;

class AddressGroup extends \capps\modules\database\classes\CBObject {

    public function __construct(
        mixed $id = null,
        ?array $arrDB_Data = null,
        array $config = []
    ) {
        // Call parent constructor with agent-specific settings
        parent::__construct(
            $id,                    // ID to load
            'capps_addressgroup',          // Table name
            'addressgroup_uid',            // Primary key
            $arrDB_Data,            // Database config
            $config                 // Additional config
        );
    }
	
}
?>