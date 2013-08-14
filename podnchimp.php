<?php

/*
Plugin Name: Pod 'n Chimp
Description: Two-way sync between Pods Framework and MailChimp.
Version: 0.1
Author: Pheng Heong Tan
Author URI: https://plus.google.com/106730175494661681756
*/

// No point continuing if WordPress hasn't been loaded.
// (eg. through direct access to this PHP file.)
if (!defined('ABSPATH')) {
	exit();
}

require_once('config.php');
require_once('/helpers/PCLogger.php');

class PodNChimp {

	private static $instance;

	private function __construct() {
		
		global $podName;

		$postSaveFilter = 'pods_api_post_save_pod_item_' . $podName;
		$saveFunctionArguments = 2;
		$postDeleteFilter = 'pods_api_post_delete_pod_item_' . $podName;
		$deleteFunctionArguments = 2;
		$filterFunctionPriority = 10; // Lower numbers mean earlier execution by WordPress.

		add_filter($postSaveFilter, array($this, 'updateToMailChimp'), $filterFunctionPriority, $saveFunctionArguments);
		add_filter($postDeleteFilter, array($this, 'deleteFromMailChimp'), $filterFunctionPriority, $deleteFunctionArguments);

		// TODO add filters for pods other than contacts. Eg. countries_of_interest
	}

	// Implements singleton pattern in case this class ends up being used in
	// template files/plug-ins.
	public static function getInstance() {

		if (self::$instance == null) {
			self::$instance = new self;
		}

		pnc_log('info', "Serving an instance of PodNChimp...");

		return self::$instance;
	}

	public static function setUp() {
		// TODO code for any wpdb database prep goes here. eg. store preferences, 
		// or store IDs for existing MailChimp lists.
	}

	public static function tearDown() {
		// TODO remove any wpdb traces.
	}

	/**
	 * Takes data from the Pods post-save hook and sends
	 * it to MailChimp.
	 * 
	 * @param  arrayFromPods $arrayFromPods
	 * 	A single array of nested arrays 'fields', 'params', 'pod', and 'fields_active'.
	 * 	
	 * @param  boolean $is_new_item
	 * 
	 * @return void
	 */
	public function updateToMailChimp($arrayFromPods, $is_new_item) {
		// TODO
		// checkForUnsubscribe();
		// replaceChimpDataIfNotUnsubscribe($isUnsubscribe);
		
		// The log 2 lines below has been commented out as it is too verbose.
		// For reference, see log_2013-08-13.txt in /logs
		// pnc_log('info', '$arrayFromPods passed to ' . __METHOD__ . ' is:', $arrayFromPods); // debug.
	}


	public function deleteFromMailChimp($params, $pods) {
		// TODO inspect $params and $pods as no online docs on them.
		pnc_log('info', '$params passed to ' . __METHOD__ . ' is:', $params);
		pnc_log('info', '$pods passed to ' . __METHOD__ . ' is:', $pods);
	}

}

// Register with WordPress hooks, the necessary functions having been defined above.
register_activation_hook(__FILE__, array('PodNChimp', 'setUp'));
register_uninstall_hook(__FILE__, array('PodNChimp', 'tearDown'));

add_action('plugins_loaded', array('PodNChimp', 'getInstance')); // get an instance so that we can register with Pods' hooks, if not already done.

?>