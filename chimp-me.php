<?php
/*
Plugin Name: ChimpMe
Description: A WordPress plug-in that acts on Webhook events raised by a MailChimp mailing list. 
Version: 0.1
Author: Pheng Heong, Tan
Author URI: https://plus.google.com/106730175494661681756
*/

$dbName = "wordpress";
$dbTableName = "wp_users";
$dbEmailColumn = "user_email";

$debug = true;

if ($debug) {
	stubInPostRequest();
}

logRequest();

if (!empty($_POST)) {

	// check this is an unsubscribe

	// check the unsubscriber's email exists in our database.

	// unsubscribe from database
}

/*
 * Helper method to simulate a POST request fired by thewebhook
 * at MailChimp.
 */
function stubInPostRequest() {
	add_action('admin_footer', 'fireUnsubRequest');
}



/*
 * Simulates a POST request from an MailChimp unsubscribe webhook.
 */
function fireUnsubRequest() {

	$unsubscriberEmail = 'user1@echoandhere.com';
	$requestTarget = __FILE__; // Post back to this file.

	// Sample unsubscribe request:
	// 
	// type: unsubscribe
	// fired_at: 2013-07-02 09:58:09
    // data: {"action": "unsub", "reason": "manual", "id": "8249032551", 
    // "email": "phtan90@gmail.com", "email_type": "html", 
    // "ip_opt": "137.132.202.66", "web_id": "17701457", 
    // "campaign_id": "6fb66e1caf", "merges": {"EMAIL": "phtan90@gmail.com", 
    // "FNAME": "PH", "LNAME": "at Gmail"}, "list_id": "b970dd90fa"}

    echo "Beginning Javascript POST request..."; // debug
?>

<script type="text/javascript">

console.log("Starting JQuery..."); // debug.

jQuery(document).ready(function($) {

	console.log("Hi " + <?php echo "\"" . $requestTarget . "\""; ?>); // debug.
 
	var request = {
		type: 'unsubscribe',
		fired_at: $.now(),
		data: {action: "unsub", reason: "manual", id: "8249032551", 
 			email: <?php echo "\"" . $unsubscriberEmail . "\"" ?>, email_type: "html", 
     		ip_opt: "137.132.202.66", web_id: "17701457", 
     		campaign_id: "6fb66e1caf",
     		merges: {EMAIL: <?php echo "\"" . $unsubscriberEmail . "\"" ?>, FNAME: "PH", LNAME: "at Gmail"},
     		list_id: "b970dd90fa"}
	};

	$.post(<?php echo $requestTarget; ?>, request, function(reply) {
		alert("Sent POST request and received the reply: " + reply);
	});
});
</script>

<?php
echo "Finished running Javascript."; // debug
}

/*
 * Helper method to inspect incoming POST requests.
 * Deposits a CSV file in this plug-in directory.
*/
function logRequest() {

	echo "Logging..."; // debug

	$file = fopen("POST_request_log.csv", "w");

	foreach ($_POST as $key => $value) {
		$keyValuePair = array($key, $value);
		fputcsv($file, $keyValuePair);
	}

	fclose($file);
}

?>