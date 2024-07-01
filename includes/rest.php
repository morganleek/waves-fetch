<?php 
	// Restful functions

	add_action( 'rest_api_init', function () {
		register_rest_route( 'waves/v1', '/cache', array(
			'methods' => 'GET', 
			'callback' => 'waf_cache_test',
			'args' => array()
		) );
	} );

	function waf_cache_test() {
		// Check if caching is working
		if( $cache_test = wp_cache_get( 'cache_test', 'waf', false ) ) {
			// Found
			$data = array( 'message' => $cache_test );
		} 
		else {
			// Wait 2 seconds 
			// set_time_limit( 2 );

			// Message to user
			$data = array( 'message' => 'Not found, saving cache' );

			// Set Cache, for 15 minutes
			wp_cache_set( 'cache_test', 'Cache set @ ' . date('Y-m-d H:i:s'), 'waf', 900 );
		}

		return new WP_REST_Response( $data, 200 );
	}

	add_action( 'rest_api_init', function () {
		// GET /buoys
		// register_rest_route( 'waves/v1', '/buoys', array(
		// 	'methods' => 'GET', 
		// 	'callback' => 'waf_buoys'
		// ) );
		// GET /buoys/<id>
		register_rest_route( 'waves/v1', '/buoys/(?P<id>\d+)', array(
			'methods' => 'GET', 
			'callback' => 'waf_buoy',
			'args' => array(
				'id' => array(
					'required' => false,
					'validate_callback' => function($param, $request, $key) {
						return is_numeric( $param );
					}
				)
			)
		) );
	} );

	function waf_buoys( WP_REST_Request $request ) {
		// $result = wp_cache_get( 'buoys', 'waf', false );
		// if( !$result ) {
		// 	$result = waf_rest_list_buoys( [], [] );

		// 	// Cache response 900s
		// 	wp_cache_set( 'buoys', $result, 'waf', 900 );
		// }
		$response = "no id";

		$id = $request->get_param( 'id' );
		if( $id ) {
			$response = "id: " . $id;
		}

		return new WP_REST_Response( $response, 200 );
	}

	function waf_buoy( WP_REST_Request $request ) {
		global $wpdb;

		// Check for ID
		$id = $request->get_param( 'id' );
		if( $id ) {
			$_args['id'] = $id;
		}
		else {
			return new WP_REST_Response( "No ID set", 400 );
		}

		// Get query params
		$query_params = $request->get_query_params();

		// Check for start and end dates
		$start = 0;
		if( isset( $query_params['start'] ) ) {
			$start = intval( $query_params['start'] );
		}
		$end = 0;
		if( isset( $query_params['end'] ) ) {
			$end = intval( $query_params['end'] );
		}

		// Range 
		$range = '5';
		if( isset( $query_params['range'] ) ) {
			$range = intval( $query_params['range'] );
		}

		// Check for cached data
		$datapoints = wp_cache_get( "buoy_{$id}__{$start}_{$end}", 'waf_rest' );
		
		if( $datapoints === false ) {
			// Fetch fresh
			$data = waf_rest_list_buoy_datapoints( array( 'id' => $id, 'start' => $start, 'end' => $end, 'table' => $wpdb->prefix . 'waf_wave_data', 'order' => 'DESC', 'range' => '-' . $range . ' days', 'json' => true ) );
			
			// Process datapoints to valid JSON data
			$data_decoded = json_decode( $data );

			// $data_decoded_data = $data_decoded['data'];
			array_walk( $data_decoded->data, function( &$row, $key ) { 
				$row = json_decode( $row->data_points );
			} );
			$datapoints = $data_decoded->data;
			
			// Cache results for 10 minutes
			wp_cache_set( "buoy_{$id}__{$start}_{$end}", $datapoints, 'waf_rest', 600 );
		}

		return new WP_REST_Response( array(
			'buoyId' => $_args['id'],
			'data' => $datapoints
		), 200 );		
	}

	