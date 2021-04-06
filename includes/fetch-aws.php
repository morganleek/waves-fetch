<?php
	// Compsore AWS Library
	require_once WAF__PLUGIN_DIR . 'vendor/autoload.php';

	use League\Csv\Reader;
	use Aws\S3\Exception\S3Exception;
	use Aws\S3\S3Client;

	
	function waf_expand_date_path( $date = '', $label = '', $root = '' ) {
		// Format "wawaves/Torbay/text_archive/2021/01/Torbay_20210124.csv"
		if( !empty( $date ) && !empty( $label ) && !empty( $root ) ) {
			$year = substr( $date, 0, 4 ); // Year folder
			$month = substr( $date, 4, 2 ); // Month folder
			return $root . '/' . $label . '/' . 'text_archive' . '/' . $year . '/' . $month . '/' . $label . '_' . $date . '.csv';
		}
		return '';
	}

	function waf_collapse_date_path( $path = '', $label = '' ) {
		if( !empty( $path ) && !empty( $label ) ) {
			return substr( $path, strpos($path, $label . '_') + strlen( $label . '_' ), 8 );
		}
		return '';
	}

	function waf_create_s3_connection( ) {
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
		
		if( $waf_s3 ) {
			$s3 = new S3Client([
				'credentials' => array(
					'key' => $waf_s3['key'],
					'secret' => $waf_s3['secret'],
				),
				'version' => 'latest',
				'region'	=> $waf_s3['region']
			]);

			return $s3;
		}

		return false;
	}

	function waf_fetch_resource( $object = '' ) {
		$waf_s3 = get_option('waf_s3');
		
		if( empty( $object ) ) {
			if( empty( $waf_s3['buoy_csv'] ) ) {
				// No default file
				return false;
			}
			$object = $waf_s3['buoy_csv'];
		}

		if( $s3 = waf_create_s3_connection() ) {
			// Fetch Object
			try {
				$result = $s3->getObject([
					'Bucket' => $waf_s3['bucket'],
					'Key'		=> $object
				]);
				
				$body = $result['Body'];
				return $body;
			}
			catch ( S3Exception $e ) {
				print "There was an error reading the file '" . $object . "'\n";
				print $e->getResponse();
			}
		}
	}

	function waf_fetch_buoys_csv() {
		// Fetch from AWS
		// No parameters for default buoys csv
		$buoys_csv = waf_fetch_resource();
		
		if( $buoys_csv !== 0 ) {
			// Convert to CSV Object
			$reader = Reader::createFromString( $buoys_csv );
			$reader->setHeaderOffset(0);
			$records = $reader->getRecords();
			$ids = [];
			foreach( $records as $k => $r ) {
				$ids[] = intval( $r['buoy_id'] );
				$buoy = array(
					'id' => intval( $r['buoy_id'] ),
					'label' => $r['label'],
					'web_display_name' => $r['web_display_name'],
					'type' => $r['type'],
					'is_enabled' => intval( $r['enabled'] ),
					'menu_order' => intval( $r['order'] ),
					'data' => $r['data'],
					'start_date' => $r['start_date'],
					'end_date' => $r['end_date'],
					'first_update' => $r['first_updated'],
					'last_update' => $r['last_updated'],
					'lat' => $r['Latitude'],
					'lng' => $r['Longitude'],
					'drifting' => $r['drifting']
				);

				waf_update_buoy( $buoy );
			}

			// Remove buoys that no longer exist
			waf_trim_buoys( $ids );

			return 1;
		}

		return 0;
	}

	function waf_trim_buoys( $ids = array( ) ) {
		global $wpdb;

		if( !empty( $ids ) ) {
			// Delete all buoys not in CSV
			$wpdb->query(
				$wpdb->prepare(
					"DELETE
					FROM {$wpdb->prefix}waf_buoys
					WHERE `id` NOT IN (" . implode(',', $ids) . ")"
				)
			);
		}
	}

	function waf_update_buoy( $buoy = array( ) ) {
		global $wpdb;

		if( !empty( $buoy ) ) {
			// Check it exists
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}waf_buoys
					WHERE `id` = %d",
					$buoy['id']
				)
			);

			// Validate fields
			if( !array_keys_exists( 
				array( 'id', 'label', 'type', 'is_enabled', 'menu_order', 'data', 'start_date', 'end_date', 'first_update', 'last_update', 'lat', 'lng', 'drifting' ),
				$buoy
			) ) {
				return 0;
			}

			if( $wpdb->num_rows == 0 ) {
				// Doesn't exist

				// Set update required
				$buoy += [ 'requires_update' => 1 ]; // Append
				
				// Insert
				$wpdb->insert(
					$wpdb->prefix . 'waf_buoys', 
					$buoy,
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d' )
				);
			}
			else {
				// Already exists

				// Pop ID
				$buoy_id = array_shift( $buoy );
				
				// Check if isn't already needing an update and has changed
				$requires_update = ( $row->requires_update == 1 || ( $row->last_update < $buoy['last_update'] ) ) ? 1 : 0;
				$buoy += [ 'requires_update' => $requires_update ]; // Append

				// Update
				$wpdb->update(
					$wpdb->prefix . 'waf_buoys', 
					$buoy,
					array( 'id' => $buoy_id ),
					array( '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d' ),
					array( '%d' )
				);
			}
			return 1;
		}
		return 0;
	}

	function waf_fetch_file_list( $id = 0, $continuation_token = '' ) {
		global $wpdb;
		$MaxKeys = 1000; 

		// Options
		$waf_s3 = get_option('waf_s3');
		if( empty( $waf_s3 ) ) {
			// Not configured
			return 0;
		}
		
		if( $id !== 0 ) {
			// Buoy Exists 
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}waf_buoys
					WHERE `id` = %d", $id
				)
			);

			// Exists
			if( $wpdb->num_rows > 0 ) {
				// Prefix
				$prefix = $waf_s3['buoy_root'] . '/' . $row->label . '/' . 'text_archive' . '/';
				
				$items = array(
					'Bucket' => $waf_s3['bucket'],
					'MaxKeys' => $MaxKeys,
					'Prefix' => $prefix
				);
				
				// Start After
				if( $row->start_after_id !== 0 ) {
					$file_date_name = $wpdb->get_var(
						// Before last file
						$wpdb->prepare(
							"SELECT `file_date_name` 
							FROM {$wpdb->prefix}waf_wave_files 
							WHERE `id` < '%d'
							AND `buoy_id` = '%d'
							ORDER BY `id` DESC
							LIMIT 1",
							$row->start_after_id,
							$id
						)
					);

					if( $wpdb->num_rows > 0 ) {
						$start_after = waf_expand_date_path( $file_date_name, $row->label, $waf_s3['buoy_root'] );
						$items += ['StartAfter' => $start_after];
					}
				}
				
				// If continuing
				if( !empty( $continuation_token ) ) {
					$items += ['ContinuationToken' => $continuation_token];
				}

				if( $s3 = waf_create_s3_connection() ) {
					try {
						$objects = $s3->listObjectsV2($items);

						if(isset($objects['Contents'])) {
							$last_file_id = 0;
							foreach ($objects['Contents'] as $key => $object) {
								
								// $last_file = $object['Key'];
								if( strpos( $object['Key'], '.csv' ) !== false ) {
									// Condense date path for storage
									$file_date = waf_collapse_date_path( $object['Key'], $row->label );
									
									// Only CSV Files with Dates
									if( !empty( $file_date ) && $file_date !== 0 ) {
										$timestamp = $object['LastModified']->format('U');

										$file = $wpdb->get_row(
											$wpdb->prepare(
												"SELECT * FROM {$wpdb->prefix}waf_wave_files
												WHERE buoy_id = %d
												AND file_date_name = %d",
												$row->id,
												$file_date
											)
										);

										if( $wpdb->num_rows > 0 ) {
											// For S3 SearchAfter
											$last_file_id = $file->id;
											// Exists check timestamp
											if( $file->timestamp < intval( $timestamp ) ) {
												// Timestamp has increased
												// update and set required_update
												$wpdb->update(
													"{$wpdb->prefix}waf_wave_files",
													array(
														'timestamp' => $timestamp,
														'requires_update' => 1
													),
													array( 'id' => $file->id ),
													array( '%d', '%d' ),
													array( '%d' )
												);
											}
											// Do nothing if not updated
										}
										else {
											// Insert
											$wpdb->insert(
												"{$wpdb->prefix}waf_wave_files",
													array(
														'buoy_id' => $row->id,
														'file_date_name' => $file_date,
														'timestamp' => $timestamp,
														'requires_update' => 1
													),
													array( '%d', '%d' )
											);
											// For S3 SearchAfter
											$last_file_id = $wpdb->insert_id;
										}
									}
								}
							}

							if( $last_file_id !== 0 ) {
								// Update Buoy start_after
								$wpdb->update(
									"{$wpdb->prefix}waf_buoys",
									array( 
										'start_after_id' => $last_file_id,
										'requires_update' => 0
									),
									array( 'id' => $id ),
									array( '%d', '%d' ),
									'%d'
								);
							}

							if( $objects['NextContinuationToken'] ) {
								// Loop
								waf_fetch_file_list( $id, $objects['NextContinuationToken'] );
							}
						}
					} catch (S3Exception $e) {
						$html = $e->getMessage() . PHP_EOL;
					}
				}
			}
		}
	}

	function waf_fetch_file_list_after( $args = [] ) {
		// Default arguments
		$defaults = array(
			'bucket' => '',
			'prefix' => '', 
			'start_after' => '', 
			'continuation_token' => '', 
			'files' => array(),
			'max_keys' => 1000, 
			'filter_pattern' => ''
		);
		$_args = array_merge( $defaults, $args );

		// S3 object setup
		$items = array(
			'Bucket' => $_args['bucket'],
			'MaxKeys' => $_args['max_keys'],
			'Prefix' => $_args['prefix'],
			'StartAfter' => $_args['start_after'] // Just root path if no previous files
		);
		// If continuing
		if( !empty( $continuation_token ) ) {
			$items['ContinuationToken'] = $_args['continuation_token'];
		}
		// S3 Request
		if( $s3 = waf_create_s3_connection() ) {
			try {
				$objects = $s3->listObjectsV2( $items );

				if(isset($objects['Contents'])) {
					foreach ($objects['Contents'] as $key => $object) {
						// If filter applied filter by it
						if( empty( $_args['filter_pattern'] ) || strpos( $object['Key'], $_args['filter_pattern'] ) !== false ) {
							$_args['files'][] = $object['Key'];
						}
					}

					if( $objects['NextContinuationToken'] ) {
						// Update start_after and continuation_token
						$_args['start_after'] = $_args['files'][array_key_last($_args['files'])];
						$_args['continuation_token'] = $objects['NextContinuationToken'];
						// Fetch again
						waf_fetch_file_list_after( $_args );
					}
					else {
						return $_args['files'];
					}
				}
			} catch (S3Exception $e) {
				return $e->getMessage() . PHP_EOL;
			}
		}
	}

	function waf_update_flagged_buoys() {
		// Loop through buoys with requires_update and call 
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

	function waf_fetch_wave_file( $id = 0 ) {
		// Fetch specific file by $id or next file needing update
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

	function waf_fetch_wave_jpgs_ajax( ) {
		print waf_fetch_wave_jpgs( );
		die();
	}

	// Action: waf_fetch_wave_jpgs
	add_action( 'wp_ajax_waf_fetch_wave_jpgs', 'waf_fetch_wave_jpgs_ajax' );
	add_action( 'wp_ajax_nopriv_waf_fetch_wave_jpgs', 'waf_fetch_wave_jpgs_ajax' );

	function waf_jpg_file_to_time( $filename = '' ) {
		if( !empty( $filename ) ) {
			$pattern = "/MEMplot_(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})_(?<hour>\d{2})(?<minute>\d{2})UTC/";
			$matches = [];
			preg_match( $pattern, $filename, $matches );
			if( waf_array_keys_exists( array( 'year', 'month', 'day', 'hour', 'minute' ), $matches ) ) {
				return mktime( intval( $matches['hour'] ), intval( $matches['minute'] ), 0, intval( $matches['month'] ), intval( $matches['day'] ), intval( $matches['year'] ) );
			}
		}
		return false;
	}

	function waf_array_keys_exists(array $keys, array $arr) {
		return !array_diff_key(array_flip($keys), $arr);
	}