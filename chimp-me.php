<?php
/*
Plugin Name: ChimpMe
Description: A WordPress plug-in that acts on Webhook events raised by a MailChimp mailing list. 
Version: 0.1
Author: Pheng Heong, Tan
Author URI: https://plus.google.com/106730175494661681756
*/

require_once(dirname(dirname(dirname(dirname( __FILE__ )))) . '/wp-load.php' ); // TODO find a better way to locate wp-load.php as it might not always be in the WP root folder.
require_once('/lib/KLogger.php');

// DB parameters
$dbTableName = "wp_pods_contact";
$dbEmailColumn = "email";

// MailChimp constants
$webhookEventKey = 'type'; // key as in array key.
$unsubscribeEvent = 'unsubscribe';
$updateEvent = 'profile';

$payloadKey = 'data'; 
$emailKey = 'email'; 

// Constants for this plug-in.
$requestLog = "POST_request_log.csv";
$logDirectory = dirname(__FILE__) . "/logs";
$logger = KLogger::instance($logDirectory, KLogger::DEBUG);

$chimpme_usingStubs = true; // Set this to false if receiving webhooks from MailChimp (ie. when this plug-in is sent to production.)

// Main logic of this plug-in.
if (!empty($_POST)) { // TODO check instead for a key appended to the URL given to the mailchimp API.

	logRequest();

	if (isset($_POST[$webhookEventKey])) {

		switch($_POST[$webhookEventKey]) {

			case $unsubscribeEvent:
				chimpme_unsubscribe($_POST['payloadKey']);
				chimpme_log('info', $_POST); // debug
				break;

			case $updateEvent:
				chimpme_update($_POST['payloadKey']);
				break;

			default:
				chimpme_log('error', "ChimpMe: Unknown action requested by webhook: "
					. $_POST['type']);
		}
	
	} else {
		// The 'type' key has no value.
		// MailChimp's webhook might have changed.
		// Do nothing for now.
		// TODO log something here.
	}

} else { // There hasn't been a POST request to this file.

	if ($chimpme_usingStubs) {
		sendPostRequestStub();
	}
}

/*
 * This method deletes the unsubscriber from an user-maintained
 * database.
 * @param data
 *	An array of values from MailChimp. // TODO confirm it is an array type.
 */
function chimpme_unsubscribe($data) {

	global $wpdb;
	global $dbTableName, $dbEmailColumn;

	$dbUnsubscribeColumn = "newsletter";
	$dbUnsubscribeBit = 0;
	// TODO use prepare statements in wpdb calls below.
	
	$unsubscriber = chimpme_getEmail($data);
    chimpme_log('notice', "Unsubscribing $unsubscriber...");

	$query = "SELECT * FROM $dbTableName
		WHERE $dbEmailColumn = '$unsubscriber'";
	$subscriber = $wpdb->get_row($query);

	if ($subscriber != null) {

		$unsubscribeQuery = "UPDATE $dbTableName
			SET $dbUnsubscribeColumn = '$dbUnsubscribeBit'
			WHERE $dbEmailColumn = '$unsubscriber'";

		// $deleteQuery = "DELETE FROM $dbTableName
		// 	WHERE $dbEmailColumn = '$unsubscriber'";
		
		$unsubscribeResult = $wpdb->query($unsubscribeQuery);
		
		if ($unsubscribeResult === false) { // using identicality check as both 0 and false might be returned.
			chimpme_log('error', "Database error. Unable to delete $unsubscriber.");
		}

	} else {
		chimpme_log('warn', "Unable to unsubscribe. $unsubscriber does not exist.");
	}
}

/**
 * Updates the corresponding information about a MailChimp subscriber in a database.
 * 
 * @param data
 * 	An array of values that MailChimp sends in its webhook callback.
 */
function chimpme_update($data) {
	
	$subscriber = chimpme_getEmail($data);
	chimpme_log('notice', "Updating $subscriber...");

	// TODO check Pods for data on the subscriber.
	$query = "SELECT * FROM $dbTableName
		WHERE $dbEmailColumn = '$unsubscriber'";
	$subscriber = $wpdb->get_row($query);

	// TODO check the webhook payload for corresponding data (consider a map of each MC grouping to the associated Pods field).

	// TODO overwrite the local subscriber with the MC one (assumes that the
	// payload always sends the subscriber data in its entirety (as opposed to sending only changed data)).
}


/*
 * Helper method to retrieve the email from the POST data sent by the webhook.
 */
