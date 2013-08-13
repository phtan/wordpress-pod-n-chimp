<?php

/*
 * This file contains constants used throughout the Pod 'n Chimp plug-in.
 */

// DB parameters
$dbTableName = "wp_pods_contact";
$dbEmailColumn = "email";

// Pods CMS parameters
$podName = 'contact';
$podEmailField = 'email';

// MailChimp constants
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
$chimpme_usingStubs = false; // Set this to false if receiving webhooks from MailChimp (ie. when this plug-in is sent to production.)
$chimpme_stubType = 'upemail';

?>