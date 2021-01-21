<?php
	// Notice
	function waf_database_updated() {
		print '<div class="notice notice-success is-dismissible">';
			print '<p>Wave Fetch Database Updated</p>';
		print '</div>'; 
	}

	// Setup Database Tables
	function waf_database_init() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		global $wpdb;

		// Create Database Tables
		$charset_collate = $wpdb->get_charset_collate();
		
		// Buoys
		$table_name = $wpdb->prefix . "waf_buoys";
		$sql = "CREATE TABLE $table_name (
		  id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
			label VARCHAR(1024) NOT NULL,
			type VARCHAR(255) NOT NULL,
			is_enabled BOOLEAN DEFAULT FALSE NOT NULL,
			menu_order MEDIUMINT(9) NOT NULL,
			data TEXT NOT NULL,
			start_date DATETIME NOT NULL,
			end_date DATETIME NOT NULL,
			first_update DATETIME NOT NULL,
			last_update DATETIME NOT NULL,
		  PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// Buoy Wave Data
		$table_name = $wpdb->prefix . "waf_wave_data";
		$sql = "CREATE TABLE $table_name (
			id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
			buoy_id MEDIUMINT(9) NOT NULL,
			data_points VARCHAR(4096) NOT NULL,
			timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );
		
		// Update Database Version Option
		update_option( 'waf_database_version', WAF__VERSION );

		// Notice
		add_action( 'admin_notices', 'waf_database_updated' );
	}

	function waf_check_version() {
		if( get_site_option( 'waf_database_version' ) != WAF__VERSION ) {
			// Update Database
			waf_database_init(); 
		}
	}

	add_action( 'plugins_loaded', 'waf_check_version' );