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
	 * Takes data from the Pods post-save hook and sends it to MailChimp.
	 * This function must return the array of Pods data if it's attached to
	 * to the Pods pre-save hook instead.
	 * 
	 * @param  arrayFromPods $arrayFromPods
	 * 	A single array of nested arrays 'fields', 'params', 'pod', and 'fields_active'.
	 * 	The parameters are given by Scott himself in an
	 * 	{@link https://github.com/pods-framework/pods/issues/597 issue comment}.
	 * 	
	 * @param  boolean $is_new_item
	 * 
	 * @return void
	 */
	public function updateToMailChimp($arrayFromPods, $is_new_item) {
		
		// value of each field is in $arrayFromPods['fields']['someField']['value']; // debug.
		
		global $dbUnsubscribeColumn, $dbUnsubscribeBit;

		if (empty($arrayFromPods)) {
			pnc_log('error', "PodNChimp> Cannot update. Expected data from Pods, but received no data.");
			return;
		}

		$includesUnsubscribe = false;

		

		// TODO
		// checkForUnsubscribe();
		// $dataForChimp = convertToChimp($arrayFromPods);
		// replaceChimpDataOtherwiseUnsubscribe($dataForChimp, $isUnsubscribe);
	}


	public function deleteFromMailChimp($params, $pods) {
		// The contents of $params and $pods are logged in /logs/data_from_pods_afterSave_hook.txt
		
		// The Pod item being deleted can be found as $params['id'] from the pod
		// with name $params['pod'] // debug.
		
	}

}

// Register with WordPress hooks, the necessary functions having been defined above.
register_activation_hook(__FILE__, array('PodNChimp', 'setUp'));
register_uninstall_hook(__FILE__, array('PodNChimp', 'tearDown'));

add_action('plugins_loaded', array('PodNChimp', 'getInstance')); // get an instance so that we can register with Pods' hooks, if not already done.

?>