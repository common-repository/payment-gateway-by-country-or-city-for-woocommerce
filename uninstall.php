<?php 
/**
 * Payment gateway by country or city for Woocommerce Uninstall
 *
 * Uninstalling PLUGIN deletes its table
 *
 * Version: 1.0
 */


// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
 
// drop a custom database table
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ptwpgbcc");

?>