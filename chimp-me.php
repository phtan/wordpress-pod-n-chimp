<?php
/*
Plugin Name: ChimpMe
Description: A WordPress plug-in that acts on Webhook events raised by a MailChimp mailing list. 
Version: 0.1
Author: Pheng Heong, Tan
Author URI: https://plus.google.com/106730175494661681756
*/

require_once(dirname(dirname(dirname(dirname( __FILE__ )))) . '/wp-load.php' ); // TODO find a better way to locate wp-load.php as it might not always be in the WP root folder.

// DB parameters
$dbTableName = "wp_users";
$dbEmailColumn = "user_email";

// MailChimp constants
$emailKey = "email"; // key as in array key.

// Constants for this plug-in.
$chimpme_debug = true;

if (!empty($_POST)) { // TODO check instead for a key appended to the URL given to the mailchimp API.

	logRequest();

	if (isset($_POST['type'])) {

		switch($_POST['type']) {

			case 'unsubscribe':
				chimpme_unsubscribe($_POST['data']);
				break;

			default:
				wp_die("ChimpMe: Unknown action requested by webhook: "
					. $_POST['type']);
		}
	
	} else {
		// The 'type' key has no value.
		// MailChimp's webhook might have changed.
		// Do nothing for now.
	}

} else { // There hasn't been a POST request to this file.

	if ($chimpme_debug) {
		sendPostRequestStub();
	}
}

/*
 * This method removes the unsubscriber from an user-maintained
 * database.
 * @param data
 *	An array of values from MailChimp. // TODO confirm it is an array type.
 */
function chimpme_unsubscribe($data) {

	global $wpdb;
	global $dbTableName, $dbEmailColumn;
	// TODO use prepare statements in wpdb calls below.
	
	$unsubscriber = chimpme_getEmail($data);

	$query = "SELECT * FROM $dbTableName
		WHERE $dbEmailColumn = '$unsubscriber'";
	$subscriber = $wpdb->get_row($query);

	if ($subscriber != null) {

		echo "Unsubscribing $unsubscriber..." . "\n"; // debug.

		$deleteQuery = "DELETE FROM $dbTableName
			WHERE $dbEmailColumn = '$unsubscriber'";
		echo "Query string created."; // debug
		$deleteResult = $wpdb->query($deleteQuery);
		echo "Query sent."; // debug.

		if ($deleteResult === false) { // identicality check as both 0 and false might be returned.
			wp_die("Database error. Unable to delete $unsubscriber.");
		}

		echo "Unsubscribed." . "\n"; // debug		
	} else {
		// TODO handle non-existent non-subscriber more gracefully.
		wp_die("Unable to unsubscribe. $unsubscriber does not exist.");
	}
}

/*
 * Helper method to retrieve the email from the webhook payload.
 */
function chimpme_getEmail($payload) {

	$result = "";
	global $chimpme_debug;

	if ($chimpme_debug) {
		// Payload will be in JSON format, as coded in the
		// fireUnsubRequest method.

		$json = stripslashes($payload); // Undo any quotes from PHP or WP in the POST stub.

		echo "Received stub request: " . $json . "\n"; // debug.

		$payloadArray = json_decode($json, true);

		$result = $payloadArray['email'];

	} else {
		// TODO replace the line below.
		echo "chimpme_getEmail: Cannot get email - Not implemented yet.";
	}

	echo "chimpme_getEmail: Got the email " . $result . "\n"; // debug.
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

    echo "sent.\n"; // debug.

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

	$file = fopen("POST_request_log.csv", "w");

	foreach ($_POST as $key => $value) {
		$keyValuePair = array($key, $value);
		fputcsv($file, $keyValuePair);
	}

	fclose($file);
}

?>