function chimpme_getEmail($payload) {

	$result = "";
	global $chimpme_usingStubs;
	global $payloadKey, $emailKey;

	if ($chimpme_usingStubs) {
		// The HTTP request is a stub from the fireUnsubRequest method.
		// Receive the JSON payload.
		$json = stripslashes($payload); // Undo any quotes from PHP or WP in the POST stub.

		$payloadArray = json_decode($json, true);
		chimpme_log('info', $payloadArray); // debug

		$result = $payloadArray['email'];

	} else {
		// $payload should be an array containing data related to the
		// MailChimp event. (eg. Unsubscribe).
		$result = $payload[$emailKey];
	}
	
	// TODO verify this is a valid email address, else flag an error.
	return $result;
}

/*
 * Helper method to simulate a POST request fired by the webhook
 * at MailChimp.
 */
function sendPostRequestStub() {
	add_action('admin_footer', 'fireUnsubRequest'); // only works upon entering an admin page. (ie. WP Dashboard).
}

/*
 * Simulates a POST request from an MailChimp unsubscribe webhook.
 */
function fireUnsubRequest() {

	$unsubscriberEmail = 'user12@echoandhere.com';
	$requestTarget = plugins_url(basename(__FILE__), __FILE__); // Post back to this file.

	// Sample unsubscribe requestst:
	// 
	// type: unsubscribe
	// fired_at: 2013-07-02 09:58:09
    // data: {"action"=>"unsub", "reason"=>"manual", "id"=>"8249032551", "email"=>"phtan90@gmail.com", "email_type"=>"html", "ip_opt"=>"137.132.202.66", "web_id"=>"17701457", "campaign_id"=>"6fb66e1caf", "merges"=>{"EMAIL"=>"phtan90@gmail.com", "FNAME"=>"PH", "LNAME"=>"at Gmail"}, "list_id"=>"b970dd90fa"}
	echo "Sending POST stub...\n"; //debug

    $response = wp_remote_post( $requestTarget, array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking' => true,
		'headers' => array(),
		'body' => array( 'type' => 'unsubscribe',
			'fired_at' =>  date(DATE_ISO8601),
			'data' => json_encode(
				array(
				"action"=>"unsub", "reason"=>"manual",
				"id"=>"8249032551", "email"=>$unsubscriberEmail,
				"email_type"=>"html", "ip_opt"=>"137.132.202.66",
				"web_id"=>"17701457", "campaign_id"=>"6fb66e1caf",
				"merges"=> array("EMAIL"=>$unsubscriberEmail,
					"FNAME"=>"PH", "LNAME"=>"at Gmail"),
				"list_id"=>"b970dd90fa"))),
		'cookies' => array()
	    )
    );

    

    if( is_wp_error( $response ) ) {
    	$error_message = $response->get_error_message();
    	echo "Something went wrong: $error_message";
    } else {
    	echo 'Response: ';
    	print_r( $response );
    }

}

/*
 * Helper method to inspect incoming POST requests.
 * Deposits a CSV file in this plug-in directory.
*/
function logRequest() {

	global $requestLog;
	global $payloadKey;

	if (!$file = fopen($requestLog, "w")) {

		$error = "Cannot open file $requestLog";

		echo $error;
		// chimpme_log('error', $error);

	} else {

		foreach ($_POST as $key => $value) {

			$keyValuePair = array($key, $value);
			fputcsv($file, $keyValuePair);

			if ($key = $payloadKey && is_array($value)) { // Check that this is a MailChimp payload.
				fputcsv($file, array("(CHIMP-ME NOTICE)", "===== Printing contents of \"$payloadKey\" ====="));

				foreach ($value as $k => $v) { // Print the inner array.
					$pair = array($k, $v);
					fputcsv($file, $pair);
				}

				fputcsv($file, array("(CHIMP-ME NOTICE)", "===== Printed \"$payloadKey\" ====="));
			}
		}

		fclose($file);

		
		
	}
}

/*
 * Helper method. Logs the specified message to a text file.
 * @param $message
 *		The message to log, of type string.
 * @param $severity
 * 		The severity of the message, limited to "error", warn", "notice" and "info" for now.
 */
function chimpme_log($severity, $message) {
	global $logger;

	switch ($severity) {
	 	case 'error':
	 		$logger->logError($message);
	 		break;
	 	
	 	case 'warn':
	 		$logger->logWarn($message);
	 		break;

	 	case 'notice':
	 		$logger->logNotice($message);
	 		break;

	 	case 'info':
	 		$logger->logInfo($message);
	 		break;

	 	default:
	 		$logger->logInfo($message);
	 		break;
	 } 
}

?>