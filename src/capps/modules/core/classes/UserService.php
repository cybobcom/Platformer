<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

use Capps\Modules\Database\Classes\CBObject;

/**
 * User Service
 * 
 * File: capps/modules/core/classes/UserService.php
 */
class UserService
{
	public function getCurrentUser(): CBObject
	{
		$userId = $_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"] ?? "";
		return CBObject::make($userId, 'capps_address', 'address_uid');
	}
	
	public function getId(): string
	{
		return $_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"] ?? "";
	}
}