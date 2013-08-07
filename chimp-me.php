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

// Pods CMS parameters
$podName = 'contact';
$podEmailField = 'email';

// MailChimp constants
$webhookEventKey = 'type'; // key as in array key.
$unsubscribeEvent = 'unsubscribe';
$updateEvent = 'profile';

$payloadKey = 'data'; 
$emailKey = 'email'; 

// Constants for this plug-in.

// ======== Ensure the below values are specified in the webhook callback URL passed to MailChimp =============
// (The URL should look like http://send.callback.here/this-plugin.php?authenticateKey=authenticateValue)
$authenticateKey = "key";
$authenticateValue = "helloFromMailChimp";
// ======================================== End authentication section ========================================

$requestLog = "POST_request_log.csv";
$logDirectory = dirname(__FILE__) . "/logs";
$logger = KLogger::instance($logDirectory, KLogger::DEBUG);

$chimpme_usingStubs = true; // Set this to false if receiving webhooks from MailChimp (ie. when this plug-in is sent to production.)
$chimpe_stubType = 'update';

// Main logic of this plug-in.

// TODO Un-comment the next 2 lines in production.
// if (isset($_POST[$authenticateKey]) &&
// 		$_POST[$authenticateKey] == $authenticateValue) { // This is a callback from MailChimp.

