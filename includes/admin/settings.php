<?php
	function waf_register_settings() {
		// Register Settings Options
		register_setting( 
			'waf-buoy-options', 
			'waf_s3',
			'waf_sanitize_options'
		);

		register_setting(
			'waf-buoy-options-refresh',
			'waf_refresh',
			'waf_sanitize_options_refresh'
		);
	}

	function waf_sanitize_options( $option ) {
		// Sanitize Settings Options
		// todo
		return $option;
	}

	function waf_sanitize_options_refresh( $option ) {
		// Sanitize Settings Options
		if( isset( $option['buoy'] ) ) {
			// Buoy Id
			$id = intval( $option['buoy'] );
			
			// Delete this buoy
			global $wpdb;
			$wpdb->delete( "{$wpdb->prefix}waf_buoys", array( 'id' => $id ) );

			// Success Message
			add_settings_error( 'waf-buoy-options-refresh', 'waf-success', __( 'Processing buoy #' . $id, 'wporg' ), 'success' );
		} 
		
		return $option;
	}

	// Hooks
	add_action('admin_init', 'waf_register_settings');