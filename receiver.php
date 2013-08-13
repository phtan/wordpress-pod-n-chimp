<?php

/*
This file implements the synchronisation from MailChimp to Pods.
It depends on MailChimp webhooks and the Pods API.

Version: 0.1
Author: Pheng Heong, Tan
Author URI: https://plus.google.com/106730175494661681756
*/

// ======== Ensure the below values are specified in the webhook callback URL passed to MailChimp =============
// (The URL should look like http://send.callback.here/this-plugin.php?authenticateKey=authenticateValue)
$authenticateKey = "key";
$authenticateValue = "helloFromMailChimp";
// ======================================== End authentication section ========================================

require_once(dirname(dirname(dirname(dirname( __FILE__ )))) . '/wp-load.php' ); // TODO find a better way to locate wp-load.php as it might not always be in the WP root folder.
require_once('config.php');
require_once('/helpers/PCLogger.php');
require_once('/helpers/PCDictionary.php');

// Constants for this file.

$upemailBuffer = 1000000; // Number of micro-seconds to wait for 'upemail' handler to finish.


// ==============================
// Main logic of this plug-in.
// ==============================

// TODO Un-comment the next 2 lines in production.
// if (isset($_POST[$authenticateKey]) &&
// 		$_POST[$authenticateKey] == $authenticateValue) { // This is a callback from MailChimp.

