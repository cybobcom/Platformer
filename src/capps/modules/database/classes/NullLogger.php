<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

// Simple PSR-3 compatible logger interfaces for legacy environments
if (!interface_exists('Psr\Log\LoggerInterface')) {
	interface LoggerInterface
	{
		public function emergency($message, array $context = array());
		public function alert($message, array $context = array());
		public function critical($message, array $context = array());
		public function error($message, array $context = array());
		public function warning($message, array $context = array());
		public function notice($message, array $context = array());
		public function info($message, array $context = array());
		public function debug($message, array $context = array());
		public function log($level, $message, array $context = array());
	}
} else {
	// Use the real PSR-3 interface if available
	//use Psr\Log\LoggerInterface;
}

// Simple logger implementations
class NullLogger implements LoggerInterface
{
	public function emergency($message, array $context = array()) {}
	public function alert($message, array $context = array()) {}
	public function critical($message, array $context = array()) {}
	public function error($message, array $context = array()) {}
	public function warning($message, array $context = array()) {}
	public function notice($message, array $context = array()) {}
	public function info($message, array $context = array()) {}
	public function debug($message, array $context = array()) {}
	public function log($level, $message, array $context = array()) {}
}

class SimpleLogger implements LoggerInterface
{
	private string $logFile;
	private bool $enabled;

	public function __construct(string $logFile = null, bool $enabled = true)
	{
		$this->logFile = $logFile ?? sys_get_temp_dir() . '/cbobject.log';
		$this->enabled = $enabled;
	}

	public function emergency($message, array $context = array())
	{
		$this->log('EMERGENCY', $message, $context);
	}

	public function alert($message, array $context = array())
	{
		$this->log('ALERT', $message, $context);
	}

	public function critical($message, array $context = array())
	{
		$this->log('CRITICAL', $message, $context);
	}

	public function error($message, array $context = array())
	{
		$this->log('ERROR', $message, $context);
	}

	public function warning($message, array $context = array())
	{
		$this->log('WARNING', $message, $context);
	}

	public function notice($message, array $context = array())
	{
		$this->log('NOTICE', $message, $context);
	}

	public function info($message, array $context = array())
	{
		$this->log('INFO', $message, $context);
	}

	public function debug($message, array $context = array())
	{
		$this->log('DEBUG', $message, $context);
	}

	public function log($level, $message, array $context = array())
	{
		if (!$this->enabled) {
			return;
		}

		$timestamp = date('Y-m-d H:i:s');
		$contextStr = !empty($context) ? ' ' . json_encode($context) : '';
		$logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;

		// Simple file logging with error handling
		@file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
	}
}


?>