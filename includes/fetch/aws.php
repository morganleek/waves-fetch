<?php
	use Aws\S3\Exception\S3Exception;
	use Aws\S3\S3Client;

	// Create S3 Connection
	// Returns S3Client object
	function waf_create_s3_connection( ) {
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

	// Fetch S3 object using key
	// Returns object body
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

	function waf_fetch_cache_resource( $aws_path = '', $cache_path = '' ) {
		$image = waf_fetch_resource( $aws_path );
		if( !file_exists( $cache_path ) ) {
			// Create folder path
			wp_mkdir_p( dirname( $cache_path ) );
			// Write file
			if( $handle = fopen( $cache_path, 'w' ) ) {
				if( fwrite( $handle, $image ) !== false ) {
					return true;
				}
			}
			// if( file_put_contents( $cache_path, $image ) !== false ) {
			// 	return true;
			// }
		}
		return false;
	}

	