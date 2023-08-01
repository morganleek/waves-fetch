<?php
	// Compsore AWS Library
	require_once WAF__PLUGIN_DIR . 'vendor/autoload.php';

	use League\Csv\Writer;

	// List Buoys
	function waf_rest_list_buoys( $id = 0 ) {
		global $wpdb;
		
		// Check for cached data
		$cached = wp_cache_get( 'list_buoys', 'waf_rest' );

		if( $cached === false ) {
			// All buoys
			$query = "SELECT *, UNIX_TIMESTAMP() AS `now` 
			FROM {$wpdb->prefix}waf_buoys
			WHERE `is_enabled` != 0";
			// Specific buoy
			if( $id != 0 ) {
				$query = $wpdb->prepare(
					$query . " AND `id` = %d",
					$id
				);
			}
			// Order by Menu Order
			$query = $query . " ORDER BY `menu_order`";
			// Get buoys
			$buoys = $wpdb->get_results( $query );
			// Cache results for 5 minutes
			wp_cache_set( 'list_buoys', $buoys, 'waf_rest', 300 );
			// Return JSON
			return json_encode( $buoys );
		}
		else { // Cached results
			return json_encode( $cached );
		}
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
	function waf_rest_list_buoy_datapoints( $args = [] ) {
		global $wpdb;
		
		$defaults = array(
			'id' => 0,
			'start' => 0,
			'end' => 0,
			'table' => $wpdb->prefix . 'waf_wave_data',
			'json' => true,
			'order' => 'DESC'
		);
		$_args = array_merge( $defaults, $args );

		$default_range = "-5 days";
		$default_data_points = 48;
		$has_results = false;

		// Fetch in timeframe
		$query = $wpdb->prepare( 
			"SELECT * FROM {$_args['table']} 
			WHERE `buoy_id` = %d",
			$_args['id']
		);

		// No range set
		if( $_args['start'] == 0 && $_args['end'] == 0 ) {
			// // Grab last 2 days results
			$query = $wpdb->prepare( 
				$query . " AND `timestamp` > %d",
				date( 'U', strtotime( $default_range ) )
			); 
		}
		else {
			// Range set
			if( $_args['start'] != 0 ) {
				$query = $wpdb->prepare( 
					$query . " AND `timestamp` > %d",
					$_args['start']
				); 
			}

			if( $_args['end'] != 0 ) {
				$query = $wpdb->prepare( 
					$query . " AND `timestamp` < %d",
					$_args['end']
				); 
			}
		}

		// Order
		$query .= " ORDER BY `timestamp` " . $_args['order'];

		$data = $wpdb->get_results( $query, 'ARRAY_A' );

		// No results in that time range?
		if( $wpdb->num_rows == 0 && $_args['start'] == 0 && $_args['end'] == 0 ) {
			// Most recent timestamp
			$recent = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT `timestamp` FROM {$_args['table']} 
					WHERE `buoy_id` = %d
					ORDER BY `timestamp` DESC
					LIMIT 1", 
					$_args['id']
				)
			);

			if( $recent ) {
				// Two days before most recent
				$data = $wpdb->get_results(
					$wpdb->prepare( 
						"SELECT * FROM {$_args['table']} 
						WHERE `buoy_id` = %d
						AND `timestamp` > %d
						ORDER BY `timestamp` " . $_args['order'],
						$_args['id'], date( 'U', strtotime( $default_range, $recent ) )
					),
					'ARRAY_A'
				);
			}
		}
		
		
		if( !empty( $data ) ) {
			$has_results = true;
		}
		
		if( $_args['json'] ) {
			return json_encode(
				array(
					'success' => intval( $has_results ),
					'buoy_id' => $_args['id'],
					'data' => $data
				)
			);
		}
		return array(
			'success' => intval( $has_results ),
			'buoy_id' => $_args['id'],
			'data' => $data
		);
	}
	
	function waf_rest_list_buoy_datapoints_ajax( ) {
		global $wpdb;
		
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

		// Check for cached data
		$datapoints = wp_cache_get( "list_buoy_datapoints_{$id}_{$start}_{$end}_waf_wave_data", 'waf_rest' );
		
		if( $datapoints === false ) {
			// Fetch fresh
			$datapoints = waf_rest_list_buoy_datapoints( array( 'id' => $id, 'start' => $start, 'end' => $end, 'table' => $wpdb->prefix . 'waf_wave_data', 'order' => 'DESC' ) );
			// Cache results for 5 minutes
			wp_cache_set( "list_buoy_datapoints_{$id}_{$start}_{$end}_waf_wave_data", $datapoints, 'waf_rest', 300 );
		}
		print $datapoints;

		wp_die();
	}

	add_action( 'wp_ajax_waf_rest_list_buoy_datapoints', 'waf_rest_list_buoy_datapoints_ajax' );
	add_action( 'wp_ajax_nopriv_waf_rest_list_buoy_datapoints', 'waf_rest_list_buoy_datapoints_ajax' );

	// Output Buoy Datapoints as CSV
	function waf_rest_list_buoy_datapoints_csv_ajax( ) {
		global $wpdb; 

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

		// Check for cached data
		$datapoints = wp_cache_get( "list_buoy_datapoints_{$id}_{$start}_{$end}_waf_wave_data_csv", 'waf_rest' );
		
		if( $datapoints === false ) {
			// No cache fetch fresh
			$data_points = waf_rest_list_buoy_datapoints( array( 'id' => $id, 'start' => $start, 'end' => $end, 'table' => $wpdb->prefix . 'waf_wave_data', 'json' => false, 'order' => 'ASC' ) );

			// Cache results for 5 minutes
			wp_cache_set( "list_buoy_datapoints_{$id}_{$start}_{$end}_waf_wave_data_csv", $datapoints, 'waf_rest', 300 );
		}
		
		if( isset( $data_points['data'] ) ) {
			$csv_rows = [];
			foreach( $data_points['data'] as $data ) {
				$csv_rows[] = json_decode( $data['data_points'], true );
			}
			
			if( sizeof( $csv_rows ) > 0 ) {
				$csv_headers = array_keys( $csv_rows[0] );
				// Load the CSV
				$csv = Writer::createFromString();
				// Add headers
				$csv->insertOne( $csv_headers );
				$csv->insertAll( $csv_rows );

				// Output as CSV file
				$filename = "buoy-" . $id . "-wave-data" . ( ( $start > 0 ) ? "-" . $start : "" ) . ( ( $end > 0 ) ? "-" . $end : "" ) . ".csv";
				header( "Content-type: text/csv" );
				header( "Content-Disposition: attachment; filename=" . $filename );
				header( "Pragma: no-cache" );
				header( "Expires: 0" );
				print $csv->getContent();
			}
		}

		wp_die();
	}

	add_action( 'wp_ajax_waf_rest_list_buoy_datapoints_csv', 'waf_rest_list_buoy_datapoints_csv_ajax' );
	add_action( 'wp_ajax_nopriv_waf_rest_list_buoy_datapoints_csv', 'waf_rest_list_buoy_datapoints_csv_ajax' );

	// List Drifting Buoys 
	function waf_rest_list_buoys_drifting( $id = 0, $limit = 480 ) {
		global $wpdb;

		// All buoys
		$query = "SELECT *
		FROM {$wpdb->prefix}waf_buoys
		WHERE `drifting` = 1";
		// Specific buoy
		if( $id != 0 ) {
			$query = $wpdb->prepare(
				$query . " AND `id` = %d",
				$id
			);
		}
		// Order by Menu Order
		$query = $query . " ORDER BY `menu_order`";
		// Get buoys
		$buoys = $wpdb->get_results( $query, ARRAY_A );
		
		foreach( $buoys as $k => $buoy ) {
			
			// Get Drifting Data
			$drift_data = $wpdb->get_results(
				$wpdb->prepare( 
					"SELECT `data_points`, MIN(timestamp) AS min_timestamp FROM `{$wpdb->prefix}waf_wave_data`
					WHERE `buoy_id` = %d
					GROUP BY DATE(FROM_UNIXTIME(timestamp)), HOUR(FROM_UNIXTIME(timestamp))
					ORDER BY `timestamp` DESC
					LIMIT %d", $buoy['id'], $limit
				), ARRAY_A
			);

			if( $wpdb->num_rows > 0 ) {
				// array_walk( $drift_data, function( &$row, $key ) {
				// 	$row = $row['data_points'];
				// } );
				
				$buoys[$k]['data'] = $drift_data;
			}
		}
		
		// // Return JSON
		return json_encode( $buoys );
	}

	function waf_rest_list_buoys_drifting_ajax() {
		$id = 0;
		if( isset( $_REQUEST['id'] ) ) {
			$id = intval( $_REQUEST['id'] ); 
		}

		// Check for cached data
		$buoy = wp_cache_get( "list_buoys_drifting_{$id}", 'waf_rest' );

		if( $buoy === false ) {
			// Fetch fresh data
			$buoy = waf_rest_list_buoys_drifting( $id );
			
			// Cache results for 5 minutes
			wp_cache_set( "list_buoys_drifting_{$id}", $buoy, 'waf_rest', 300 );
		}

		print $buoy;
		wp_die();
	}

	add_action( 'wp_ajax_waf_rest_list_buoys_drifting', 'waf_rest_list_buoys_drifting_ajax' );
	add_action( 'wp_ajax_nopriv_waf_rest_list_buoys_drifting', 'waf_rest_list_buoys_drifting_ajax' );

	function waf_rest_list_buoys_memplots_ajax( ) {
		global $wpdb;
		
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

		// Check for cached version 
		$datapoints = wp_cache_get( "list_buoys_memplots_{$id}_{$start}_{$end}", 'waf_rest' );
		
		if( $datapoints === false ) {
			// Fetch fresh data
			$datapoints = waf_rest_list_buoy_datapoints( 
				array( 
					'id' => $id, 
					'start' => $start, 
					'end' => $end, 
					'table' => $wpdb->prefix . 'waf_wave_memplots',
					'order' => 'DESC'
				) 
			);
			// Cache results for 5 minutes
			wp_cache_set( "list_buoys_memplots_{$id}_{$start}_{$end}", $datapoints, 'waf_rest', 300 );
		}

		print $datapoints;

		wp_die();
	}

	add_action( 'wp_ajax_waf_rest_list_buoys_memplots', 'waf_rest_list_buoys_memplots_ajax' );
	add_action( 'wp_ajax_nopriv_waf_rest_list_buoys_memplots', 'waf_rest_list_buoys_memplots_ajax' );

	// Spotter AJAX
	function waf_spotter_fetch_devices_ajax() {
		waf_spotter_fetch_devices();

		wp_die();
	}

	add_action( 'wp_ajax_waf_spotter_fetch_devices', 'waf_spotter_fetch_devices_ajax' );
	add_action( 'wp_ajax_nopriv_waf_spotter_fetch_devices', 'waf_spotter_fetch_devices_ajax' );

	function waf_spotter_needs_update_ajax() {
		waf_spotter_needs_update();

		wp_die();
	}
	add_action( 'wp_ajax_waf_spotter_needs_update', 'waf_spotter_needs_update_ajax' );
	add_action( 'wp_ajax_nopriv_waf_spotter_needs_update', 'waf_spotter_needs_update_ajax' );

	function waf_spotter_fetch_updates_ajax() {
		waf_spotter_fetch_updates();

		wp_die();
	}
	add_action( 'wp_ajax_waf_spotter_fetch_updates', 'waf_spotter_fetch_updates_ajax' );
	add_action( 'wp_ajax_nopriv_waf_spotter_fetch_updates', 'waf_spotter_fetch_updates_ajax' );
	

	// Local AJAX

	function waf_local_fetch_updates_ajax() {
		waf_local_fetch_updates();

		wp_die();
	}
	add_action( 'wp_ajax_waf_local_fetch_updates', 'waf_local_fetch_updates_ajax' );
	add_action( 'wp_ajax_nopriv_waf_local_fetch_updates', 'waf_local_fetch_updates_ajax' );