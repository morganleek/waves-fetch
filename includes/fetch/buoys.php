<?php
	use League\Csv\Reader;

	// Fetch single buoy file list
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

	// Fetch single buoy file list with filter pattern (better)
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
						return waf_fetch_file_list_after( $_args );
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

	// Fetch buoys core CSV
	function waf_fetch_buoys_csv() {
		// Options
		$waf_s3 = get_option('waf_s3');
		if( empty( $waf_s3 ) ) {
			// Not configured
			return 0;
		}

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
					'drifting' => $r['drifting'],
					'download_text' => ( isset( $r['download_text' ] ) ) ? $r['download_text'] : '',
					'description' => ( isset( $r['description' ] ) ) ? $r['description'] : '',
					'image' => ( isset( $r['image' ] ) ) ? $waf_s3['buoy_root'] . '/' . $r['label'] . '/' . $r['image'] : ''
				);

				waf_update_buoy( $buoy );
			}

			// Remove buoys that no longer exist
			waf_trim_buoys( $ids );

			return 1;
		}

		return 0;
	}

	// Remove buoys no longer in CSV
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

	// Update/insert buoy
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
				array( 'id', 'label', 'type', 'is_enabled', 'menu_order', 'data', 'start_date', 'end_date', 'first_update', 'last_update', 'lat', 'lng', 'drifting', 'download_text',	'description', 'image' ),
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
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
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
					array( '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ),
					array( '%d' )
				);
			}
			return 1;
		}
		return 0;
	}