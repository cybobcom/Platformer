<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

/**
 * Logger Service
 * 
 * File: capps/modules/core/classes/LoggerService.php
 */
class LoggerService
{
	public function error(string $message, array $context = []): void
	{
		error_log("ERROR: {$message} " . json_encode($context));
	}
	
	public function info(string $message, array $context = []): void
	{
		if (defined('DEBUG_MODE') && DEBUG_MODE) {
			error_log("INFO: {$message} " . json_encode($context));
		}
	}
	
	public function debug(string $message, array $context = []): void
	{
		if (defined('DEBUG_MODE') && DEBUG_MODE) {
			error_log("DEBUG: {$message} " . json_encode($context));
		}
	}
}