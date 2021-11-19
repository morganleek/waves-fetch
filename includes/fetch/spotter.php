<?php
	// Grab records directly from Spotter

	function waf_spotter_time_conversion( $time_string ) {
		return 0;
	}

	function waf_spotter_fetch_devices() {
		// Options
		$waf_spotter = get_option('waf_spotter');
		if( empty( $waf_spotter ) ) {
			// Not configured
			return 0;
		}


		// CURL All Devices
		// $request = curl_init();
		// curl_setopt( $request, CURLOPT_URL, "https://api.sofarocean.com/api/devices" );
		// curl_setopt( $request, CURLOPT_HTTPHEADER, array( 'token: ' . $waf_spotter['key'] ) );
		// curl_setopt( $request, CURLOPT_RETURNTRANSFER, true );
		
		// $response = curl_exec ( $request );
		// curl_close( $request );
		
		// if( $errno = curl_errno( $request ) ) {
		// 	$error_message = curl_strerror( $errno );
		// 	print "cURL error ({$errno}):\n {$error_message}";
		// }
		// else {
		// 	print $response;
		// }

		// Testing
		$response = '{"message":"4 devices","data":{"devices":[{"name":"SPOT0867-KI","spotterId":"SPOT-0867"},{"name":"SPOT0940-Semaphore","spotterId":"SPOT-0940"},{"name":"SPOT0943-Brighton","spotterId":"SPOT-0943"},{"name":"","spotterId":"SPOT-1019"}]}}';
		$json_res = json_decode( $response );
		// ___( $json_res );
		foreach( $json_res->data->devices as $device ) {
			// Data
			$name = $device->name;
			$spotterId = $device->spotterId;
			// Regex the ID
			$match = [];
			preg_match( '/(?:SPOT-)(.*)/', $spotterId, $match );
			if( sizeof( $match ) == 2 ) {
				// ID as int
				$id = intval( $match[1] );
				
				// Check Database for record

				// If record fetch from last_update

				// Else fetch from 2000-01-01
			}
		}
		
		// if( !empty( $json_res->data->devices ) ) {
			
			// foreach( $json_res->data->devices as $device ) {
			// 	$name = $device['name'];
			// 	$spotterId = $device['spotterId'];
			
			// }
			
		// }
		
		// Fetch all buoys


		// Convert buoy ids to DB ids
		// Check existance
		// Get first date with startDate 2000-01-01 and Spotter ID. Set needs update.
	}

	function waf_spotter_fetch_device( $id, $start_date ) {
		
		
		// Insert

		// Merge Data into single record

		// Push to DB

		// Update last_update
	}


	// $spotter = get_option('waf_spotter');