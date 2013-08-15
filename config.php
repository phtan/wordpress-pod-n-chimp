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

// $pnc_mailChimpListId = 'YOUR-LIST-ID-HERE'; // Un-comment this line and delete the line below.
$pc_mailChimpListId = 'b970dd90fa';

$pc_doubleOptIn = true; // Whether subscribers who are new to MailChimp will receive an opt-in email. Recommended by MailChimp guidelines.
$pc_unsubscribeNotifications = true; // Whether to send a notification email to the address defined in the list email notification settings.

$pc_grouping_countriesOfInterest = "Countries of interest";
$pc_grouping_organization = "Organizations";

// Developer constants.
$webhookEventKey = 'type'; // key as in array key.
$unsubscribeEvent = 'unsubscribe';
$updateEvent = 'profile';
$emailChangeEvent = 'upemail';

$payloadKey = 'data'; 
$emailKey = 'email'; 
$oldEmailKey = 'old_email';
$newEmailKey = 'new_email';

// ===========
// Debugging
// ============
$chimpme_debug = true; // set to false in production to remove verbose logs.

?>