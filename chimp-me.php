<?php
/*
Plugin Name: ChimpMe
Description: A plug-in that syncs the unsubscribes from a MailChimp mailing list with a table in the WordPress database.
Version: 0.1
Author: Pheng Heong, Tan
Author URI: https://plus.google.com/106730175494661681756
*/

$dbName = "wordpress";
$dbTableName = "wp_users";
$dbEmailColumn = "user_email";

// TODO Stub in a POST request here. (simulate one from MailChimp).
// The trigger can be the 'admin_footer' hook from WordPress.
// As example, See the first code block at http://codex.wordpress.org/AJAX_in_Plugins

if (!empty($_POST)) {

	// check this is an unsubscribe

	// check the unsubscriber's email exists in our database.

	// unsubscribe from database
}



/*
Helper method to inspect incoming POST requests.
Deposits a CSV file in this plug-in directory.
*/
function logRequest() {

	$file = fopen("POST_request.csv", "w");

	foreach ($_POST as $key => $value) {
		$keyValuePair = array($key, $value);
		fputcsv($file, $keyValuePair);
	}

	fclose($file);
}

?>