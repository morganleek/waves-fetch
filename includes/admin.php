<?php 
	// Admin Settings
	require_once( WAF__PLUGIN_DIR . 'includes/admin/settings.php' );
	// Admin Options
	require_once( WAF__PLUGIN_DIR . 'includes/admin/menus.php' );
	// Waves Database Setup
	require_once( WAF__PLUGIN_DIR . 'includes/admin/database.php' );
	// File Cache
	require_once( WAF__PLUGIN_DIR . 'includes/admin/cache.php' );


	function waf_check_version() {
		if( get_site_option( 'waf_database_version' ) != WAF__VERSION ) {
			// Update Database
			waf_database_init(); 
			// Update Files
			waf_cache_init();
		}
	}

	add_action( 'plugins_loaded', 'waf_check_version' );