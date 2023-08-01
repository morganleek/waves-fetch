<?php
	// use League\Csv\Reader;

	function waf_local_fetch_updates() {
		global $wpdb;
		$MAXFILES = 5; 

		// Options
		$waf_local_csv = get_option('waf_local_csv');
		if( empty( $waf_local_csv ) ) {
			// Not configured
			return;
		}

		if( !isset( $waf_local_csv['bouy_id'] ) ) {
			print 'No buoy ID set';
			return;
		}
		$buoy_id = $waf_local_csv['bouy_id'];
		
		// Open CSV
		if( isset( $waf_local_csv['path'] ) && file_exists( $waf_local_csv['path'] ) ) {
			// Import to DB
			if( $full_dir = scandir( $waf_local_csv['path'] ) ) {
				$csvs = array_filter( $full_dir, function( $li ) {
					return preg_match( '/\.csv$/', $li ) === 1;
				} );
				
				// Trim list
				$ls = array_slice( $csvs, 0, $MAXFILES + 2 ); // First files are '.' and '..' so +2 
				$json_values = [];
				foreach( $ls as $l ) {
					// File extention .csv
					if( preg_match( '/\.csv$/', $l ) === 1 ) {
						// Create UTC date from file name
						$date_matches = [];
						preg_match_all( '/\d.*?(?=[_|\.])/', $l, $date_matches );
						if( !empty( $date_matches ) ) {
							$year = substr( $date_matches[0][0], 0, 4 );
							$month = substr( $date_matches[0][0], 4, 2 );
							$day = substr( $date_matches[0][0], 6, 2 );
							$hour = substr( $date_matches[0][1], 0, 2 );
							$minutes = substr( $date_matches[0][1], 2, 2 );

							$time_str = "{$year}-{$month}-{$day}T{$hour}:{$minutes}:00+10:00";
							$u = strtotime( $time_str );

							// Read CSV into string
							$file = file_get_contents( $waf_local_csv['path'] . '/' . $l, true );
							if( $file ) {
								$file = str_replace( array( "\n", "\r", " " ), '', $file ); // Remove formatting spaces, new lines and carriage returns
								$exploded = explode( ',', $file ); // Explode into rows
								// 0: Hs, // Significant wave height
								// 1: Hmax // Maximum wave height
								// 2: Tz, // Zero-crossing average wave period
								// 3: Hm0 // Spectral significant wave height
								// 4: Tp // Peak period
								// 5: Tm01 // Spectral significant wave period
								// 6: 0p // Peak Wave Direction
								// 7: 0m // Mean Wave Direction
								// 8: SST // Sea surface temperature
								// 9: lerr // Wave error flag // Only 0 is good
								// 10: Battery Status // Battery status flag
								// 11: Lat
								// 12: Lng
								if( intval( $exploded[9] ) === 0 ) {
									
									$json_values[] = array(
										"Time (UNIX/UTC)" => $u, //
										"Timestamp (UTC)" => date('Y-m-d\TH:i:s.000Z', $u), 
										"Site" => "SPOT-" . $buoy_id, 
										"BuoyID" => "SPOT-" . $buoy_id, 
										"Hsig (m)" => floatval( $exploded[0] ), // Significant Wave Height (metres)
										"Hmax (m)" => floatval( $exploded[1] ), // Max Wave Height (m)
										"Tz (s)" => floatval( $exploded[2] ), // Zero-crossing avergage
										"Tp (s)" => floatval( $exploded[4] ), // Peak Wave Period (s)
										"Tm (s)" => -9999, // Mean Wave Period (s)
										"Dp (deg)" => floatval( $exploded[6] ), // Peak Wave Direction (deg)
										"DpSpr (deg)" => -9999, // Peak Wave Directional Spreading (deg)
										"Dm (deg)" => floatval( $exploded[7] ), // Mean Wave Direction (deg)
										"DmSpr (deg)" => -9999, // Mean Wave Directional Spreading (deg)
										"QF_waves" => intval( $exploded[9] ) === 0 ? 1 : 0, // Wave data quality
										"SST (degC)" => floatval( $exploded[8] ),
										"QF_sst" => intval( $exploded[9] ) === 0 ? 1 : 0,
										"WindSpeed (m/s)" => -9999, 
										"WindDirec (deg)" => -9999, 
										"CurrmentMag (m/s)" => -9999, // Current Mag (m/s) 
										"CurrentDir (deg)" => -9999, // Current Direction (deg)
										"Latitude (deg)" => floatval( $exploded[11] ), 
										"Longitude (deg) " => floatval( $exploded[12] ), 
										"buoy_id" => $buoy_id, 
										"seasurfaceId" => 1, 
										"processing_source" => "embedded", 
									);
								}
							}
	
	
							// // Insert
							// $insert_query = $wpdb->prepare(
							// 	"INSERT INTO {$wpdb->prefix}waf_wave_data " . 
							// 	"(`buoy_id`, `data_points`, `timestamp`) " .
							// 	"VALUES " . $values
							// );
							// $wpdb->query( $insert_query );
						}
						else {
							print 'File contains no date';
							return;
						}

					}
				}

				if( sizeof( $json_values ) > 0 ) {
					// Format for single insert
					$values = "(" . implode( "), (", 
						array_map(
							function( $v ) {
								return $v['buoy_id'] . ', \'' . json_encode( $v ) . '\', ' . $v['Time (UNIX/UTC)'];
							}, $json_values 
						) 
					) . ")";

					// Delete if it already exists
					foreach( $json_values as $j ) {
						$wpdb->query( 
							$wpdb->prepare( "DELETE FROM {$wpdb->prefix}waf_wave_data WHERE `buoy_id` = %d AND `timestamp` = %s",
								$j['buoy_id'],
								$j['Time (UNIX/UTC)']
							)
						);
					}

					// Insert into DB
					$insert_query = $wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}waf_wave_data " . 
						"(`buoy_id`, `data_points`, `timestamp`) " .
						"VALUES " . $values
					);
					$wpdb->query( $insert_query );
				}
				
				// Mark as read by changing extension 
				foreach( $ls as $l ) {
					// File extention .csv
					if( preg_match( '/\.csv$/', $l ) === 1 ) {
						$new_file_name = $waf_local_csv['path'] . '/' . str_replace( ".csv", ".csv.processed", $l );
						$file = $waf_local_csv['path'] . '/' . $l;
						rename( $file, $new_file_name );
					}
				}
			}

		}
		else {
			print 'No local path set';
			return;
		}
	}

	