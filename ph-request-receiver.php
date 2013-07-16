<?php
/*
Plugin Name: PH's POST Request Receiver
Description: A plug-in that receives a POST request made to WordPress through the XML-RPC interface.
Version: 0.1
Author: Pheng Heong, Tan
Author URI: https://plus.google.com/106730175494661681756
*/

// With reference to the solution at
// http://stackoverflow.com/a/7144611
// and WP's own guide at http://codex.wordpress.org/XML-RPC_Extending

$receive_method_name = "ph.receivePostRequest";

function ph_addActions(array $methods) {

	if (!isset($methods[$receive_method_name])) {
		$methods[$receive_method_name] = 'ph_receivePostRequest';
	}
	// TODO Wordpress is not picking up on the method pointed to by
	// $receive_method_name.
	// 
	// Error message below is shown when heading to
	// http://localhost/test-site/blog/wp-content/plugins/yd-webhook-to-xml-rpc/webhook.php?method=ph.receivePostRequest
	// 
    // "faultCode -32601 faultString server error. requested method
	// ph.receivePostRequest does not exist.""
	//
	// Control test for the WP XML-RPC server (and the webhook plugin):
	// http://localhost/test-site/blog/wp-content/plugins/yd-webhook-to-xml-rpc/webhook.php?method=demo.sayHello
	// "Hello!" should be printed.

	return $methods;
}

// This method receives a POST request and processes it accordingly.
// #FIXME make method description above more specific.
function ph_receivePostRequest($args) {
	echo $args;
	echo "<br />";
	var_dump($_POST); // debug
}

// Register with WP
add_filter('xmlrpc_methods', 'ph_addActions');

?>