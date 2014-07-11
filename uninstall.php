<?php
//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

$option_names = array('cdn_scripts','registered_scripts','override_scripts');

foreach($option_names as $option_name) {
	delete_option($option_name);
	// For site options in multisite
	delete_site_option( $option_name );  
}
wp_clear_scheduled_hook('load_cdn_scripts');

//drop a custom db table
//global $wpdb;
//$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mytable" );

//note in multisite looping through blogs to delete options on each blog does not scale. You'll just have to leave them.

?>