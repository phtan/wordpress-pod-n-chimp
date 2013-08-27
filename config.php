<?php

/*
 * This file contains constants used throughout the Pod 'n Chimp plug-in.
 */

// =============================================================================
// SET THESE
// =============================================================================

// Your MailChimp details.
$pc_mailChimpAPIKey = 'YOUR-API-KEY';
$pc_mailChimpListID = 'YOUR-LIST-ID'; // The list you wish to sync with.

// Pods details.
$podName = 'contact'; // Pod to sync with.
$podEmailField = 'email'; // Name of the Pod field containing email addresses.

// WordPress database details.
$dbTableName = "wp_pods_contact"; // Name of the database table for the Pod.
$dbUnsubscribeColumn = "newsletter"; // The column tracking unsubscribes.
$dbUnsubscribeBit = 0; // The value representing an unsubscribe.

// Webhook security.
// Ensure the below values are specified on MailChimp, as part of the
// URL that you provide for the webhook callback.
// 
// For example, the callback URL should look like
// http://send.callback.to/this-plugin.php?authenticateKey=authenticateValue)
$authenticateKey = "ANY-NAME-FOR-YOUR-KEY";
$authenticateValue = "HELLO-FROM-MAILCHIMP"; // Alphanumeric sequence recommended.

// =============================================================================
// OPTIONS FOR MAILCHIMP
// =============================================================================

// Whether subscribers who are new to MailChimp will receive an opt-in email.
// Recommended by MailChimp guidelines.
$pc_doubleOptIn = false;

 // Whether to send a welcome email if the double opt-in above is not enabled.
$pc_sendWelcome = false;

 // Whether to send a notification email to the address defined in the List's
 // email notification settings.
$pc_unsubscribeNotifications = true;

 // Email type preference for the email ('html' or 'text'). MailChimp's
 // default is 'html'.
$pc_defaultEmailPreference = 'html';

// =============================================================================
// SYNC SETTINGS
// =============================================================================

// This section tells the plug-in what to sync.
// You will need to provide these details:
// (a) Names of your Pods fields
// (b) Merge tags you use in your MailChimp campaign (and want sync-ed).
//     Eg. "FNAME", "LNAME".
// (c) Interest Groupings you have set up for your MailChimp campaign.
//     Eg. "Countries of Interest", "Sectoral Interest".

// Sync-ing from Pods to MailChimp
// -----------------------------------------------------------------------------
// Specify the Pods fields that you want sync-ed to MailChimp, and their
// corresponding MailChimp Interest Groupings.
$pc_podsFields = array(

	// Pods field => MailChimp grouping
	'countries_of_interest' => 'Countries of interest',
	'organization' => 'Organization 1',
	'organization2' => 'Organization 2',
	'organization3' => 'Organization 3'

);

// Sync-ing from MailChimp to Pods
// -----------------------------------------------------------------------------
// Your MailChimp merge tags go here, with the Pods fields they should sync to.
$pc_mergeTags = array(

	// MailChimp merge tag => Pods field
	'FNAME' => 'first_name',
	'LNAME' => 'last_name'

);
// =============================================================================
// THAT'S ALL
// =============================================================================

// Database
$dbEmailColumn = $podEmailField;

// Pods
$podsDataKey = 'fields';

// MailChimp
$webhookEventKey = 'type'; // key as in array key.
$unsubscribeEvent = 'unsubscribe';
$updateEvent = 'profile';
$emailChangeEvent = 'upemail';

$payloadKey = 'data'; 
$emailKey = 'email'; 
$oldEmailKey = 'old_email';
$newEmailKey = 'new_email';

$interestGroupsMergeTag = 'GROUPINGS';

$pc_relevantKeys = array('id', 'email', 'merges', 'list_id'); // Keys for the information to retain from MailChimps' webhook payload.
$pc_relevantMergeTags = array_merge((array)$interestGroupsMergeTag, (array)array_keys($pc_mergeTags)); // Merge tags, in the MailChimp payload, to keep.

// Debugging
$chimpme_debug = false; // set to false to remove verbose logs.

?>