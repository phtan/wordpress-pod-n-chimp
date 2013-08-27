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
// require_once('config_develop.php'); // use development environment presets.
require_once('/helpers/PCLogger.php');
require_once('/helpers/PCDictionary.php');
require_once('/helpers/PCRequestQueue.php');
require_once('/lib/MCAPI.class.php');

class PodNChimp {

	private static $instance;

	private function __construct() {
		
		global $podName;

		$postSaveFilter = 'pods_api_post_save_pod_item_' . $podName;
		$saveFunctionArguments = 2;
		$preDeleteFilter = 'pods_api_pre_delete_pod_item_' . $podName;
		$deleteFunctionArguments = 2;
		$filterFunctionPriority = 10; // Lower numbers mean earlier execution by WordPress.

		add_filter($postSaveFilter, array($this, 'updateToMailChimp'), $filterFunctionPriority, $saveFunctionArguments);
		add_filter($preDeleteFilter, array($this, 'deleteFromMailChimp'), $filterFunctionPriority, $deleteFunctionArguments);

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
		
		// debug
		$isNew = ($is_new_item == 1);
		pnc_log('info', "Post-save hook fired. Checking if this subscriber is new to Pods: $isNew");

		// end debug.

		global $podsDataKey;
		global $pc_mailChimpAPIKey, $pc_mailChimpListID;

		// Defensive code to ensure we have the data we want.
		if (empty($arrayFromPods)) {
			pnc_log('error', __CLASS__ . "> Cannot update. Expected data from Pods, but received no data.");
			return;
		}

		$subscriber = "";
		try {
			$subscriber = self::getEmail($arrayFromPods);
		} catch (Exception $e) {
			pnc_log('error', $e->getMessage());
			die();
		}

		$queue = PCRequestQueue::getInstance();
		if ($queue->isInQueue($subscriber)) {
			pnc_log('info', __METHOD__ . "> Not updating MailChimp as this Pods save was triggered by MailChimp itself.");
			return;
		}

		// End defensive code.

		// First, update MailChimp.
		$isOnMailChimp = self::existsOnMailChimp($subscriber, $pc_mailChimpListID);
		$createNewSubscriber = !$isOnMailChimp;
		$itemDetails = $arrayFromPods[$podsDataKey];			

		$data = self::prepareUpdateParameters($itemDetails, $createNewSubscriber);

		pnc_log('notice', "Updating with MailChimp...");
		pnc_log('info', __METHOD__ . " > Sending these params to MC:", $data); // debug.
		
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
		
		// Then, check if there is a change to this subscriber's subscription status.
		if (!self::wantsNewsletter($arrayFromPods)) {	

			if ($isOnMailChimp) {
		
				// S/he doesn't want a newsletter, yet exists as a subscriber on MailChimp.
				// We should unsubscribe him/her from MailChimp.
				
				pnc_log('info', __METHOD__ . " > Detected an unsubscribe sent from Pods."); // debug

				self::unsubscribeFromMailChimp($subscriber, $pc_mailChimpListID);

			} else {

			// Pods tells us this subscriber doesn't want newsletters.
			// Conveniently, this subscriber also doesn't exist on MailChimp yet.
			
			// Do nothing.

			}
		}
	}

