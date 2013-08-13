<?php

/*
Plugin Name: Pods 'n Chimp
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

register_activation_hook(__FILE__, array('PodsNChimp', 'setUp'));
register_uninstall_hook(__FILE__, array('PodsNChimp', 'tearDown'));

add_action('plugins_loaded,' array('PodsNChimp', 'getInstance'));

class PodsNChimp {

	private static $instance;

	private function __construct() {
		// TODO
		// add_filter(...)
	}


	// Implements singleton pattern in case this class
	// ends up being used in template files/plug-ins.
	public static function getInstance() {

		if (self::$instance == null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public static function setUp() {
		// TODO code for any wpdb database prep goes here. eg. store preferences.
	}

	public static function tearDown() {
		// TODO remove any wpdb traces.
	}

	// TODO function thisIsAFilter($pieces, ...) {}
}

?>