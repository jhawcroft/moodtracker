<?php
if( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) 
	// Make sure not to call this file directly
	exit();

global $wpdb, $table_prefix;

$table_name = $table_prefix.'jh_mood_tracker';
$sql = "DROP TABLE IF EXISTS `" . $table_name . "`";
$wpdb->query($sql);


?>