<?php 
	/*
	Plugin Name:  Waves Fetch
	Plugin URI:   https://github.com/morganleek/waves-fetch/
	Description:  WP Plugin for fetching buoy data via AWS
	Version:      0.1.3
	Author:       https://morganleek.me/
	Author URI:   https://morganleek.me/
	License:      GPL2
	License URI:  https://www.gnu.org/licenses/gpl-2.0.html
	Text Domain:  wporg
	Domain Path:  /languages
	*/

	// Security
	defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
	
	// Plugin Data
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	$plugin_data = get_plugin_data( __FILE__ );
	
	// Paths
	define( 'WAF__PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
	define( 'WAF__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'WAF__VERSION', $plugin_data['Version'] );

	// Admin
	require_once( WAF__PLUGIN_DIR . 'includes/admin.php' );

	// Fetch Mechanism
	require_once( WAF__PLUGIN_DIR . 'includes/fetch.php' );

	// Return 
	require_once( WAF__PLUGIN_DIR . 'includes/ajax.php' );