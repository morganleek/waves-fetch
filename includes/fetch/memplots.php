<?php 
	// Fetch S3 file lists of memplots and
	// insert in to wp_waf_wave_memplots
	function waf_fetch_wave_jpgs( $id = 0 ) {
		global $wpdb;
		// Fetch file from bucket
		$waf_s3 = get_option('waf_s3');

		if( 
			empty( $waf_s3['key'] ) 		|| 
			empty( $waf_s3['secret'] ) 	|| 
			empty( $waf_s3['region'] ) 	|| 
			empty( $waf_s3['bucket'] ) 
		) {
			// Not configured
			return 0;
		}

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

		// For each buoy fetch list
		foreach( $buoys as $buoy ) {
			// Get last file fetched
			$last_fetch = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}waf_wave_memplots
					WHERE `buoy_id` = %d
					ORDER BY `timestamp` DESC
					LIMIT 1"
					, $buoy->id
				)
			);
			// Leave empty if there is no result 
			$start_after = ( $wpdb->num_rows == 0 ) ? '' : $last_fetch->full_path;
			$prefix = $waf_s3['buoy_root'] . '/' . $buoy->label . '/' . 'mem_plot' . '/';

			$files = waf_fetch_file_list_after( array(
				'bucket' => $waf_s3['bucket'],
				'prefix' => $prefix, 
				'start_after' => $start_after, 
				'filter_pattern' => '.jpg'
			) );
			// If files exist add to database
			if( !empty( $files ) ) {
				// Move into easy to manage table with timestamp keys
				$insert_rows = [];
				foreach( $files as $file ) {
					$insert_rows[ waf_jpg_file_to_time( $file ) ] = array( 
						'full_path' => $file,
						'buoy_id' => $buoy->id,
						'timestamp' => waf_jpg_file_to_time( $file )
					);
				}

				$timestamps = join( ", ", array_keys( $insert_rows ) );
				$existing = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *
						FROM `{$wpdb->prefix}waf_wave_memplots` 
						WHERE `buoy_id` = %d 
						AND `timestamp` IN (" . $timestamps . ")",
						$buoy->id
					)
				);
				
				// Remove existing elements
				foreach( $existing as $e ) {
					unset($insert_rows[$e->timestamp]);
				}

				// Insert
				if( sizeof( $insert_rows ) > 0 ) {
					// Format for single insert
					$values = "(" . implode( "), (", array_map(
						function( $v ) {
							return $v['buoy_id'] . ', ' . $v['timestamp'] . ', \'' . $v['full_path'] . '\'';
						}, $insert_rows ) 
					) . ")";

					$insert_query = $wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}waf_wave_memplots " . 
						"(`buoy_id`, `timestamp`, `full_path`) " .
						"VALUES " . $values
					);
					$wpdb->query( $insert_query );
				}
			}
		}
	}

	// Ajax trigger
	function waf_fetch_wave_jpgs_ajax( ) {
		print waf_fetch_wave_jpgs( );
		wp_die();
	}

	// Action: waf_fetch_wave_jpgs
	add_action( 'wp_ajax_waf_fetch_wave_jpgs', 'waf_fetch_wave_jpgs_ajax' );
	add_action( 'wp_ajax_nopriv_waf_fetch_wave_jpgs', 'waf_fetch_wave_jpgs_ajax' );

	

	