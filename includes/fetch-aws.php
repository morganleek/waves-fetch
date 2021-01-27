<?php
	// Compsore AWS Library
	require_once WAF__PLUGIN_DIR . 'vendor/autoload.php';

	use League\Csv\Reader;
	use Aws\S3\Exception\S3Exception;
	use Aws\S3\S3Client;

	function waf_aws_object() {
		

		
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
				echo "There was an error reading the file '" . $object . "'\n";
			}
		}
	}

	function waf_fetch_list( $start_after = '', $limit = 100 ) {
		// Fetch file list from a point
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
			foreach( $records as $k => $r ) {
				$buoy = array(
					'id' => intval( $r['buoy_id'] ),
					'label' => $r['label'],
					'type' => $r['type'],
					'is_enabled' => intval( $r['enabled'] ),
					'menu_order' => intval( $r['order'] ),
					'data' => $r['data'],
					'start_date' => $r['start_date'],
					'end_date' => $r['end_date'],
					'first_update' => $r['first_updated'],
					'last_update' => $r['last_updated']
				);

				waf_update_buoy( $buoy );
			}

			return 1;
		}

		return 0;
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
				array( 'id', 'label', 'type', 'is_enabled', 'menu_order', 'data', 'start_date', 'end_date', 'first_update', 'last_update' ),
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
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d' )
				);
			}
			else {
				// Already exists

				// Pop ID
				$buoy_id = array_shift( $buoy );
				
				// Check if requires update
				$requires_update = ( $row->last_update < $buoy['last_update'] ) ? 1 : 0;
				$buoy += [ 'requires_update' => $requires_update ]; // Append

				// Update
				$wpdb->update(
					$wpdb->prefix . 'waf_buoys', 
					$buoy,
					array( 'id' => $buoy_id ),
					array( '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d' ),
					array( '%d' )
				);
			}
			return 1;
		}
		return 0;
	}

	function waf_fetch_file_list( $id = 0, $continuation_token = '' ) {
		global $wpdb;

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
				// Start After
				$start_after = $row->start_after;
				if( empty( $start_after ) ) {
					// If start_after is empty set as root directory
					$start_after = $waf_s3['buoy_root'] . '/' . $row->label . '/' . 'text_archive' . '/';
				}
				
				$items = array(
					'Bucket' => $waf_s3['bucket'],
					'MaxKeys' => 2, // 1000 // Max
					'Prefix' => $prefix,
					'StartAfter' => $start_after
				);

				if( !empty( $continuation_token ) ) {
					$items += ['ContinuationToken' => $continuation_token];
				}
				// else {
				// 	$items = array(
				// 		'Bucket' => $waf_s3['bucket'],
				// 		'MaxKeys' => 2, // 1000 // Max
						
				// 		'Prefix' => $prefix,
				// 		'StartAfter' => $start_after
				// 	);
				// }
				
				$files = [];

				if( $s3 = waf_create_s3_connection() ) {
					try {
						$objects = $s3->listObjectsV2($items);
						if(isset($objects['Contents'])) {
							foreach ($objects['Contents'] as $key => $object) {
								// $file_date = '';
								print $object['Key'];
								if( strpos( $object['Key'], '.csv' ) !== false ) {
									$file_date = substr( $object['Key'], strpos($object['Key'], $row->label . '_') + strlen( $row->label . '_' ), 8 );
									
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
										}
									}
								}
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

	//
	// Testing 
	function waf_fetch_buoys_next_ajax_test() {
		$id = 1;
		
		waf_fetch_file_list( $id );
		
		wp_die();
	}

	function waf_fetch_buoys_csv_ajax_test() {
		waf_fetch_buoys_csv();
		wp_die();
	}

	add_action( 'wp_ajax_waf_fetch_buoys_csv', 'waf_fetch_buoys_csv_ajax_test' );
	add_action( 'wp_ajax_nopriv_waf_fetch_buoys_csv', 'waf_fetch_buoys_csv_ajax_test' );

	add_action( 'wp_ajax_waf_fetch_buoys_next', 'waf_fetch_buoys_next_ajax_test' );
	add_action( 'wp_ajax_nopriv_waf_fetch_buoys_next', 'waf_fetch_buoys_next_ajax_test' );
	