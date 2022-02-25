<?php
	// Collect user submitted data
	function waf_collect_user_data() {
		global $wpdb;

		$buoy_id = 0;
		if( isset( $_REQUEST['buoy_id'] ) && isset( $_REQUEST['nonce'] ) ) {
			$buoy_id = intval( $_REQUEST['buoy_id'] ); 
			$nonce = $_REQUEST['nonce'];
			$form_data = sanitize_text_field( $_REQUEST['form_data'] );
		}
		else {
			// No ID set
			print 0;
			wp_die();
		}
		
		if( !wp_verify_nonce( $nonce, 'user_submitted_data' . date( 'YmdHa' ) ) ) {
			print 0;
			wp_die();
		}
		
		$wpdb->insert(
			$wpdb->prefix . 'waf_user_data',
			array(
				'buoy_id' => $buoy_id,
				'form_data' => $form_data,
				'timestamp' => date( 'U' )
			),
			array( '%d', '%s', '%s' )
		);
		
		$buoy_label = $wpdb->get_var( 
			$wpdb->prepare( 
				"SELECT `label` FROM `wp_waf_buoys` WHERE `id` = %d",
				$_REQUEST['buoy_id']
			)
		);
		
		// Send Email
		if( $options = get_option('wad_options') ) {
			if( isset( $options['buoy_display_user_info_email_recipient'] ) ) {
				print $options['buoy_display_user_info_email_recipient'];
				$message = 'Buoy: ' . $buoy_label . "\n";
				
				$form_data = json_decode( stripslashes( $_REQUEST['form_data'] ) );
				foreach( $form_data as $key => $data ) {
					$message .= $key . ': ' . $data . "\n";
				}

				wp_mail( $options['buoy_display_user_info_email_recipient'], 'SA Waves Download User Info', $message );
			}
		}
		
		wp_die();
	}
	
	add_action( 'wp_ajax_waf_collect_user_data', 'waf_collect_user_data' );
	add_action( 'wp_ajax_nopriv_waf_collect_user_data', 'waf_collect_user_data' );