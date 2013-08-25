<?php

/*
 * This file contains constants used throughout the Pod 'n Chimp plug-in.
 */

// ====================
// Database parameters
// ====================

$dbTableName = "wp_pods_contact";
$dbEmailColumn = "email";
$dbUnsubscribeColumn = "newsletter";
$dbUnsubscribeBit = 0; // This value represents an unsubscribe.

// ====================
// Pods CMS parameters
// ====================

// Details for the Pod of interest.
$podName = 'contact';
$podEmailField = 'email';

// Universal Pods contants.
$podsDataKey = 'fields';

// ====================
// MailChimp constants
// ====================

// Constants for the MailChimp admin to set.

// $pc_mailChimpAPIKey = 'YOUR-API-KEY-HERE'; // Un-comment this line and delete the line 2 lines below.
// $pc_mailChimpListID = 'YOUR-LIST-ID-HERE'; // Un-comment this line and delete the line 2 lines below.
$pc_mailChimpAPIKey = 'e4f4cfc633fea7d08a90da053719685e-us7';
$pc_mailChimpListID = 'b970dd90fa';

$pc_grouping_countriesOfInterest = "Countries of interest";
$pc_grouping_organization1 = "Organization 1";
$pc_grouping_organization2 = "Organization 2";
$pc_grouping_organization3 = "Organization 3";

$pc_doubleOptIn = false; // Whether subscribers who are new to MailChimp will receive an opt-in email. Recommended by MailChimp guidelines.
$pc_sendWelcome = false; // Whether to sends a welcome email instead, if double opt-in is not enabled.
$pc_sendGoodbye = true; // Sends a goodbye email to the email address on unsubscribe.
$pc_unsubscribeNotifications = true; // Whether to send a notification email to the address defined in the list email notification settings.
$pc_defaultEmailPreference = 'html'; // Email type preference for the email ('html' or 'text'). MailChimp's default is 'html'.

// Developer constants.
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

// ===========
// Debugging
// ============
$chimpme_debug = true; // set to false in production to remove verbose logs.

?>