	/**
	 * Takes data from the Pods pre-delete hook and then instructs MailChimp
	 * accordingly.
	 * This function should not be attached to the *post*-delete hook
	 * as the email of the Pod item cannot be retrieved by then.
	 * 
	 * @param  stdClass $params
	 * 	A PHP object containing information on the Pod item being deleted.
	 *  As of Pods 2.3.6, its properties are:
	 *  - 'pod': The name of the Pod that the item is deleted from.
	 *  - 'id': The ID of the item within the Pod.
	 *  - 'pod_id': (Not verified)
	 * 	
	 * @param  array $pods
	 *  Contains information on the Pod that holds the item to be deleted.
	 *  As of Pods 2.3.6, a few keys in the array are 'meta_field_id', 'field_slug',
	 *  'object_hierarchical' and 'join'.
	 * 
	 * @return void
	 */
	public function deleteFromMailChimp($params, $pods) {

		global $pc_mailChimpListID;

		// Use the Pods API to find our way back to the subsriber's email, using
		// info provided by the Pods hook.
		$nameOfPod = $params->pod;
		$subscriberPodsID = $params->id;

		$subscriber = pods($nameOfPod, $subscriberPodsID);
		$subscriberEmail = $subscriber->field('email');

		$isDelete = true;

		pnc_log('info', "Pre-delete hook fired. Preparing to delete $subscriberEmail from MailChimp..."); // debug.
		self::unsubscribe($subscriberEmail, $pc_mailChimpListID, $isDelete);

		// TODO refactor the above - abstract out the API-specific magic object properties
		// into helper functions. Eg. getName(), getEmail().
	}

	/**
	 * Unsubscribes an user from a MailChimp mailing list.
	 * 
	 * @param  string $email
	 *  Email address to be unsubscribed.
	 *  
	 * @param  string $listID
	 *  The ID of the list as assigned by MailChimp.
	 *  
	 * @return void
	 * 
	 */
	public function unsubscribeFromMailChimp($email, $listID) {
		$isDelete = false;
		self::unsubscribe($email, $listID, $isDelete);
	}

	/** 
	 * Checks if the save to some Pods item constitutes an intention to subscribe
	 * to a newsletter.
	 * Private method.
	 *
	 * @param array $hookData
	 * 	An array passed to the callback function by the Pods post-save hook.
	 *
	 * @return bool
	 * 	True if there has been an unsubscribe.
	 */
	private function wantsNewsletter($hookData) {

		global $dbUnsubscribeColumn, $dbUnsubscribeBit;

		$unsubscribe = ($hookData['fields'][$dbUnsubscribeColumn]['value'] == $dbUnsubscribeBit);
		
		return !$unsubscribe;
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

		global $pc_mailChimpListID, $pc_defaultEmailPreference, $pc_doubleOptIn, $pc_sendWelcome;
		
		if (empty($fields)) {
			pnc_log('warn', __CLASS__ . " > Cannot update MailChimp. No data received from Pods.");
			return;
		}

		$interestGroupings = PCDictionary::getMailChimpGroupings($fields);
		$overwriteExistingGroupingsAtMailChimp = true;

		// value of each field in the array passed in from the Pods hook
		// is stored under the key $fields['fields']['someField']['value']
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
			'replace_interests' => $overwriteExistingGroupingsAtMailChimp,
			'send_welcome' => $pc_sendWelcome
			);

