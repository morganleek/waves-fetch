<?php
	// Compsore AWS Library
	require_once WAF__PLUGIN_DIR . 'vendor/autoload.php';

	use League\Csv\Writer;

	// List Buoys
	function waf_rest_list_buoys( $id = 0 ) {
		global $wpdb;

		// All buoys
		$query = "SELECT *, UNIX_TIMESTAMP() AS `now` 
		FROM {$wpdb->prefix}waf_buoys
		WHERE `is_enabled` = 1";
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
						AND `timestamp` > %d",
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
			'buoy_id' => $id,
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

		print waf_rest_list_buoy_datapoints( array( 'id' => $id, 'start' => $start, 'end' => $end, 'table' => $wpdb->prefix . 'waf_wave_data' ) );
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

		$data_points = waf_rest_list_buoy_datapoints( array( 'id' => $id, 'start' => $start, 'end' => $end, 'table' => $wpdb->prefix . 'waf_wave_data', 'json' => false, 'order' => 'ASC' ) );
		
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
		print waf_rest_list_buoys_drifting( $id );
		wp_die();
	}

	add_action( 'wp_ajax_waf_rest_list_buoys_drifting', 'waf_rest_list_buoys_drifting_ajax' );
	add_action( 'wp_ajax_nopriv_waf_rest_list_buoys_drifting', 'waf_rest_list_buoys_drifting_ajax' );

	// function waf_rest_list_buoys_memplots( $id = 0, $start = 0, $end = 0, $json = true ) {
	// 	global $wpdb;
	// 	$default_range = "-5 days";
	// 	$default_data_points = 48;
	// 	$has_results = false;

	// 	// Fetch in timeframe
	// 	$query = $wpdb->prepare( 
	// 		"SELECT * FROM {$wpdb->prefix}waf_wave_memplots
	// 		WHERE `buoy_id` = %d",
	// 		$id
	// 	);

	// 	// No range set
	// 	if( $start == 0 && $end == 0 ) {
	// 		// // Grab last 2 days results
	// 		$query = $wpdb->prepare( 
	// 			$query . " AND `timestamp` > %d",
	// 			date( 'U', strtotime( $default_range ) )
	// 		); 
	// 		// Grab last 48 retults
	// 		// $query = $wpdb->prepare(
	// 		// 	$query . " ORDER BY `timestamp` DESC LIMIT %d",
	// 		// 	$default_data_points
	// 		// );
	// 	}
	// 	else {
	// 		// Range set
	// 		if( $start != 0 ) {
	// 			$query = $wpdb->prepare( 
	// 				$query . " AND `timestamp` > %d",
	// 				$start
	// 			); 
	// 		}

	// 		if( $end != 0 ) {
	// 			$query = $wpdb->prepare( 
	// 				$query . " AND `timestamp` < %d",
	// 				$end
	// 			); 
	// 		}
	// 	}

	// 	// Order
	// 	$query .= " ORDER BY `timestamp` DESC ";

	// 	$data = $wpdb->get_results( $query, 'ARRAY_A' );

	// 	// No results in that time range?
	// 	if( $wpdb->num_rows == 0 && $start == 0 && $end == 0 ) {
	// 		// Most recent timestamp
	// 		$recent = $wpdb->get_var(
	// 			$wpdb->prepare(
	// 				"SELECT `timestamp` FROM {$wpdb->prefix}waf_wave_memplots 
	// 				WHERE `buoy_id` = %d
	// 				ORDER BY `timestamp` DESC
	// 				LIMIT 1", 
	// 				$id
	// 			)
	// 		);

	// 		if( $recent ) {
	// 			// Two days before most recent
	// 			$data = $wpdb->get_results(
	// 				$wpdb->prepare( 
	// 					"SELECT * FROM {$wpdb->prefix}waf_wave_memplots 
	// 					WHERE `buoy_id` = %d
	// 					AND `timestamp` > %d",
	// 					$id, date( 'U', strtotime( $default_range, $recent ) )
	// 				),
	// 				'ARRAY_A'
	// 			);
	// 		}
	// 	}
		
	// 	if( !empty( $data ) ) {
	// 		$has_results = true;
	// 	}

	// 	// array_walk( $data, function( &$data, $key ) {
	// 	// 	// Convert serialized data to JSON
	// 	// 	$data['data_points'] = unserialize( $data['data_points'] );
	// 	// } );
		
	// 	if( $json ) {
	// 		return json_encode(
	// 			array(
	// 				'success' => intval( $has_results ),
	// 				'buoy_id' => $id,
	// 				'data' => $data
	// 			)
	// 		);
	// 	}
	// 	return array(
	// 		'success' => intval( $has_results ),
	// 		'buoy_id' => $id,
	// 		'data' => $data
	// 	);
	// }

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

		print waf_rest_list_buoy_datapoints( array( 'id' => $id, 'start' => $start, 'end' => $end, 'table' => $wpdb->prefix . 'waf_wave_memplots' ) );
		wp_die();
	}

	add_action( 'wp_ajax_waf_rest_list_buoys_memplots', 'waf_rest_list_buoys_memplots_ajax' );
	add_action( 'wp_ajax_nopriv_waf_rest_list_buoys_memplots', 'waf_rest_list_buoys_memplots_ajax' );