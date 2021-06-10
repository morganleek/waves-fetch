<?php
	use League\Csv\Reader;
	
	// Loop through buoys with requires_update for wave files and call 
	function waf_update_flagged_buoys() {
		global $wpdb;
		
		$need_updating = $wpdb->get_results("
			SELECT * FROM {$wpdb->prefix}waf_buoys
			WHERE `requires_update` = 1
			AND `end_date` = 0
		");

		$updated = array();

		if( $wpdb->num_rows ) {
			foreach( $need_updating as $buoy ) {
				// Fetch file list
				waf_fetch_file_list( $buoy->id );
				$updated[] = $buoy->id;
			}

			// Set update status to done in one query
			$updated_ids = join(', ', $updated);
			$wpdb->query("
				UPDATE {$wpdb->prefix}waf_buoys
				SET `requires_update` = 0
				WHERE `id` IN (' . $updated_ids . ')
			");
		}
	}

	// Fetch specific wave file by $id or next file needing update
	function waf_fetch_wave_file( $id = 0 ) {
		global $wpdb;
		
		// Options
		$waf_s3 = get_option('waf_s3');
		if( empty( $waf_s3 ) ) {
			// Not configured
			return 0;
		}

		// If $id = 0 fetch next file requiring update
		if( $id == 0 ) {
			$next_file = $wpdb->get_row(
				"SELECT * FROM {$wpdb->prefix}waf_wave_files
				WHERE `requires_update` = 1
				LIMIT 1"
			);

			if( $wpdb->num_rows > 0 ) {
				$id = $next_file->id;
			}
		}

		if( $id !== 0 ) {
			$buoy_file = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT f.`file_date_name`, f.`buoy_id`, b.`label`
					FROM wp_waf_wave_files AS f, wp_waf_buoys AS b 
					WHERE f.`buoy_id` = b.`id` AND f.`id` = %d",
					$id
				)
			);

			if( $wpdb->num_rows > 0 ) {
				// Buoy
				$buoy_id = $buoy_file->buoy_id;
				// Make into full path
				$object = waf_expand_date_path( $buoy_file->file_date_name, $buoy_file->label, $waf_s3['buoy_root'] );
				// Fetch CSV data
				$wave_data_csv = waf_fetch_resource( $object );

				// Heading leading spaces
				$wave_data_csv = str_replace( array(", ", " ," ), ",", $wave_data_csv );
				// Repeating headings 
				// requested this be removed from the Csvs
				$wave_data_csv = str_replace( "blank,blank,blank", "blank1,blank2,blank3", $wave_data_csv );
				
				// Convert CSV to Object
				try {
					$reader = Reader::createFromString( $wave_data_csv );
					$reader->setHeaderOffset(0);
					$records = $reader->getRecords();
				}
				catch( Exception $e ) {
					echo 'Caught exception: ' . $e->getMessage(), "\n";
				}
				
				// if( sizeof( $records ) > 0 ) {
				// Put values in array by timestamp
				$insert_rows = [];
				foreach( $records as $k => $r ) {
					$r['buoy_id'] = $buoy_id;
					$insert_rows[$r['Time (UNIX/UTC)']] = $r;
				}

				// Check which already exist and remove to allow post date adjustments
				$timestamps = join( ", ", array_keys( $insert_rows ) );
				$wpdb->get_results(
					$wpdb->prepare(
						"DELETE
						FROM `{$wpdb->prefix}waf_wave_data` 
						WHERE `buoy_id` = %d 
						AND `timestamp` IN (" . $timestamps . ")",
						$buoy_id
					)
				);

				// Removed this section that checked for existing records as the
				// preferred method is to remove them and rewrite.
				//
				// $existing = $wpdb->get_results(
				// 	$wpdb->prepare(
				// 		"SELECT *
				// 		FROM `{$wpdb->prefix}waf_wave_data` 
				// 		WHERE `buoy_id` = %d 
				// 		AND `timestamp` IN (" . $timestamps . ")",
				// 		$buoy_id
				// 	)
				// );
				//
				// // Remove existing elements
				// foreach( $existing as $e ) {
				// 	unset($insert_rows[$e->timestamp]);
				// }

				if( sizeof( $insert_rows ) > 0 ) {
					// Format for single insert
					$values = "(" . implode( "), (", array_map(
						function( $v ) {
							return $v['buoy_id'] . ', \'' . json_encode( $v ) . '\', ' . $v['Time (UNIX/UTC)'];
						}, $insert_rows ) 
					) . ")";
					
					// Insert
					$insert_query = $wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}waf_wave_data " . 
						"(`buoy_id`, `data_points`, `timestamp`) " .
						"VALUES " . $values
					);
					$wpdb->query( $insert_query );
				}

				// Mark file as updated
				$wpdb->update(
					$wpdb->prefix . "waf_wave_files",
					array( 'requires_update' => 0 ),
					array( 'id' => $id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	// Return total buoy wave files needing downloading
	function waf_count_wave_file_requires_download() {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}waf_wave_files
			WHERE `requires_update` = 1"
		);

		return $count;
	}

	//
	// Fetch next available file
	function waf_fetch_wave_file_ajax() {
		waf_fetch_wave_file();
		wp_die();
	}

	// Fetch number of files needing fetching
	function waf_count_wave_file_requires_download_ajax() {
		print waf_count_wave_file_requires_download();
		wp_die();
	}

	// Fetch files for buoys Ajax
	function waf_update_flagged_buoys_ajax() {
		waf_update_flagged_buoys();
		wp_die();
	}

	// Fetch Buoy List Ajax
	function waf_fetch_buoys_csv_ajax() {
		waf_fetch_buoys_csv();
		wp_die();
	}

	// Force fetch
	function waf_fetch_file_list_ajax() {
		if( isset( $_REQUEST['id'] ) ) {
			$id = intval( $_REQUEST['id'] );
			waf_fetch_file_list( $id );
		}
	}

	// Action: waf_fetch_buoys_csv
	add_action( 'wp_ajax_waf_fetch_buoys_csv', 'waf_fetch_buoys_csv_ajax' );
	add_action( 'wp_ajax_nopriv_waf_fetch_buoys_csv', 'waf_fetch_buoys_csv_ajax' );

	// Action: waf_update_flagged_buoys
	add_action( 'wp_ajax_waf_update_flagged_buoys', 'waf_update_flagged_buoys_ajax' );
	add_action( 'wp_ajax_nopriv_waf_update_flagged_buoys', 'waf_update_flagged_buoys_ajax' );
	
	// Action: waf_count_wave_file_requires_download
	add_action( 'wp_ajax_waf_count_wave_file_requires_download', 'waf_count_wave_file_requires_download_ajax' );
	add_action( 'wp_ajax_nopriv_waf_count_wave_file_requires_download', 'waf_count_wave_file_requires_download_ajax' );

	// Action: waf_fetch_wave_file
	add_action( 'wp_ajax_waf_fetch_wave_file', 'waf_fetch_wave_file_ajax' );
	add_action( 'wp_ajax_nopriv_waf_fetch_wave_file', 'waf_fetch_wave_file_ajax' );

	// Action: waf_fetch_file_list
	add_action( 'wp_ajax_waf_fetch_file_list', 'waf_fetch_file_list_ajax' );
	add_action( 'wp_ajax_nopriv_waf_fetch_file_list', 'waf_fetch_file_list_ajax' );