if (!empty($_POST)) {

	logRequest();

	if (isset($_POST[$webhookEventKey])) {

		switch($_POST[$webhookEventKey]) {

			case $unsubscribeEvent:
				chimpme_unsubscribe($_POST[$payloadKey]);
				chimpme_log('info', $_POST); // debug
				break;

			case $updateEvent:
				chimpme_update($_POST[$payloadKey]);
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

} elseif (empty($_POST)) { // There hasn't been a POST request to this file.

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

	if ($subscriber != null) { // TODO move this check (and the SQL above) into the future DAO class' unsubscribe method.

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
	
	global $wpdb;
	global $dbTableName, $dbEmailColumn;

	$mailchimp_subscriber_email = chimpme_getEmail($data);
	chimpme_log('notice', "Updating $mailchimp_subscriber_email...");

	$query = "SELECT * FROM $dbTableName
		WHERE $dbEmailColumn = '$mailchimp_subscriber_email'";
	$local_subscriber = $wpdb->get_row($query);

	if ($local_subscriber != null) { // TODO move this check (and the SQL above) into the future DAO class' update method.

		$localData = getSubscriberDataFromPods($mailchimp_subscriber_email); // TODO Get a hash of the local data in Pods.
		chimpme_log('info', "Found the subscriber: ", $localData); // debug

		// TODO check the webhook payload for corresponding data (consider a map of each MC grouping to the associated Pods field).

		// TODO (in the hash) overwrite the local subscriber with the MC one (assumes that the
		// payload always sends the subscriber data in its entirety (as opposed to sending only changed data)).
		
		// TODO dump the hash back to Pods.

	} else {
		chimpme_log('warn', "Unable to update. $mailchimp_subscriber_email does not exist in the database table $dbTableName.");
	}
	
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
		chimpme_log('info', "Received POST request: ", $payloadArray); // debug.

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
 * Helper method to retrieve the existing data on the specified subscriber
 * from the Pods plug-in.
 *
 * @returns Array An associative array.
 */
function getSubscriberDataFromPods($email) {

	// TODO check that the Pods plug-in exists here.

	global $podName;

	$data = array();

	$subscribers = pods($podName);
	$find_params = array(
		'limit' => -1,
		'where' => "email = '$email'"
		);
	$subscribers->find($find_params);

	if ($subscribers->total() == 1) {
		
		$subscribers->fetch();

		$value = "";
		foreach($subscribers->fields as $field_key=>$field_values) {

			// If this is a Relationship field...
			if ($field_values['pick_object'] != "") {

				 // .. Retrieve the value from the related pod.
				$field_values = $subscribers->field($field_key . "." .
					get_name_column_of_related_pod($field_key));

				// Pods may store the value of the Relationship field as an
				// array.
				// TODO Read up on why Pods does so, so that the hack below
				// can go away.
				if (is_array($field_values)) {

					foreach($field_values as $array_value) {
						$value = $array_value; // TODO fix this so it doesn't only just keep the last value. Maybe by making $field_value an array as well.
					}
				}

			} else {
				// If not an Relationship, simply get the value from this pod.
				$value = $subscribers->field($field_key);
			}

			$data[$field_key] = $value;
		}
	} elseif ($subscribers->total() > 1) {
		chimpme_log('error', "Cannot update $email in the Pod $pods_name. Expected subscribers to be unique, but 1 or more subscriber records have the same email.");
	} else {
		chimpme_log('error', "Cannot update. Cannot find $email in the pod $pods_name.");
	}

	return $data;
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
 *      
 * @param $severity
 * 		The severity of the message, limited to "error", warn", "notice" and "info" for now.
 * 
 * @param $objectToPrint
 * 		An optional argument. Prints the contents of the object.
 */
function chimpme_log($severity, $message, $objectToPrint = false) {
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

// Method: get_name_column_of_related_pod
// Accepts a name referring to an external pod, and returns the name
// of the column in the pod that acts the most human-readable identifier.
// ie. returns the name of the "name" column.
//
// This function also defined in the Pods page template also by me.
function get_name_column_of_related_pod($local_name_for_pod) {
	$column_name = 'name'; // General norm for tables created by the client.
	
	// Handle the conventions of specific tables.
	if($local_name_for_pod == 'author') {
		$column_name = 'display_name'; // With reference to the wp_users table.
	}

	return $column_name;
}

// ======================================
// POST request stubs below
// ======================================

/*
 * Helper method to simulate a POST request fired by the webhook
 * at MailChimp.
 */
function sendPostRequestStub() {
	global $chimpe_stubType;

	switch ($chimpe_stubType) {
		
		case 'unsubscribe':

		// exhaustively remove other stubs hooked into WordPress.
		remove_action('init', 'fireUpdateRequest');

		// only works upon entering an admin page. (ie. WP Dashboard).
		add_action('admin_footer', 'fireUnsubRequest');

		break;


		case 'update':

		// exhaustively remove other similar stubs hooked into WordPress.
		remove_action('init', 'fireUnsubRequest');

		// only works upon entering an admin page. (ie. WP Dashboard).
		add_action('admin_footer', 'fireUpdateRequest');
		
		break;

		default:
		remove_action('init', 'fireUpdateRequest');
		add_action('admin_footer', 'fireUnsubRequest');
	}
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
	echo "Sending 'unsubscribe' POST stub...\n"; //debug

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
 * Simulates a POST request from an MailChimp update webhook.
 */
function fireUpdateRequest() {

	$unsubscriberEmail = 'kna3@np.edu.sg';
	$requestTarget = plugins_url(basename(__FILE__), __FILE__); // Post back to this file.

	// Sample update callback:
	// 
	// type: profile
	// fired_at: 2013-07-30 03:27:31
	// data: {"id"=>"7bb817e5dc", "email"=>"user7@echoandhere.com", "email_type"=>"html",
	// 	"ip_opt"=>"137.132.119.222", "web_id"=>"24512937",
	// 	"merges"=>{"EMAIL"=>"user7@echoandhere.com", "FNAME"=>"User 7", "LNAME"=>"From Echo And Here", "INTERESTS"=>"Sociology", "GROUPINGS"=>{"0"=>{"id"=>"2745", "name"=>"Research interest", "groups"=>"Sociology"}, "1"=>{"id"=>"2749", "name"=>"Gender", "groups"=>"Female"}}}, "list_id"=>"b970dd90fa"}
	            

	echo "Sending 'update' POST stub...\n"; //debug

    $response = wp_remote_post( $requestTarget, array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking' => true,
		'headers' => array(),
		'body' => array( 'type' => 'profile',
			'fired_at' =>  date(DATE_ISO8601),
			'data' => json_encode(
				array(
				"id"=>"7bb817e5dc", "email"=>"$unsubscriberEmail",
				"email_type"=>"html", "ip_opt"=>"137.132.119.222", "web_id"=>"24512937",
				"merges"=> array("EMAIL"=>$unsubscriberEmail,
					"FNAME"=>"PH",
					"LNAME"=>"at Gmail",
					"INTERESTS"=>"Sociology",
					"GROUPINGS"=> array("0"=>array("id"=>"2745",
											"name"=>"Research interest",
											"groups"=>"Sociology"),
										"1"=>array("id"=>"2749",
											"name"=>"Gender",
											"groups"=>"Female"))),
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

?>