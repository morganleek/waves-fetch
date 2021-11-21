<?php
	// Grab records directly from Spotter

	function waf_spotter_time_conversion( $time_string ) {
		return 0;
	}

	function waf_spotter_fetch_devices() {
		global $wpdb;

		// Options
		$waf_spotter = get_option('waf_spotter');
		if( empty( $waf_spotter ) ) {
			// Not configured
			return 0;
		}

		// CURL All Devices
		$response = waf_spotter_curl_request( array( 
			"url" => "https://api.sofarocean.com/api/devices",
			"token" => $waf_spotter['key']
		) );

		// Testing
		// $response = '{"message":"4 devices","data":{"devices":[{"name":"TestBuoy","spotterId":"SPOT-0001"},{"name":"SPOT0867-KI","spotterId":"SPOT-0867"},{"name":"SPOT0940-Semaphore","spotterId":"SPOT-0940"},{"name":"SPOT0943-Brighton","spotterId":"SPOT-0943"},{"name":"","spotterId":"SPOT-1019"}]}}';
		
		if( $response ) {	
			$json_res = json_decode( $response );
	
			// Result buoys
			$buoys = [];
	
			// Regex Spotter IDs for DB ID
			foreach( $json_res->data->devices as $device ) {
				// Data
				$name = $device->name;
				$spotterId = $device->spotterId;
				// Regex the ID
				$match = [];
				preg_match( '/(?:SPOT-)(.*)/', $spotterId, $match );
				if( sizeof( $match ) == 2 ) {
					// Push ID as int to array
					$buoys[ intval( $match[1] ) ] = array(
						'spotterId' => $spotterId,
						'name' => $name
					);
				}
			}
	
			if( !empty( $buoys ) ) {
				// Check which exist 
				$exist = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT `id` FROM `{$wpdb->prefix}waf_buoys`
						 WHERE `id` IN (" . implode(",", array_keys( $buoys ) ) . ")"
					)
				);
	
				// Flip Keys and Values
				$exist = array_flip( $exist );
				
				// Difference with Buoys
				$diff = array_diff_key( $buoys, $exist );
	
				// Add to Database
				foreach( $diff as $key => $buoy ) {
					
					$wpdb->insert(
						$wpdb->prefix . "waf_buoys",
						array(
							'id' => $key,
							'label' => $buoy['name'],
							'is_enabled' => 1,
							'start_date' => 0,
							'end_date' => 0,
							'first_update' => 0,
							'last_update' => 0,
							'menu_order' => 0,
							'start_after_id' => 0,
							'web_display_name' => $buoy['name'],
							'description' => "Spotter Buoy"
						),
						array( '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
					);
				}
			}
		}
	}

	function waf_spotter_needs_update( ) {
		global $wpdb;

		// Options
		$waf_spotter = get_option('waf_spotter');
		if( empty( $waf_spotter ) ) {
			// Not configured
			return 0;
		}

		// Fetch all buoys
		$buoys = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}waf_buoys`" );
		// Check most recent for each
		if( $buoys ) {
			foreach( $buoys as $buoy ) {
				$spotterId = sprintf( "SPOT-%04d", $buoy->id ) ;

				// CURL Latest data
				$response = waf_spotter_curl_request( array( 
					"url" => "https://api.sofarocean.com/api/latest-data?spotterId=" . $spotterId,
					"token" => $waf_spotter['key']
				) );
				
				// Testing
				// $response = '{"data":{"spotterId":"SPOT-0940","spotterName":"SPOT0940-Semaphore","payloadType":"waves","batteryVoltage":4.13,"batteryPower":-0.03,"solarVoltage":6.67,"humidity":30.4,"track":[{"latitude":-34.9532333,"longitude":138.5055333,"timestamp":"2021-11-17T22:27:01.000Z"},{"latitude":-34.9532333,"longitude":138.5055333,"timestamp":"2021-11-17T22:57:01.000Z"}],"waves":[{"significantWaveHeight":0.05,"peakPeriod":25.6,"meanPeriod":6.76,"peakDirection":292.687,"peakDirectionalSpread":75.611,"meanDirection":274.399,"meanDirectionalSpread":79.464,"timestamp":"2021-11-17T22:27:01.000Z","latitude":-34.95323,"longitude":138.50553},{"significantWaveHeight":0.03,"peakPeriod":7.3,"meanPeriod":5.14,"peakDirection":352.875,"peakDirectionalSpread":69.328,"meanDirection":180,"meanDirectionalSpread":80.83,"timestamp":"2021-11-17T22:57:01.000Z","latitude":-34.95323,"longitude":138.50553}],"frequencyData":[]}}';
				
				$json_res = json_decode( $response );

				// Check latest wave data
				if( !empty( $json_res->data->waves ) ) {
					$last = array_pop( $json_res->data->waves ); // Pop last entry
					$last_update = waf_spotter_ISO8601_to_epoch( $last->timestamp ); // date( "U", strtotime( $last->timestamp ) ); // ISO8601 Format to Unix Timestamp
					// ___( $last_update . ' > ' .  $buoy->last_update );
					$requires_update = ( $last_update > $buoy->last_update ) ? 1 : 0;
					// if( $last_update > $buoy->last_update ) {
					// Needs update
					$wpdb->update(
						$wpdb->prefix . "waf_buoys",
						array( "requires_update" => $requires_update ),
						array( "id" => $buoy->id ),
						array( "%d" ),
						array( "%d" )
					);
					// }
				}
			}
		}
	}

	function waf_spotter_fetch_updates() {
		global $wpdb;

		// Options
		$waf_spotter = get_option('waf_spotter');
		if( empty( $waf_spotter ) ) {
			// Not configured
			return 0;
		}

		// Fetch limit 
		$limit = 10;

		// Fetch all buoys requiring an update
		$buoys = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}waf_buoys` WHERE `requires_update` = 1" );

		if( $buoys ) {
			foreach( $buoys as $buoy ) {
				$spotterId = sprintf( "SPOT-%04d", $buoy->id ) ;

				$last_update = waf_spotter_epoch_to_ISO8601( $buoy->last_update );

				$params = array(
					'spotterId=' . $spotterId,
					'limit=' . $limit,
					'startDate=' . $last_update, 
					'includeWindData=true',
					'includeSurfaceTempData=true',
					'includeFrequencyData=false',
					'includeDirectionalMoments=true'
				);

				// CURL Latest data
				$response = waf_spotter_curl_request( array( 
					"url" => "https://api.sofarocean.com/api/wave-data?" . implode( '&', $params ),
					"token" => $waf_spotter['key']
				) );
				
				// $response = '{"data":{"spotterId":"SPOT-0867","limit":10,"waves":[{"significantWaveHeight":0.01,"peakPeriod":20.48,"meanPeriod":10.24,"peakDirection":7.765,"peakDirectionalSpread":79.25,"meanDirection":37.776,"meanDirectionalSpread":78.999,"timestamp":"2020-10-19T23:31:31.000Z","latitude":37.77335,"longitude":-122.38632},{"significantWaveHeight":0.06,"peakPeriod":5.12,"meanPeriod":5.48,"peakDirection":27.087,"peakDirectionalSpread":74.975,"meanDirection":180,"meanDirectionalSpread":79.064,"timestamp":"2020-10-20T00:01:31.000Z","latitude":37.77335,"longitude":-122.38632},{"significantWaveHeight":0.07,"peakPeriod":10.24,"meanPeriod":7.4,"peakDirection":42.29,"peakDirectionalSpread":70.922,"meanDirection":30.44,"meanDirectionalSpread":76.443,"timestamp":"2021-07-16T04:55:25.000Z","latitude":-34.95325,"longitude":138.50555},{"significantWaveHeight":0.04,"peakPeriod":10.24,"meanPeriod":5.96,"peakDirection":212.196,"peakDirectionalSpread":78.461,"meanDirection":86.186,"meanDirectionalSpread":79.222,"timestamp":"2021-07-16T05:25:25.000Z","latitude":-34.95325,"longitude":138.50553},{"significantWaveHeight":0.05,"peakPeriod":10.24,"meanPeriod":6.78,"peakDirection":11.768,"peakDirectionalSpread":80.052,"meanDirection":58.339,"meanDirectionalSpread":78.187,"timestamp":"2021-07-16T05:55:25.000Z","latitude":-34.95327,"longitude":138.50555},{"significantWaveHeight":0.05,"peakPeriod":6.82,"meanPeriod":6.52,"peakDirection":118.021,"peakDirectionalSpread":60.236,"meanDirection":98.945,"meanDirectionalSpread":76.576,"timestamp":"2021-07-16T06:25:25.000Z","latitude":-34.95325,"longitude":138.50555},{"significantWaveHeight":0.17,"peakPeriod":10.24,"meanPeriod":3.78,"peakDirection":234.486,"peakDirectionalSpread":54.644,"meanDirection":258.111,"meanDirectionalSpread":76.275,"timestamp":"2021-09-13T12:06:00.000Z","latitude":-34.85525,"longitude":138.3547},{"significantWaveHeight":0.36,"peakPeriod":11.36,"meanPeriod":5.78,"peakDirection":217.398,"peakDirectionalSpread":42.796,"meanDirection":217.595,"meanDirectionalSpread":69.572,"timestamp":"2021-09-13T12:36:00.000Z","latitude":-34.89295,"longitude":138.2922},{"significantWaveHeight":1.23,"peakPeriod":11.36,"meanPeriod":7.88,"peakDirection":260.386,"peakDirectionalSpread":23.884,"meanDirection":264.02,"meanDirectionalSpread":44.104,"timestamp":"2021-09-13T22:12:01.000Z","latitude":-35.51842,"longitude":137.04245},{"significantWaveHeight":1.56,"peakPeriod":11.36,"meanPeriod":8.38,"peakDirection":260.488,"peakDirectionalSpread":20.714,"meanDirection":263.091,"meanDirectionalSpread":38.692,"timestamp":"2021-09-13T22:42:01.000Z","latitude":-35.55008,"longitude":136.97073}],"surfaceTemp":[{"degrees":13.72,"latitude":-34.95325,"longitude":138.5055333,"timestamp":"2021-07-16T05:25:25.000Z"},{"degrees":11.26,"latitude":-34.95325,"longitude":138.50555,"timestamp":"2021-07-16T06:25:25.000Z"}],"wind":[{"speed":0,"direction":125,"seasurfaceId":1,"latitude":37.77335,"longitude":-122.3863167,"timestamp":"2020-10-19T23:31:31.000Z"},{"speed":0,"direction":74,"seasurfaceId":1,"latitude":37.77335,"longitude":-122.3863167,"timestamp":"2020-10-20T00:01:31.000Z"},{"speed":0,"direction":22,"seasurfaceId":1,"latitude":-34.95325,"longitude":138.50555,"timestamp":"2021-07-16T04:55:25.000Z"},{"speed":0,"direction":354,"seasurfaceId":1,"latitude":-34.95325,"longitude":138.5055333,"timestamp":"2021-07-16T05:25:25.000Z"},{"speed":0,"direction":22,"seasurfaceId":1,"latitude":-34.9532667,"longitude":138.50555,"timestamp":"2021-07-16T05:55:25.000Z"},{"speed":0,"direction":40,"seasurfaceId":1,"latitude":-34.95325,"longitude":138.50555,"timestamp":"2021-07-16T06:25:25.000Z"}]}}';
				$json_res = json_decode( $response );

				$data = [];

				$checks = array(
					$json_res->data->wind,
					$json_res->data->surfaceTemp,
					$json_res->data->waves
				);

				$final_update = 0;

				// Group all data by timestamp
				foreach( $checks as $check ) {
					if( $check ) {
						foreach( $check as $data_point ) {
							$key = waf_spotter_ISO8601_to_epoch( $data_point->timestamp );
							$final_update = ( $key > $final_update ) ? $key : $final_update;
							if( !isset( $data[$key] ) ) {
								$data[$key] = array( 
									'buoy_id' => $buoy->id,
									'Time (UNIX/UTC)' => $key,
									'BuoyID' => $buoy->label
								);
							}
							$data[$key] = array_merge( $data[$key], get_object_vars( $data_point ) );
						}
					}
				}
				
				// Convert keys to system format
				$local_format = waf_spotter_to_waf_keys( $data );

				// Inset into DB
				foreach( $local_format as $timestamp => $entry ) {
					$wpdb->insert(
						$wpdb->prefix . "waf_wave_data",
						array(
							'buoy_id' => $buoy->id,
							'data_points' => json_encode( $entry ),
							'timestamp' => $timestamp
						),
						array( '%d', '%s', '%d' )
					);
				}

				// Update Buoy's own info 
				$updates = array( "last_update" => $final_update );
				$updates_format = array( "%d");
				// Check if Lat/Lng set
				if( $buoy->lat == '' || $buoy->lng == '' ) {
					// Set with initial values if empty
					$init = array_pop( $data );
					$updates["lat"] = $init['latitude'];
					$updates_format[] = "%f";
					$updates["lng"] = $init['longitude'];
					$updates_format[] = "%f";
				}

				$wpdb->update(
					$wpdb->prefix . "waf_buoys",
					$updates,
					array( "id" => $buoy->id ),
					$updates_format,
					array( "%d" )
				);
			}
		}
	}


	function waf_spotter_curl_request( $args ) {
		$defaults = array(
			'url' => '',
			'token' => '',
			'params' => array()
		);

		$_args = array_merge( $defaults, $args );

		// Required attributes
		if( empty( $_args['url'] ) || empty( $_args['token'] ) ) {
			return false;
		}

		// CURL All Devices
		$request = curl_init();
		curl_setopt( $request, CURLOPT_URL, $_args['url'] );
		curl_setopt( $request, CURLOPT_HTTPHEADER, array( 'token: ' . $_args['token'] ) );
		curl_setopt( $request, CURLOPT_RETURNTRANSFER, true );
		
		$response = curl_exec ( $request );
		curl_close( $request );
		
		if( $errno = curl_errno( $request ) ) {
			$error_message = curl_strerror( $errno );
			print "cURL error ({$errno}):\n {$error_message}";
			return false;
		}
		else {
			return $response;
		}
	}

	function waf_spotter_ISO8601_to_epoch( $time ) {
		return date( "U", strtotime( $time ) );
	}

	function waf_spotter_epoch_to_ISO8601( $time ) {
		return date_format( date_create( '@'. $time ), 'c' );	
	}

	function waf_spotter_to_waf_keys( $data_array ) {
		$conversions = array(
			'timestamp' => 'Timestamp (UTC)',
			// '' => 'Site',
			// '' => 'BuoyID',
			'significantWaveHeight' => 'Hsig (m)',
			'peakPeriod' => 'Tp (s)',
			'meanPeriod' => 'Tm (s)',
			'peakDirection' => 'Dp (deg)',
			'peakDirectionalSpread' => 'DpSpr (deg)',
			'meanDirection' => 'Dm (deg)',
			'meanDirectionalSpread' => 'DmSpr (deg)',
			// '' => 'QF_waves',
			'degrees' => 'SST (degC)',
			// '' => 'QF_sst',
			// '' => 'Bottom Temp (degC)',
			// '' => 'QF_bott_temp',
			'speed' => 'WindSpeed (m/s)',
			'direction' => 'WindDirec (deg)',
			// '' => 'CurrmentMag (m/s)',
			// '' => 'CurrentDir (deg)',
			'latitude' => 'Latitude (deg)',
			'longitude' => 'Longitude (deg) ',
		);

		foreach( $data_array as $key_array => $data ) {
			foreach( $data as $key => $value ) {
				if( key_exists( $key, $conversions ) ) {
					$data_array[$key_array][$conversions[$key]] = $value;
					unset( $data_array[$key_array][$key] );
				}
			}
		}

		return $data_array;
	}