		return $params;
	}

	/**
	 * Retrieves the email from the data from Pods.
	 * 
	 * @param array $hookData
	 * 	An array passed to the callback function by the Pods post-save hook.
	 * 	
	 * @return string
	 *  The email if it can be found.
	 *
	 * @throws  Exception
	 *  If the email cannot be retrieved from $hookData.
	 */
	private function getEmail($hookData) {
		$email = "";
		global $podEmailField;

		pnc_log('info', __METHOD__ . " > Getting email from hook data..."); // debug.
		if (!empty($hookData)) {

			if (isset($hookData['fields'][$podEmailField]['value'])) {
			
				$email = $hookData['fields'][$podEmailField]['value'];	
			
			} else {
				throw new Exception("Cannot retrieve email. Key '$podsEmailField' absent in array.");
			}

		} else {

			throw new Exception("Cannot retrieve email. Argument is empty.");
		}

		pnc_log('info', __METHOD__ . " > Got the email $email"); // debug.
		return $email;
	}

	/**
	 * Checks if the specified email already exists on the specified
	 * MailChimp mailing list.
	 * 
	 * @param  string $email
	 *  The email to check MailChimp for.
	 *
	 * @param string $listID
	 *  The ID of the MailChimp list. This can be obtained from the MailChimp
	 *  website.
	 * 
	 * @return bool
	 *  True if there is a subscriber on MailChimp with the specified
	 *  email address.
	 */
	private function existsOnMailChimp($email, $listID) {
		
		global $pc_mailChimpAPIKey;
		$exists = false;
		$existFlags = array('subscribed', 'pending'); // Set the MailChimp statuses to check for here.

		$mailchimp = new MCAPI($pc_mailChimpAPIKey); // TODO replace with a "global" MCAPI object.
		$result = $mailchimp->listMemberInfo($listID, $email);
		
		if ($result['success'] >= 1) {

			if ($result['success'] > 1) {
				pnc_log('warn', "More than one subscriber found on MailChimp with the email $email");	
			}

			foreach ($result['data'] as $oneResult) {

				pnc_log('info', __METHOD__ . " > ======= MCAPI::listMemberInfo ======= ");

				foreach ($oneResult as $infoKey => $infoValue) {

					pnc_log('info', __METHOD__ . " > $infoKey = $infoValue"); // debug.
					
					if ($infoKey == 'status') {

						foreach ($existFlags as $flag) { // check through the statuses that represent a subscribed address.

							if ($infoValue = $flag) {
								$exists = true;
								pnc_log('info', __METHOD__ . " > ======= member exists ======= ");
								break 3; // stop all loops. we've found what we wanted.
							}
							
						}
							
					}					
				}

				pnc_log('info', __METHOD__ . " > ======= end one member's info ======= ");

			}
			

		} elseif ($result['success'] == 0) {
			pnc_log('warn', "No subscriber found on MailChimp having the email $email");

		}

		// check in case of error with mailchimp calls.
		if ($mailchimp->errorCode) {
				pnc_log('error', __METHOD__ . " > Unable to query MailChimp.\tCode=" . $mailchimp->errorCode . "\tMsg=". $mailchimp->errorMessage);	
		}

		return $exists;
		
	}

	/**
	 * Unsubscribes or deletes a member from a MailChimp list.
	 * This method interfaces with the MailChimp API.
	 * Private method.
	 * 
	 * @param  string $email
	 *  The email address to be unsubscribed.
	 *  
	 * @param  string $listID
	 *  The ID of the list, as assigned by MailChimp.
	 *  
	 * @param  boolean $is_delete
	 *  Whether to remove the email address from the mailing list, on top
	 *  of marking it as unsubscribed.
	 * 
	 * @return void
	 *  Logs the error if there are problems with the call to MailChimp.
	 */
	private function unsubscribe($email, $listID, $is_delete) {
		
		global $pc_mailChimpAPIKey;
		global $pc_sendGoodbye, $pc_unsubscribeNotifications;
		
		$mailchimp  = new MCAPI($pc_mailChimpAPIKey);

		$mailchimp->listUnsubscribe($listID, $email, $is_delete, $pc_sendGoodbye, $pc_unsubscribeNotifications);
		
		if ($mailchimp->errorCode) {
		 	pnc_log('error', "Unable to unsubscribe with MailChimp.\tCode=" . $mailchimp->errorCode . "\tMsg=". $mailchimp->errorMessage);
		} else {

			$message = "";
			if ($is_delete) {
				$message = "Deleted from MailChimp.";
			} else {
				$message = "Unsubscribed from MailChimp.";
			}

			pnc_log('notice', $message);
		    
		}
	}
} // End class declaration.


// Register with WordPress hooks, the necessary functions having been defined above.
register_activation_hook(__FILE__, array('PodNChimp', 'setUp'));
register_uninstall_hook(__FILE__, array('PodNChimp', 'tearDown'));

add_action('plugins_loaded', array('PodNChimp', 'getInstance')); // get an instance so that we can register with Pods' hooks, if not already done.

?>