<?php

	// Check for local copy
	// yes : show local copy
	// no : fetch aws version
		// show local copy

	function waf_get_file_path( $id = 0 ) {
		global $wpdb;
		
		if( $id == 0 ) {
			return;
		}

		$memplot = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}waf_wave_memplots
				WHERE `id` = %d",
				$id
			)
		);

		if( $wpdb->num_rows > 0 ) {
			$path = $memplot->full_path;
			if( file_exists( WAF__PLUGIN_DIR . 'cache/' . $path ) ) {
				// File exists already
				return WAF__PLUGIN_URL . 'cache/' . $path;
			}
			else {
				// File doesn't exist, fetch it
				if( waf_fetch_cache_resource( $path, WAF__PLUGIN_DIR . 'cache/' . $path ) ) {
					return WAF__PLUGIN_URL . 'cache/' . $path;
				}
			}
		}

		return;
	}

	function waf_get_file_path_ajax( ) {
		$id = 0;
		if( isset( $_REQUEST['id'] ) ) {
			$id = intval( $_REQUEST['id'] ); 
		}
		print waf_get_file_path( $id );
		wp_die();
	}

	// Action: waf_get_file_path
	add_action( 'wp_ajax_waf_get_file_path', 'waf_get_file_path_ajax' );
	add_action( 'wp_ajax_nopriv_waf_get_file_path', 'waf_get_file_path_ajax' );
