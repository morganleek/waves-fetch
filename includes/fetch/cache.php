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
			if( substr($memplot->full_path, 0, 4) === "http" ) {
				$memplot->full_path;
			}
			else {
				return waf_get_local_path( $memplot->full_path );
			}
		}

		return;
	}

	function waf_get_buoy_image_path( $buoy_id = 0 ) {
		global $wpdb;
		// Check if using AWS
		$waf_s3 = get_option('waf_s3');
		
		if( $buoy_id == 0 ) {
			return;
		}

		$buoy = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}waf_buoys
				WHERE `id` = %d",
				$buoy_id
			)
		);

		if( $wpdb->num_rows > 0 ) {
			if( substr($buoy->image, 0, 4) === "http" ) {
				return $buoy->image; // waf_get_local_path();
			}
			else {
				return waf_get_local_path($buoy->image);
			}	
		}

		return;
	}

	function waf_get_local_path( $path ) {
		if( !file_exists( WAF__PLUGIN_DIR . 'cache/' . $path ) ) {
			// File doesn't exist, fetch it
			if( waf_fetch_cache_resource( $path, WAF__PLUGIN_DIR . 'cache/' . $path ) ) {
				return WAF__PLUGIN_URL . 'cache/' . $path;
			}
			else {
				return;
			}
		}
		// File exists already
		return WAF__PLUGIN_URL . 'cache/' . $path;
	}

	function waf_get_file_path_ajax( ) {
		$id = 0;
		if( isset( $_REQUEST['id'] ) ) {
			$id = intval( $_REQUEST['id'] ); 
		}
		if( isset( $_REQUEST['buoy_id'] ) ) {
			$buoy_id = intval( $_REQUEST['buoy_id'] ); 
		}

		$local_path = waf_get_file_path( $id );
		if( !empty( $local_path ) ) {
			print json_encode( array( 'id' => $id, 'buoy_id' => $buoy_id, 'path' => $local_path ) );
		}
		wp_die();
	}

	// Action: waf_get_file_path
	add_action( 'wp_ajax_waf_get_file_path', 'waf_get_file_path_ajax' );
	add_action( 'wp_ajax_nopriv_waf_get_file_path', 'waf_get_file_path_ajax' );

	

	function waf_get_buoy_image_path_ajax( ) {
		$buoy_id = 0;
		if( isset( $_REQUEST['buoy_id'] ) ) {
			$buoy_id = intval( $_REQUEST['buoy_id'] ); 
		}

		$local_path = waf_get_buoy_image_path( $buoy_id );
		if( !empty( $local_path ) ) {
			print json_encode( array( 'buoy_id' => $buoy_id, 'path' => $local_path ) );
		}
		wp_die();
	}

	// Action: waf_get_buoy_image_path
	add_action( 'wp_ajax_waf_get_buoy_image_path', 'waf_get_buoy_image_path_ajax' );
	add_action( 'wp_ajax_nopriv_waf_get_buoy_image_path', 'waf_get_buoy_image_path_ajax' );

	
