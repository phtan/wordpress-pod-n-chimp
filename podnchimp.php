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
require_once('/helpers/PCDictionary.php');
require_once('/lib/MCAPI.class.php'); // debug. testing alternative to the wrapper below.
// require_once('/lib/mailchimp-api-php/src/Mailchimp.php');

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
	 * 	The sub-array names are given by Scott himself in a
	 * 	{@link https://github.com/pods-framework/pods/issues/597 comment on an issue}.
	 * 	
	 * @param  boolean $is_new_item
	 * 
	 * @return void
	 */
	public function updateToMailChimp($arrayFromPods, $is_new_item) {
		
		// value of each field is in $arrayFromPods['fields']['someField']['value']; // debug.
		// 
		pnc_log('info', "Post-save hook fired. Checking if this subscriber is new to Pods:", $is_new_item); // debug

		global $podsDataKey;
		global $pc_mailChimpAPIKey;

		if (empty($arrayFromPods)) {
			pnc_log('error', __CLASS__ . "> Cannot update. Expected data from Pods, but received no data.");
			return;
		}

		if (self::isUnsubscribe($arrayFromPods)) {
			
			pnc_log('info', __METHOD__ . " > Detected unsubscribe from Pods."); // debug

			// TODO
			// prepareUnsubParams($arrayFromPods);
			// unsubWithMCAPI($params);

		} else {
			
			$itemDetails = $arrayFromPods[$podsDataKey];
			$data = self::prepareUpdateParameters($itemDetails, $is_new_item);

			pnc_log('notice', "Updating with MailChimp...");
			pnc_log('info', __METHOD__ . " > Sending these params to MC:", $data); // debug.

			// $mcapi = new Mailchimp($pc_mailChimpAPIKey, array('debug'=>true));
			// $mcapi->lists->subscribe(
			// 	$data['id'],
			// 	$data['email'],
			// 	$data['merge_vars'],
			// 	$data['email_type'],
			// 	$data['double_optin'],
			// 	$data['update_existing'],
			// 	$data['replace_interests'],
			// 	$data['send_welcome']
			// 	);
			
			$mailchimp = new MCAPI($pc_mailChimpAPIKey);
			$mailchimp->listSubscribe(
				$data['id'],
				$data['email']['email'],
				$data['merge_vars'],
				$data['email_type'],
				$data['double_optin'],
				$data['update_existing'],
				$data['replace_interests'],
				$data['send_welcome']
				);

			if ($mailchimp->errorCode) {
				pnc_log('error', "Unable to update.\tCode=" . $mailchimp->errorCode . "\tMsg=". $mailchimp->errorMessage);
			} else {
			    pnc_log('notice', "Updated.");
			}
		}
	}


	public function deleteFromMailChimp($params, $pods) {
		// The contents of $params and $pods are logged in /logs/data_from_pods_afterSave_hook.txt
		
		// The Pod item being deleted can be found as $params['id'] from the pod
		// with name $params['pod'] // debug.
		
	}

	/** 
	 * Checks if the save to some Pods item constitutes an unsubscribe.
	 * Private method.
	 *
	 * @param array $hookData
	 * 	An array passed to the callback function by the Pods post-save hook.
	 *
	 * @return bool
	 * 	True if there has been an unsubscribe.
	 */
	private function isUnsubscribe($hookData) {

		global $dbUnsubscribeColumn, $dbUnsubscribeBit;

		pnc_log('info', __METHOD__ . " > Checking for unsubscribe..."); // debug

		return ($hookData['fields'][$dbUnsubscribeColumn]['value'] === $dbUnsubscribeBit);
	}

	/**
	 * Creates parameters suitable for use by the MailChimp API.
	 * Private method.
	 * 
	 * @param  array $fields
	 * 	Details for the fields of a Pod item. This should have been provided by
	 * 	Pods.
	 *
	 * @param bool $isNewSubscriber
	 * 	Determines if we add a new subscriber to MailChimp or update an existing one.
	 * 	
	 * @return array
	 * 	Meant for use by the MCAPI,
	 * 	available {@link https://bitbucket.org/mailchimp/mailchimp-api-php here}.
	 */
	private function prepareUpdateParameters($fields, $isNewSubscriber) {

		if (empty($fields)) {
			pnc_log('warn', __CLASS__ . " > Cannot update MailChimp. No data received from Pods.");
			return;
		}

		$interestGroupings = PCDictionary::getMailChimpGroupings($fields);
		$overwriteExistingGroupingsAtMailChimp = true;
		$shouldUpdateExistingSubscriber = !($isNewSubscriber == 1);

		global $pc_mailChimpListID;

		$params = array(

			'id' => $pc_mailChimpListID,

			'email' => array(
				'email' => $fields['email']['value'], // TODO find another pointer that can be a handle on the "old" email, say, after an update the email address. Changing this will also affect the 'new-email' key in the merge_vars sub-array.
				'euid' => '', // empty
				'leid' => '', // empty
				),
			
			'merge_vars' => array(
				'new-email' => $fields['email']['value'],
				'fname' => $fields['first_name']['value'],
				'lname' => $fields['last_name']['value'],
				'groupings' => $interestGroupings
				),

			'email_type' => $pc_defaultEmailPreference,
			'double_optin' => $pc_doubleOptIn,
			'update_existing' => !$isNewSubscriber,
			'replace_interests' => $overwriteMailChimpsExistingGroupings,
			'send_welcome' => $pc_sendWelcome
			);

		return $params;
	}

}

// Register with WordPress hooks, the necessary functions having been defined above.
register_activation_hook(__FILE__, array('PodNChimp', 'setUp'));
register_uninstall_hook(__FILE__, array('PodNChimp', 'tearDown'));

add_action('plugins_loaded', array('PodNChimp', 'getInstance')); // get an instance so that we can register with Pods' hooks, if not already done.

?>