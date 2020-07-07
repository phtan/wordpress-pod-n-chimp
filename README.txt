=== Pod 'n Chimp ===
Contributors: phtan
Tags: pods, mailchimp, sync, link, middleman, broker, cms, email
Tested up to: 3.5.1
Author URI: http://phtan.github.io
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows two-way syncs between MailChimp and the Pods Framework plug-in.

* As of 7th July 2020, this repository is no longer maintained. *

== Description ==

Pod 'n Chimp saves you the hassle of updating your subscribers' details on both MailChimp and Pods. Make changes on one and it appears in the other.

Tested with WordPress 3.5.1, Pods 2.3.6 and MailChimp API v1.3.

= Feature list =

These actions are sync-ed:

**Pods to MailChimp**
* Add a new contact
* Update first and last names
* Delete a contact
* Update subscription status (if you have a "newsletter" column tracking subscriptions)
* Update Pods Relationship fields, like the Organization your contact works at (beta feature)

**MailChimp to Pods**
* Update first and last names
* Unsubscribe from the mailing list
* Update Interest Groups (syncs with the relevant Pods Relationship field) (beta feature)

== Installation ==

1. Upload the contents of this plug-in's zip file to the `/wp-content/plugins/`
   directory.
2. Activate the "Pod 'n Chimp" plug-in through the "Plugins" menu in WordPress.
3. Tell MailChimp to keep this plug-in in the loop, by visiting the Webhook settings
   for the mailing list. It can be found at `MailChimp Dashboard > Lists > Your list > Settings > Webhooks`
   + Click 'Add A New Webhook'
   + Specify the path to the `receiver.php` file, bundled with this plug-in, as the Callback URL.
     For example, `http://your-site.com/wp-content/plugins/podnchimp/receiver.php?key=pass`
     Note the `key` and `pass` at the end; substitute them with your own, say,
     `my-key` and `my-pass`, as they are used for authentication purposes later.
   + Ensure 'the API' option for 'Send updates when a change is made by' is unchecked.
     Tick the other options.
   + Click 'Save'.
4. Open the bundled file `config.php`. Fill in the settings in the file.

== Frequently Asked Questions ==

= Why isn't the email address synced when I update it on either Pods or MailChimp? =

Updates to email addresses will not be synced. This is due to a limitation where
each subscriber is identified by his/her email address; a change in the email
address implies a different sync target (which usually cannot be found). This
issue is marked for resolution in a future release of the plug-in.

= Is there an easier way to tell if there has been a sync? =

Yes. Success messages as well as error messages are in logged in the `/logs`
directory, on a daily basis.

= How do I make suggestions or report bugs for this plugin? =

Send an email to the author at <phengheong@hotmail.com>.

= What do you mean by a "beta" feature? =

A feature is in beta if it hasn't been tested.


== Caveats ==

* The Interest Groupings that correspond to Pods relationship fields must be
  set up in MailChimp, before both can be mutually synced.
* Removal of subscribers from a MailChimp mailing list is irreversible. For this
  reason, take care when deleting items from *Pods* while this plug-in is activated.

== Future work ==

* Import existing Pods contacts to the MailChimp mailing list. \[First-time sync to MailChimp\] 
* Sync subscribers added to the mailing list. \[Sync to Pods\] 
* Update a contact's email address \[Sync from either one to the other\]

(End of document)
