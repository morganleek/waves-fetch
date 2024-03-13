<?php
	function waf_register_settings() {
		// Register Settings Options
		register_setting( 
			'waf-buoy-options', 
			'waf_s3',
			array(
				'sanitize_callback' => 'waf_sanitize_options'
			)
		);

		register_setting( 
			'waf-buoy-options', 
			'waf_spotter',
			array(
				'sanitize_callback' => 'waf_sanitize_options'
			)
		);

		register_setting( 
			'waf-buoy-options', 
			'waf_local_csv',
			array(
				'sanitize_callback' => 'waf_sanitize_options'
			)
		);

		register_setting( 
			'waf-buoy-options', 
			'waf_willy_weather',
			array(
				'sanitize_callback' => 'waf_sanitize_options'
			)
		);

		register_setting(
			'waf-buoy-options-refresh',
			'waf_refresh',
			array(
				'sanitize_callback' => 'waf_sanitize_options_refresh'
			)
		);

		register_setting(
			'waf-buoy-options-refresh-images',
			'waf_refresh_images',
			array(
				'sanitize_callback' => 'waf_sanitize_options_refresh_images'
			)
		);

		register_setting(
			'waf-buoy-options-migrate',
			'waf_migrate',
			array(
				'sanitize_callback' => 'waf_sanitize_options_migrate'
			)
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

	function waf_sanitize_options_refresh_images( $option ) {
		global $wpdb;
		// Get all buoy images 
		$all = $wpdb->get_results( "SELECT `image` FROM `{$wpdb->prefix}waf_buoys`" );

		$paths = [];
		foreach( $all as $row ) {
			// Get full path to file
			$path = WAF__PLUGIN_DIR . 'cache/' . $row->image;
			// Check exisitance
			if( is_file( $path ) ) {
				// Delete
				unlink( $path ); 
				// List to be returned to the user
				$paths[] = $path; 
			}
		}

		// Success Message
		add_settings_error( 'waf-buoy-options-refresh-images', 'waf-success', "Deleted - " . join(", ", $paths), 'success' );

		return $option;
	}

	function waf_sanitize_options_migrate( $option ) {
		if( 
			empty( $option['waf_migrate_from'] ) || 
			empty( $option['waf_migrate_to'] ) || 
			empty( $option['waf_start_date'] ) || 
			empty( $option['waf_end_date'] ) ) {
				// Missing data
				add_settings_error( 'waf-buoy-options-migrate', 'waf-success', __( 'Missing required fields', 'wporg' ), 'error' );
				return $option;
		}

		// Extract Option Values
		extract( $option );

		// Check Buoys
		if( $waf_migrate_from == $waf_migrate_to ) {
			// Same Buoys
			add_settings_error( 'waf-buoy-options-migrate', 'waf-success', __( 'Select different buoys', 'wporg' ), 'error' );
			return $option;
		}

		// Convert to datetime
		$waf_start_datetime = strtotime( $waf_start_date );
		$waf_end_datetime = strtotime( $waf_end_date );
		
		// Check Date
		if( $waf_end_datetime < $waf_start_datetime ) {
			// End before the start
			add_settings_error( 'waf-buoy-options-migrate', 'waf-success', __( 'Your end date is before your start date', 'wporg' ), 'error' );
			return $option;
		}
		
		global $wpdb;

		// Get number of effected items
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				FROM `{$wpdb->prefix}waf_wave_data` 
				WHERE `buoy_id` = %d 
				AND `timestamp` >= %d 
				AND `timestamp` <= %d",
				$waf_migrate_from,
				$waf_start_datetime,
				$waf_end_datetime
			)
		);
		
		// Do it as a test
		if( isset( $_REQUEST['test'] ) ) {
			add_settings_error( 'waf-buoy-options-migrate', 'waf-success', __( 'This will effect ' . $count . ' items', 'wporg' ), 'success' );
			add_settings_error( 'waf-buoy-options-migrate', 'waf-migrate-test', array(
				'waf_migrate_from' => $waf_migrate_from,
				'waf_migrate_to' => $waf_migrate_to,
				'waf_start_date' => $waf_start_date,
				'waf_end_date' => $waf_end_date
			), 'data' );
		}
		// Do it for real
		else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}waf_wave_data
					SET `buoy_id` = %d
					WHERE `buoy_id` = %d
					AND `timestamp` >= %d
					AND `timestamp` <= %d",
					$waf_migrate_to,
					$waf_migrate_from,
					$waf_start_datetime,
					$waf_end_datetime
				)
			);

			add_settings_error( 'waf-buoy-options-migrate', 'waf-success', __( $count . ' data points updated', 'wporg' ), 'success' );
		}
		return $option;
	}

	// Hooks
	add_action('admin_init', 'waf_register_settings');