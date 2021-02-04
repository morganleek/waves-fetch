<?php
	// List Buoys
	function waf_rest_list_buoys( $id = 0 ) {
		global $wpdb;

		// All buoys
		$query = "SELECT * FROM {$wpdb->prefix}waf_buoys";
		// Specific buoy
		if( $id != 0 ) {
			$query = $wpdb->prepare(
				$query . " WHERE `id` = %d",
				$id
			);
		}
		// Get buoys
		$buoys = $wpdb->get_results( $query );
		// Return JSON
		return json_encode( $buoys );
	}
	
	function waf_rest_list_buoys_ajax( ) {
		$id = 0;
		if( isset( $_REQUEST['id'] ) ) {
			$id = intval( $_REQUEST['id'] ); 
		}
		print waf_rest_list_buoys( $id );
		wp_die();
	}

	add_action( 'wp_ajax_waf_rest_list_buoys', 'waf_rest_list_buoys_ajax' );
	add_action( 'wp_ajax_nopriv_waf_rest_list_buoys', 'waf_rest_list_buoys_ajax' );

	// List Buoy Datapoints
	function waf_rest_list_buoy_datapoints( $id = 0, $start = 0, $end = 0 ) {
		global $wpdb;
		$default_range = "-2 days";
		$default_data_points = 48;

		// Fetch in timeframe
		$query = $wpdb->prepare( 
			"SELECT * FROM {$wpdb->prefix}waf_wave_data 
			WHERE `buoy_id` = %d",
			$id
		);

		// No range set
		if( $start == 0 && $end == 0 ) {
			$query = $wpdb->prepare( 
				$query . " AND `timestamp` > %d",
				date( 'U', strtotime( $default_range ) )
			); 
		}
		else {
			// Range set
			if( $start != 0 ) {
				$query = $wpdb->prepare( 
					$query . " AND `timestamp` > %d",
					$start
				); 
			}

			if( $end != 0 ) {
				$query = $wpdb->prepare( 
					$query . " AND `timestamp` < %d",
					$end
				); 
			}
		}

		$data = $wpdb->get_results( $query, 'ARRAY_A' );

		// No results in that time range?
		if( $wpdb->num_rows == 0 ) {
			// Get most recent n data points
			$data = $wpdb->get_results(
				$wpdb->prepare( 
					"SELECT * FROM {$wpdb->prefix}waf_wave_data 
					WHERE `buoy_id` = %d
					ORDER BY `timestamp` DESC
					LIMIT %d",
					$id, $default_data_points
				),
				'ARRAY_A'
			);
		}

		// array_walk( $data, function( &$data, $key ) {
		// 	// Convert serialized data to JSON
		// 	$data['data_points'] = unserialize( $data['data_points'] );
		// } );

		return json_encode( $data );
	}
	
	function waf_rest_list_buoy_datapoints_ajax( ) {
		$id = 0;
		if( isset( $_REQUEST['id'] ) ) {
			$id = intval( $_REQUEST['id'] ); 
		}
		else {
			// No ID set
			print 0;
			wp_die();
		}

		// Check for start and end dates
		$start = 0;
		if( isset( $_REQUEST['start'] ) ) {
			$start = intval( $_REQUEST['start'] );
		}
		$end = 0;
		if( isset( $_REQUEST['end'] ) ) {
			$end = intval( $_REQUEST['end'] );
		}

		print waf_rest_list_buoy_datapoints( $id, $start, $end );
		wp_die();
	}

	add_action( 'wp_ajax_waf_rest_list_buoy_datapoints', 'waf_rest_list_buoy_datapoints_ajax' );
	add_action( 'wp_ajax_nopriv_waf_rest_list_buoy_datapoints', 'waf_rest_list_buoy_datapoints_ajax' );