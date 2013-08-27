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
// Ensure the below values are specified with MailChimp as part of the
// webhook callback URL.
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
// THAT'S ALL
// =============================================================================


$dbEmailColumn = $podEmailField;
$podsDataKey = 'fields';

// Constants for the MailChimp admin to set.

$pc_grouping_countriesOfInterest = "Countries of interest";
$pc_grouping_organization1 = "Organization 1";
$pc_grouping_organization2 = "Organization 2";
$pc_grouping_organization3 = "Organization 3";

// MailChimp
$webhookEventKey = 'type'; // key as in array key.
$unsubscribeEvent = 'unsubscribe';
$updateEvent = 'profile';
$emailChangeEvent = 'upemail';

$payloadKey = 'data'; 
$emailKey = 'email'; 
$oldEmailKey = 'old_email';
$newEmailKey = 'new_email';

$pc_firstNameMerge = 'FNAME';
$pc_lastNameMerge = 'LNAME';

// Debugging
$chimpme_debug = true; // set to false in production to remove verbose logs.

?>