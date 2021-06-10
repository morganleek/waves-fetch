<?php 
	if( !function_exists( 'array_keys_exists' ) ) {
		function array_keys_exists(array $keys, array $arr) {
			return !array_diff_key(array_flip($keys), $arr);
		}
	}

	if( !function_exists( 'waf_array_keys_exists' ) ) {
		function waf_array_keys_exists(array $keys, array $arr) {
			return !array_diff_key(array_flip($keys), $arr);
		}
	}

	if( !function_exists( 'waf_expand_date_path' ) ) {
		function waf_expand_date_path( $date = '', $label = '', $root = '' ) {
			// Format "wawaves/Torbay/text_archive/2021/01/Torbay_20210124.csv"
			if( !empty( $date ) && !empty( $label ) && !empty( $root ) ) {
				$year = substr( $date, 0, 4 ); // Year folder
				$month = substr( $date, 4, 2 ); // Month folder
				return $root . '/' . $label . '/' . 'text_archive' . '/' . $year . '/' . $month . '/' . $label . '_' . $date . '.csv';
			}
			return '';
		}
	}

	if( !function_exists( 'waf_collapse_date_path' ) ) {
		function waf_collapse_date_path( $path = '', $label = '' ) {
			if( !empty( $path ) && !empty( $label ) ) {
				return substr( $path, strpos($path, $label . '_') + strlen( $label . '_' ), 8 );
			}
			return '';
		}
	}

	if( !function_exists( 'waf_jpg_file_to_time' ) ) {
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
	}