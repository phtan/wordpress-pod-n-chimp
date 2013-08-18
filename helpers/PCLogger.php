<?php

/*
 * This file contains the implementation of a logger for this plug-in.
 * 
 * TODO Adopt a class-based approach. Or simply expose the KLogger library
 * altogether, instead of using this thin wrapper.
 */

require_once(dirname(dirname(__FILE__)) . '/lib/KLogger.php');
require_once(dirname(dirname(__FILE__)) . '/config.php');

$logDirectory = dirname(dirname(__FILE__)) . "/logs";
$requestLogDirectory = $logDirectory . '/requests';
$logger;
$requestLogger;

// Declare loggers.
if ($chimpme_debug) {

	$logger = KLogger::instance($logDirectory, KLogger::NOTICE);
	$requestLogger = KLogger::instance($requestLogDirectory, KLogger::DEBUG);

} else {

	$logger = KLogger::instance($logDirectory, KLogger::NOTICE);
}

/*
 * Helper method. Logs the specified message to a text file.
 * @param $message
 *		The message to log, of type string.
 *      
 * @param $severity
 * 		The severity of the message, limited to "error", warn", "notice" and "info" for now.
 * 
 * @param $objectToPrint
 * 		An optional argument. Prints the contents of the object.
 */
function pnc_log($severity, $message, $objectToPrint = false) {
	global $logger;

	if(!$objectToPrint) {
		$objectToPrint = KLogger::NO_ARGUMENTS;
	}

	switch ($severity) {
	 	case 'error':
	 		$logger->logError($message, $objectToPrint);
	 		break;
	 	
	 	case 'warn':
	 		$logger->logWarn($message, $objectToPrint);
	 		break;

	 	case 'notice':
	 		$logger->logNotice($message, $objectToPrint);
	 		break;

	 	case 'info':
	 		$logger->logInfo($message, $objectToPrint);
	 		break;

	 	default:
	 		$logger->logInfo($message, $objectToPrint);
	 		break;
	 } 
}

?>