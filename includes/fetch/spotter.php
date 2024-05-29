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


		$keys = $waf_spotter['key'];
		if( !empty( $keys ) ) {
			// Explode keys
			$keys = str_replace(" ", "", $keys);
			$keys_array = explode(",", $keys);
			if( count( $keys_array ) > 0 ) {
				// Loop through keys and fetch buoys
				foreach( $keys_array as $key ) {
					// CURL All Devices
					$response = waf_spotter_curl_request( array( 
						"url" => "https://api.sofarocean.com/api/devices",
						"token" => $key
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
							preg_match( '/(?:SPOT-)([0-9]*)/', $spotterId, $match ); // Capture only the number
							if( sizeof( $match ) == 2 ) {
								// Push ID as int to array
								$buoys[ intval( $match[1] ) ] = array(
									'spotterId' => $spotterId,
									'name' => !empty( $name ) ? $name : '...'
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

							// Add new buoys to database
							foreach( $diff as $id => $buoy ) {
								$wpdb->insert(
									$wpdb->prefix . "waf_buoys",
									array(
										'id' => $id,
										'label' => $buoy['spotterId'],
										'is_enabled' => 1,
										'start_date' => 0,
										'end_date' => 0,
										'first_update' => 0,
										'last_update' => 0,
										'menu_order' => 0,
										'start_after_id' => 0,
										'web_display_name' => $buoy['name'],
										'description' => "Spotter Buoy",
										'type' => 1,
										'api_key' => $key
									),
									array( '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
								);
							}

							// Update existing buoys to include API key
							foreach( $exist as $id => $buoy ) {
								$wpdb->update( 
									$wpdb->prefix . "waf_buoys",
									array( 'api_key' => $key ),
									array( 'id' => $id ),
									array( '%s' )
								);
							}
						}
					}
				}
			}
			else {
				print 'Spotter keys are empty';
				die();
			}
		}
		else {
			print 'No spotter keys set';
			die();
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
		$buoys = $wpdb->get_results( 
			"SELECT * FROM
			`{$wpdb->prefix}waf_buoys`
			WHERE `type` = 1
			AND `api_key` != ''" 
		);
		// Check most recent for each
		if( $buoys ) {
			foreach( $buoys as $buoy ) {
				// $spotterId = sprintf( "SPOT-%04d", $buoy->id ) ;
				$spotterId = $buoy->label;
				
				// CURL Latest data
				$response = waf_spotter_curl_request( array( 
					"url" => "https://api.sofarocean.com/api/latest-data?spotterId=" . $spotterId,
					"token" => $buoy->api_key
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
		$limit = 100;

		// Fetch all buoys requiring an update
		$buoys = $wpdb->get_results( 
			"SELECT * FROM `{$wpdb->prefix}waf_buoys` 
			WHERE `requires_update` = 1 
			AND `type` = 1
			AND `api_key` != ''" 
		);

		// Debug
		// error_log( "Buoys requiring updates found " . $wpdb->num_rows, 0 );

		if( $buoys ) {
			foreach( $buoys as $buoy ) {
				// $spotterId = sprintf( "SPOT-%04d", $buoy->id ) ;
				$spotterId = $buoy->label;

				// Fetch last update from buoys list
				// $last_update = waf_spotter_epoch_to_ISO8601( $buoy->last_update );

				// Fetch last buoy update from wave_data
				$last_update_timestamp = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT `timestamp` FROM `{$wpdb->prefix}waf_wave_data` 
						WHERE `buoy_id` = %d 
						ORDER BY `timestamp` 
						DESC LIMIT 1",
						$buoy->id
					)
				);

				// Check for null values for new buoys
				if( $last_update_timestamp == null ) {
					$last_update_timestamp = 0;
					$last_update_timestamp_end = time();
				}
				else {
					$last_update_timestamp_end = strtotime( "+5 days", $last_update_timestamp );
				}
				
				$params = array(
					'spotterId=' . $spotterId,
					// 'limit=' . $limit,
					'startDate=' . waf_spotter_epoch_to_ISO8601( $last_update_timestamp ), 
					'endDate=' . waf_spotter_epoch_to_ISO8601( $last_update_timestamp_end ), 
					'includeWindData=true',
					'includeSurfaceTempData=true',
					'includeFrequencyData=false',
					'includeDirectionalMoments=true',
					'includePartitionData=true',
					'includeBarometerData=true'
				);
				
				// CURL Latest data
				$response = waf_spotter_curl_request( array( 
					"url" => "https://api.sofarocean.com/api/wave-data?" . implode( '&', $params ),
					"token" => $buoy->api_key
				) );

				// Debug 
				// error_log( "Response for " . $spotterId . ": Length " . strlen( $response ), 0 );
				
				$json_res = json_decode( $response );

				$data = [];
				
				$checks = array(
					'wind' => $json_res->data->wind,
					'surfaceTemp' => $json_res->data->surfaceTemp,
					'waves' => $json_res->data->waves,
					'barometerData' => $json_res->data->barometerData,
					'partitionData' => $json_res->data->partitionData
				);

				// Set final update to previous last update
				// This was set to '0' thinking it'd always be replaced but wouldn't
				// when there were no updates avaiable.
				$final_update = $last_update_timestamp;

				// Time

				// Group all data by timestamp
				foreach( $checks as $check_key => $check ) {
					if( $check ) {
						foreach( $check as $data_point ) {
							$key = waf_spotter_ISO8601_to_epoch( $data_point->timestamp );
							
							// Store final update for compare for new records later
							$final_update = ( $key > $final_update ) ? $key : $final_update;
							
							// Setup basic buoy info if not already set
							if( !isset( $data[$key] ) ) {
								$data[$key] = array( 
									'Time (UNIX/UTC)' => intval( $key ),
									'BuoyID' => $buoy->label,
									'Site' => $buoy->label,
									'buoy_id' => $buoy->id,
									'key' => $data_point->timestamp
								);
							}

							// Partitioned data is structured differently
							// print $check_key . "\n";
							if( $check_key == "partitionData") {
								$partitions_grouped = [];
								// For each of the two partition data sets 
								
								foreach( $data_point->partitions as $partition_item => $partition ) {
									// Extract object properties as array and merge into group value
									$sep = get_object_vars( $partition );
									foreach( $sep as $k => $v ) {
										$partitions_grouped['partition_' . $partition_item . '_' . $k] = $v;
									}
								}
								
								$data[$key] = array_merge( $data[$key], $partitions_grouped );
							}
							else {
								$data[$key] = array_merge( $data[$key], get_object_vars( $data_point ) );
							}
						}
					}
				}

				// Convert keys to system format
				$local_format = waf_spotter_to_waf_keys( $data );

				// Debug
				// error_log( "Local format size: " . sizeof( $local_format ), 0 );

				// Inset into DB
				foreach( $local_format as $timestamp => $entry ) {
					// Check data doesn't already exist
					$wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}waf_wave_data
							WHERE `buoy_id` = %d
							AND `timestamp` = %d",
							$buoy->id,
							$timestamp
						)
					);

					if( $wpdb->num_rows == 0 ) {
						// Listing already exists
						$wpdb->insert(
							$wpdb->prefix . "waf_wave_data",
							array(
								'buoy_id' => $buoy->id,
								'data_points' => json_encode( $entry, JSON_UNESCAPED_SLASHES ),
								'timestamp' => $timestamp
							),
							array( '%d', '%s', '%d' )
						);
					}
					else {
						error_log( "Record already exists for buoy (" . $buoy->id . ') at ' . $timestamp, 0 );
					}


					// Debug 
					// error_log( "Insert ID: " . $wpdb->insert_id, 0 );
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
		
		// Check for failure
		if( curl_error( $request ) ) {
			$error_message = "cURL error: " . curl_error( $request );
			error_log( $error_message, 0 );
			curl_close( $request );
			return false;
		}
		// Close cURL
		curl_close( $request );
		// Return response
		return $response;
	}

	function waf_spotter_ISO8601_to_epoch( $time ) {
		return date( "U", strtotime( $time ) );
	}

	function waf_spotter_epoch_to_ISO8601( $time ) {	
		return date( 'Ymd\THis\Z', $time );
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
			'partition_0_significantWaveHeight' => 'Hsig_swell (m)', 
			'partition_1_significantWaveHeight' => 'Hsig_sea (m)',
			'value' => 'Pressure (hPa)'
		);

		$defaults = array(
			"Time (UNIX/UTC)" => 0,
			"Timestamp (UTC)" => "",
			"Site" => "",
			"BuoyID" => "",
			"Hsig (m)" => -9999,
			"Tp (s)" => -9999,
			"Tm (s)" => -9999,
			"Dp (deg)" => -9999,
			"DpSpr (deg)" => -9999,
			"Dm (deg)" => -9999,
			"DmSpr (deg)" => -9999,
			"QF_waves" => 1,
			// "SST (degC)" => 0,
			// "QF_sst" => 1,
			// "Bottom Temp (degC)" => 0,
			// "QF_bott_temp" => 1,
			"WindSpeed (m/s)" => -9999,
			"WindDirec (deg)" => -9999,
			"CurrmentMag (m/s)" => -9999,
			"CurrentDir (deg)" => -9999,
			"Latitude (deg)" => 0,
			"Longitude (deg) " => 0,
			"buoy_id" => 0
		);

		foreach( $data_array as $key_array => $data ) {
			foreach( $data as $key => $value ) {
				if( key_exists( $key, $conversions ) ) {
					$data_array[$key_array][$conversions[$key]] = $value;
					unset( $data_array[$key_array][$key] );
				}
				if( $key == 'degrees' ) {
					$data_array[$key_array]['QF_sst'] = 1;
				}
			}

			$data_array[$key_array] = array_merge( $defaults, $data_array[$key_array] );
		}

		return $data_array;
	}