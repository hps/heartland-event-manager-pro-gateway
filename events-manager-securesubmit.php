<?php
/**
 * SecureSubmit add-on for Events Manager Pro 
 *
 * An addon for the Events Manager Pro plugin that provides a gateway for SecureSubmit
 *
 * @package   Events_Manager_SecureSubmit
 * @author    Mark Hagan (mark.hagan@e-hps.com)
 * @license   Custom (https://github.com/SecureSubmit/WordPress/blob/master/LICENSE.md)
 * @link      http://wordpress.org/plugins/events-manager-pro-securesubmit-gateway/
 *
 * @wordpress-plugin
 * Plugin Name: Events Manager Pro SecureSubmit Gateway
 * Plugin URI:  http://wordpress.org/plugins/events-manager-pro-securesubmit-gateway/
 * Description: A SecureSubmit Gateway add-on for the Event Manager Pro plugin
 * Version:     1.0.0
 * Author:      Mark Hagan <mark.hagan@e-hps.com>
 * Author URI:  https://developer.heartlandpaymentsystems.com/
 * Text Domain: events-manager-securesubmit-locale
 * License:     Custom
 * License URI: https://github.com/SecureSubmit/WordPress/blob/master/LICENSE.md
 * Domain Path: /lang
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die('The SecureSubmit plugin is not meant to be called directly.');
}

/**
 * events manager pro is a pre-requisite
 */
function emp_securesubmit_prereq() {
    ?> <div class="error"><p><?php _e('Please ensure you have <a href="http://eventsmanagerpro.com/">Events Manager Pro</a>
            installed, as this is a requirement for the SecureSubmit plugin.','events-manager-securesubmit'); ?></p>
       </div>
    <?php
}

/**
 * initialise plugin once other plugins are loaded 
 */
function emp_securesubmit_register() {
    //check that EM Pro is installed
    if( ! defined( 'EMP_VERSION' ) ) {
        add_action( 'admin_notices', 'emp_securesubmit_prereq' );
        return false; //don't load plugin further
    }

    require_once( plugin_dir_path( __FILE__ ) . 'class-events-manager-securesubmit.php' );
    EM_Gateways::register_gateway('securesubmit', 'EM_Gateway_SecureSubmit');

}

add_action( 'plugins_loaded', 'emp_securesubmit_register', 1000);

/**
 * Set meta links in the plugins page 
 */
function emp_securesubmit_metalinks( $actions, $file, $plugin_data ) {
    $new_actions = array();
    $new_actions[] = sprintf( '<a href="'.EM_ADMIN_URL.'&amp;page=events-manager-gateways&amp;action=edit&amp;gateway=securesubmit">%s</a>', __('Settings', 'dbem') );
    $new_actions = array_merge( $new_actions, $actions );
    return $new_actions;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'emp_securesubmit_metalinks', 10, 3 );

/* eof */
