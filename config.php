<?php

/*
 * This file contains constants used throughout the Pod 'n Chimp plug-in.
 */

// DB parameters
$dbTableName = "wp_pods_contact";
$dbEmailColumn = "email";
$dbUnsubscribeColumn = "newsletter";
$dbUnsubscribeBit = 0; // This value represents an unsubscribe.

// Pods CMS parameters
$podName = 'contact';
$podEmailField = 'email';

// MailChimp constants
// $pnc_mailChimpListId = 'YOUR-LIST-ID-HERE'; // Un-comment this line and delete the line below.
$pnc_mailChimpListId = 'b970dd90fa';
$webhookEventKey = 'type'; // key as in array key.
$unsubscribeEvent = 'unsubscribe';
$updateEvent = 'profile';
$emailChangeEvent = 'upemail';

$payloadKey = 'data'; 
$emailKey = 'email'; 
$oldEmailKey = 'old_email';
$newEmailKey = 'new_email';

// Debugging
$chimpme_debug = true; // set to false in production to remove verbose logs.

?>