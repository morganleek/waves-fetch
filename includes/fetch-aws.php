<?php
	// Compsore AWS Library
	require_once WAF__PLUGIN_DIR . 'vendor/autoload.php';

	use League\Csv\Reader;
	use Aws\S3\Exception\S3Exception;
	use Aws\S3\S3Client;

	function waf_aws_object() {
		

		
	}

	function waf_fetch_resource( $object = '' ) {
		// WPDB
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
			return false;
		}
		
		if( $waf_s3 && isset( $waf_s3['region'] ) ) {
			// $s3 = new Aws\S3\S3Client( [
			// 	'version' => 'latest',
			// 	'region' => $waf_s3['region']
			// ] );

			// Set to default buoys list if no file path specified
			if( empty( $object ) ) {
				if( empty( $waf_s3['buoy_csv'] ) ) {
					// No default file
					return false;
				}
				$object = $waf_s3['buoy_csv'];
			}

			$s3 = new S3Client([
				'credentials' => array(
					'key' => $waf_s3['key'],
					'secret' => $waf_s3['secret'],
				),
				'version' => 'latest',
				'region'	=> $waf_s3['region']
			]);

			// try {
			// 	$result = $s3->getObjectTagging([
			// 		'Bucket' => $waf_s3['bucket'],
			// 		'Key'		=> $object
			// 	]);

			// 	// print_r($result);
			// }
			// catch ( S3Exception $e ) {
			// 	print 'Error Tagging';
			// }

			// echo file_get_contents('php://temp');

			try {
				$result = $s3->getObject([
					'Bucket' => $waf_s3['bucket'],
					'Key'		=> $object
				]);
				
				$body = $result['Body'];
				$reader = Reader::createFromString( $body );
				$reader->setHeaderOffset(0);
				$records = $reader->getRecords();
				foreach( $records as $k => $r ) {
					$row = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}waf_buoys
							WHERE `id` = %d",
							intval( $r['buoy_id'] )
						)
					);

					if( $wpdb->num_rows == 0 ) {
						// Insert
						$wpdb->insert(
							$wpdb->prefix . 'waf_buoys', 
							array(
								'id' => intval( $r['buoy_id'] ),
								'label' => $r['label'],
								'type' => $r['type'],
								'is_enabled' => intval( $r['enabled'] ),
								'menu_order' => intval( $r['order'] ),
								'data' => $r['data'],
								'start_date' => '1970-01-01 00:00:00',
								'end_date' => '1970-01-01 00:00:00',
								'first_update' => '1970-01-01 00:00:00',
								'last_update' => '1970-01-01 00:00:00'
							),
							array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
						);
					}
				}
			}
			catch ( S3Exception $e ) {
				echo "There was an error reading the file '" . $object . "'\n";
			}

			// echo file_get_contents('php://temp');
		}
	}

	function waf_fetch_file_list( $start_from = '', $limit = 100 ) {
		// Fetch file list from a point
	}

	function waf_fetch_buoys_csv() {
		// $s3 = get_option('waf_s3');
		// print json_encode($s3);
		
		waf_fetch_resource();
	}

	//
	// Testing 
	function waf_fetch_buoys_csv_ajax_test() {
		waf_fetch_buoys_csv();
		wp_die();
	}

	add_action( 'wp_ajax_waf_fetch_buoys_csv', 'waf_fetch_buoys_csv_ajax_test' );
	add_action( 'wp_ajax_nopriv_waf_fetch_buoys_csv', 'waf_fetch_buoys_csv_ajax_test' );