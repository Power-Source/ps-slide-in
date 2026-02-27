<?php
/*
Plugin Name: PS Slide-In
Plugin URI: https://cp-psource.github.io/ps-slide-in/
Description: Erstelle und verwalte schöne Marketingbotschaften und konvertiere dann Deine Zielgruppe so, dass sie nicht gestört wird.
Version: 1.4.0
Text Domain: wdsi
Author: PSOURCE
Author URI: https://github.com/cp-psource


Copyright 2020-2023 PSOURCE (https://github.com/cp-psource) 
Authors - DerN3rd (PSOURCE)
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


define ('WDSI_CURRENT_VERSION', '1.4.0');
define ('WDSI_PLUGIN_SELF_DIRNAME', basename(dirname(__FILE__)));
define ('WDSI_PROTOCOL', (is_ssl() ? 'https://' : 'http://'));

//Setup proper paths/URLs and load text domains
if (is_multisite() && defined('WPMU_PLUGIN_URL') && defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename(__FILE__))) {
	define ('WDSI_PLUGIN_LOCATION', 'mu-plugins', true);
	define ('WDSI_PLUGIN_BASE_DIR', WPMU_PLUGIN_DIR, true);
	define ('WDSI_PLUGIN_URL', str_replace('http://', WDSI_PROTOCOL, WPMU_PLUGIN_URL), true);
	$textdomain_handler = 'load_muplugin_textdomain';
} else if (defined('WP_PLUGIN_URL') && defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/' . WDSI_PLUGIN_SELF_DIRNAME . '/' . basename(__FILE__))) {
	define ('WDSI_PLUGIN_LOCATION', 'subfolder-plugins');
	define ('WDSI_PLUGIN_BASE_DIR', WP_PLUGIN_DIR . '/' . WDSI_PLUGIN_SELF_DIRNAME);
	define ('WDSI_PLUGIN_URL', str_replace('http://', WDSI_PROTOCOL, WP_PLUGIN_URL) . '/' . WDSI_PLUGIN_SELF_DIRNAME);
	$textdomain_handler = 'load_plugin_textdomain';
} else if (defined('WP_PLUGIN_URL') && defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/' . basename(__FILE__))) {
	define ('WDSI_PLUGIN_LOCATION', 'plugins', true);
	define ('WDSI_PLUGIN_BASE_DIR', WP_PLUGIN_DIR, true);
	define ('WDSI_PLUGIN_URL', str_replace('http://', WDSI_PROTOCOL, WP_PLUGIN_URL), true);
	$textdomain_handler = 'load_plugin_textdomain';
} else {
	// No textdomain is loaded because we can't determine the plugin location.
	// No point in trying to add textdomain to string and/or localizing it.
	wp_die(__('There was an issue determining where Slide In plugin is installed. Please reinstall.'));
}
$textdomain_handler('wdsi', false, WDSI_PLUGIN_SELF_DIRNAME . '/languages/');

require_once WDSI_PLUGIN_BASE_DIR . '/lib/class_wdsi_mailchimp.php';
require_once WDSI_PLUGIN_BASE_DIR . '/lib/class_wdsi_options.php';
require_once WDSI_PLUGIN_BASE_DIR . '/lib/functions.php';
/*
Wdsi_Options::populate();
*/

require_once WDSI_PLUGIN_BASE_DIR . '/lib/class_wdsi_slide_in.php';
Wdsi_SlideIn::init();

if (is_admin()) {
	
	require_once WDSI_PLUGIN_BASE_DIR . '/lib/class_wdsi_admin_form_renderer.php';
	require_once WDSI_PLUGIN_BASE_DIR . '/lib/class_wdsi_admin_pages.php';
	Wdsi_AdminPages::serve();
} else {
	require_once WDSI_PLUGIN_BASE_DIR . '/lib/class_wdsi_public_pages.php';
	Wdsi_PublicPages::serve();
}