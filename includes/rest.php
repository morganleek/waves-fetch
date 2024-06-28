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
		register_rest_route( 'waves/v1', '/buoys', array(
			'methods' => 'GET', 
			'callback' => 'waf_buoys'
		) );
		// GET /buoys/<id>
		register_rest_route( 'waves/v1', '/buoys/(?P<id>\d+)', array(
			'methods' => 'GET', 
			'callback' => 'waf_buoys',
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

	