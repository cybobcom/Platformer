<?php

declare(strict_types=1);

namespace capps\modules\address\classes;

use capps\modules\database\classes\CBObject;
use capps\modules\database\classes\CBDatabase;
//use Psr\Log\LoggerInterface;
//use Psr\Log\NullLogger;
use Capps\Modules\Database\Classes\NullLogger;


/**
 * Agent - Modernized Agent Management Class
 * 
 * Specialized CBObject for handling agent records.
 * Inherits all modern features from the updated CBObject.
 * 
 * @example
 * // Create new agent object
 * $agent = new Agent();
 * 
 * @example
 * // Load existing agent
 * $agent = new Agent('agent-uuid-123');
 * 
 * @example
 * // Load agent with custom database config
 * $agent = new Agent('agent-uuid-123', $customDbConfig);
 */
class Address extends CBObject
{
	/**
	 * Initialize Agent object with modern CBObject features
	 * 
	 * @param mixed $id Agent UUID to load (null for new agent)
	 * @param array|null $arrDB_Data Database configuration (optional)
	 * @param LoggerInterface|null $logger Logger instance (optional)
	 * @param array $config Additional configuration options
	 */
	public function __construct(
		mixed $id = null,
		?array $arrDB_Data = null,
		?LoggerInterface $logger = null,
		array $config = []
	) {
		// Call parent constructor with agent-specific settings
		parent::__construct(
			$id,                    // ID to load
			'capps_address',          // Table name
			'address_uid',            // Primary key
			$arrDB_Data,            // Database config
			$logger ?? new NullLogger(), // Logger
			$config                 // Additional config
		);
	}
}

?>