if (!empty($_POST)) {

	if ($chimpme_debug) {
		logRequest();
	}

	if (isset($_POST[$webhookEventKey])) {

		switch($_POST[$webhookEventKey]) {

			case $unsubscribeEvent:
				chimpme_unsubscribe($_POST[$payloadKey]);
				break;

			case $updateEvent:
				chimpme_update($_POST[$payloadKey]);
				break;

			case $emailChangeEvent:
				chimpme_changeEmail($_POST[$payloadKey]);
				break;

			default:
				pnc_log('error', "ChimpMe: Unknown action requested by webhook: "
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
	global $emailKey;

	$dbUnsubscribeColumn = "newsletter";
	$dbUnsubscribeBit = 0;
	// TODO use prepare statements in wpdb calls below.
	
	$unsubscriber = chimpme_getEmail($data);
    pnc_log('notice', "Unsubscribing $unsubscriber...");

	$query = "SELECT * FROM $dbTableName
		WHERE $dbEmailColumn = '$unsubscriber'";
	$subscriber = $wpdb->get_row($query);

	if ($subscriber != null) { // TODO move this check (and the SQL above) into the future DAO class' unsubscribe method.

		// TODO check that the unsubscriber hasn't already unsubscribed before
		// following through with the below.

		$unsubscribeQuery = "UPDATE $dbTableName
			SET $dbUnsubscribeColumn = '$dbUnsubscribeBit'
			WHERE $dbEmailColumn = '$unsubscriber'";

		$unsubscribeResult = $wpdb->query($unsubscribeQuery);
		
		if ($unsubscribeResult === false) { // using identicality check as both 0 and false might be returned.
			pnc_log('error', "Database error. Unable to delete $unsubscriber.");
		} else {
			pnc_log('notice', "$unsubscriber has been unsubscribed.");
		}

	} else {
		pnc_log('warn', "Unable to unsubscribe. $unsubscriber does not exist.");
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
	global $emailKey;

	global $upemailBuffer;
	usleep($upemailBuffer); // TODO replace this with a queue system that checks that the upemail has indeed finished.

	$mailchimp_subscriber_email = chimpme_getEmail($data, $emailKey);
	pnc_log('notice', "Updating $mailchimp_subscriber_email...");

	$query = "SELECT * FROM $dbTableName
		WHERE $dbEmailColumn = '$mailchimp_subscriber_email'";
	$local_subscriber = $wpdb->get_row($query);

	if ($local_subscriber != null) { // TODO move this check (and the SQL above) into the future DAO class' update method.

		$localData = getSubscriberDataFromPods($mailchimp_subscriber_email);
		pnc_log('info', "Found the subscriber: ", $localData); // debug

		try {
			$remoteFields = PCDictionary::convertToPodsFields($data);
		} catch (Exception $e) {
			pnc_log('error', $e->getMessage());
			die();
		}

		// store last element of local data for reference later.
		end($localData);
		$lastGroupName = key($localData);
		reset($localData);

		// Overwrite the local info on the subscriber with the MC one.
		// Assumes that the payload always sends the subscriber data in its
		// entirety (as opposed to sending only changed data)).
		foreach ($remoteFields as $remoteName => $remoteValue) {

			foreach ($localData as $localName => $localValue) {

				if ($localName == $remoteName) {
					$localData[$localName] = $remoteValue;

					// TODO check that $remoteValue is a valid PICK column value.
					// (ie. exists in the foreign table.)
					// Because if it isn't (eg. countries_of_interest = "random_place"
					// instead of "India"), then Pods will set the PICK column
					// value to NULL instead of the invalid value.

					// TODO log a warning that the value will be set to NULL
					// if the check in the above TODO fails.

					pnc_log('notice', "Updating '$localName' from '$localValue' to '$remoteValue'...");
					break;
				} elseif ($localName == $lastGroupName && $localName != $remoteName) {
					
					// we've scanned through all the local data. The remote group is new.

					// TODO Register the remote group as a new Pods field? To ask Alfred.
					pnc_log('notice', "Found a new field '$remoteName' in MailChimp data with the value '$remoteValue'. Discarding for now...");
				}
			}
		}

		$updateSuccess = saveToPods($localData);

		if ($updateSuccess) {
			pnc_log('notice', "Updated $mailchimp_subscriber_email.");
			pnc_log('info', "Subscriber is now:",
				getSubscriberDataFromPods($mailchimp_subscriber_email));
		} else {
			pnc_log('error', "Unable to  update. Cannot save to Pods.");
		}

	} else {
		pnc_log('warn', "Unable to update. $mailchimp_subscriber_email does not exist in the database table $dbTableName.");
	}	
}

/*
 * This method changes the email of an unsubscriber in an user-maintained
 * database.
 * @param data
 * 	An array of values that MailChimp sends in its webhook callback.
 */
function chimpme_changeEmail($data) {

	global $wpdb;
	global $dbTableName, $dbEmailColumn;
	global $oldEmailKey, $newEmailKey;

	$subscriber = chimpme_getEmail($data, $oldEmailKey);
	$newEmail = chimpme_getEmail($data, $newEmailKey);
    pnc_log('notice', "Updating email address for $subscriber...");

	$query = "SELECT * FROM $dbTableName
		WHERE $dbEmailColumn = '$subscriber'";
	$subscriberInDB = $wpdb->get_row($query);

	if ($subscriberInDB != null) {

		$changeEmailQuery = "UPDATE $dbTableName
			SET $dbEmailColumn = '$newEmail'
			WHERE $dbEmailColumn = '$subscriber'";

		$changeEmailResult = $wpdb->query($changeEmailQuery);
		
		if ($changeEmailResult === false) { // using identicality check as both 0 and false might be returned.
			pnc_log('error', "Error in querying the database. Cannot set $subscriber to the new email $newEmail.");
		} else {
			pnc_log('notice', "Updated. Set old email \"$subscriber\" to new email \"$newEmail\".");
		}

	} else {
		pnc_log('warn', "Cannot update $subscriber. (to the email $newEmail). S/he does not exist in the database.");
	}
}

/*
 * Helper method to retrieve the email from the POST data sent by the webhook.
 *
 * @param $payload
 * 	An array containing data related to the MailChimp event. (eg. Unsubscribe).
 *
 * @param $emailKey
 * 	The key that points to the email address in $payload.
 */
function chimpme_getEmail($payload, $emailKey) {

	$result = "";

	$result = $payload[$emailKey];	
	
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

		$value = ""; // initialise.

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
				
				$value = ""; // reset from any assignments from a previous
							// loop iteration that entered the if statement below.

				if (is_array($field_values)) {

					foreach($field_values as $key => $array_value) {
						// pnc_log('info', "Read in from Pods: {$field_key}[$key] = $array_value"); // debug
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
		pnc_log('error', "Cannot update $email in the Pod $pods_name. Expected subscribers to be unique, but 1 or more subscriber records have the same email.");
	} else {
		pnc_log('error', "Cannot update. Cannot find $email in the pod $pods_name.");
	}

	return $data;
}

/*
 * Helper method to save an array of fields to Pods' storage.
 * 
 * @param array
 *	An associative array of data to overwrite to Pods.
 *
 * @return boolean
 *	True on success.
 */
function saveToPods($data) {
	
	global $podName;
	$success = false;

	// TODO defensive code: check that the Pods plug-in exists here.

	if (isset($data['email'])) {

		$localSubscribers = pods($podName); // TODO This is a duplicate of the call in getSubscriberDataFromPods. To avoid dups, allow the Pod object persist in the future Pods model class.
		$find_params = array(
			'limit' => -1,
			'where' => "email = '" . $data['email'] . "'"
			);
		$localSubscribers->find($find_params);

		if ($localSubscribers->total() == 1) {

			// ALready found within pods. Retrieve its id so that we have a handle
			// to call update methods on.
			$localSubscribers->fetch();
			$id = $localSubscribers->field('id'); // TODO refactor magic string for the Pods 'id' key.

			$subscriber = pods($podName, $id);
			$podsAPI = pods_api($podName);

			// debug.
			pnc_log('info', "Beginning to save to the Pods item " . $subscriber->field('email') . " that has id " . $subscriber->field('id') . "..."); // debug
			pnc_log('info', "Saving this data into Pods:", $data);
			// end debug.

			// Pods->save and PodsAPI->save_pod_item both don't update relationship
			// columns, hence the workaround below. As of Pods v2.3.6.
			$success = ($subscriber->delete() && $podsAPI->import($data)); // depends on PHP's short-circuit behaviour of the && operator.

		} elseif ($subscribers->total() > 1) {
			pnc_log('error', "Cannot update " . $data['email'] . " in the Pod $pods_name. Expected subscribers to be unique, but 1 or more subscriber records have the same email.");
		} else { 
			pnc_log('error', "Cannot update. Cannot find " . $data['email'] . " in the pod $pods_name.");
		}

	} else {
		pnc_log('error', "Cannot save to Pods. There is no email to identify the subscriber with.");
	}

	return $success;
}


/*
 * Helper method to inspect incoming POST requests.
 * Deposits a .txt file in the directory for request logs.
 */
function logRequest() {

	global $requestLogger;

	$requestLogger->logInfo("Received POST request:", $_POST); 

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
	global $chimpme_stubType;

	switch ($chimpme_stubType) {
		
		case 'unsubscribe':

		// exhaustively remove other stubs hooked into WordPress.
		remove_action('init', 'fireUpdateRequest');
		remove_action('init', 'fireUpemailRequest');

		// only works upon entering an admin page. (ie. WP Dashboard).
		add_action('admin_footer', 'fireUnsubRequest');

		break;


		case 'update':

		// exhaustively remove other similar stubs hooked into WordPress.
		remove_action('init', 'fireUnsubRequest');
		remove_action('init', 'fireUpemailRequest');

		// only works upon entering an admin page. (ie. WP Dashboard).
		add_action('admin_footer', 'fireUpdateRequest');
		
		break;

		case 'upemail':

		remove_action('init', 'fireUpdateRequest');
		remove_action('init', 'fireUnsubRequest');

		add_action('admin_footer', 'fireUpemailRequest');

		break;

		default:
		remove_action('init', 'fireUpdateRequest');
		remove_action('init', 'fireUpemailRequest');
		add_action('admin_footer', 'fireUnsubRequest');
	}
}

/*
 * Simulates a POST request from an MailChimp unsubscribe webhook.
 */
function fireUnsubRequest() {

	$unsubscriberEmail = 'kna3@np.edu.sg';
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
			'data' => array(
				"action"=>"unsub", "reason"=>"manual",
				"id"=>"8249032551", "email"=>$unsubscriberEmail,
				"email_type"=>"html", "ip_opt"=>"137.132.202.66",
				"web_id"=>"17701457", "campaign_id"=>"6fb66e1caf",
				"merges"=> array("EMAIL"=>$unsubscriberEmail,
					"FNAME"=>"PH", "LNAME"=>"at Gmail"),
				"list_id"=>"b970dd90fa")),
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

	$unsubscriberEmail = 'user@tus.edu.sg';
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
			'data' => array(
				"id"=>"7bb817e5dc", "email"=>"$unsubscriberEmail",
				"email_type"=>"html", "ip_opt"=>"137.132.119.222", "web_id"=>"24512937",
				"merges"=> array("EMAIL"=>$unsubscriberEmail,
					"FNAME"=>"PH",
					"LNAME"=>"at Gmail",
					"INTERESTS"=>"Sociology",
					"GROUPINGS"=> array("0"=>array("id"=>"2745",
											"name"=>"countries_of_interest",
											"groups"=>"Singapore"),
										"1"=>array("id"=>"2749",
											"name"=>"Gender",
											"groups"=>"Female"))),
				"list_id"=>"b970dd90fa")),
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
 * Simulates a POST request from an MailChimp upemail webhook.
 */
function fireUpemailRequest() {

	$oldEmail = 'THISISNEW@tus.edu.sg';
	$newEmail = 'user@tus.edu.sg';
	$requestTarget = plugins_url(basename(__FILE__), __FILE__); // Post back to this file.

	// Sample upemail callback:
	// 
	// type: upemail
	// fired_at: 2013-07-30 03:27:31
	// data: {"new_id"=>"00e38dac18", "new_email"=>"user@tus.edu.sg",
	// 	"old_email"=>"userWHATSTHEWEBHOOKTYPE@tus.edu.sg", "list_id"=>"b970dd90fa"}      

	echo "Sending 'upemail' POST stub...\n"; //debug

    $response = wp_remote_post( $requestTarget, array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking' => true,
		'headers' => array(),
		'body' => array( 'type' => 'upemail',
			'fired_at' =>  date(DATE_ISO8601),
			'data' => array(
				"new_id"=>"00e38dac18",
				"new_email"=>$newEmail,
				"old_email"=>$oldEmail,
				"list_id"=>"b970dd90fa")),
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