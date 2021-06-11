<?php
	// Notice
	function waf_cache_folder_created( ) {
		print '<div class="notice notice-success is-dismissible">';
			print '<p>Wave Fetch Cache Folder Created</p>';
		print '</div>'; 
	}

	function waf_cache_folder_failed( ) {
		print '<div class="notice notice-error is-dismissible">';
			print '<p>Wave Fetch Cache Folder Failed to be Created!</p>';
		print '</div>'; 
	}
	
	function waf_cache_init() {
		// Check if cache folder exists
		if( !is_dir( WAF__PLUGIN_DIR . 'cache' ) ) {
			// Create it if it doesn't
			if( wp_mkdir_p( WAF__PLUGIN_DIR . 'cache' ) ) {
				add_action( 'admin_notices', 'waf_cache_folder_created' );
			}
			else {
				add_action( 'admin_notices', 'waf_cache_folder_failed' );
			}
		}


	}