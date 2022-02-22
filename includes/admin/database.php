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
			web_display_name VARCHAR(255) NOT NULL,
			type VARCHAR(255) NOT NULL,
			is_enabled BOOLEAN DEFAULT FALSE NOT NULL,
			menu_order MEDIUMINT(9) NOT NULL,
			data TEXT NOT NULL,
			start_date BIGINT(8),
			end_date BIGINT(8),
			first_update BIGINT(8),
			last_update BIGINT(8),
			requires_update BOOLEAN DEFAULT FALSE NOT NULL,
			start_after_id MEDIUMINT(9) NOT NULL,
			lat VARCHAR(255) NOT NULL,
			lng VARCHAR(255) NOT NULL,
			drifting BOOLEAN DEFAULT FALSE NOT NULL,
			download_text TEXT NOT NULL,
			description TEXT NOT NULL,
			image VARCHAR(255) NOT NULL,
			download_enabled MEDIUMINT(9) DEFAULT 1 NOT NULL,
			download_requires_details MEDIUMINT(9) DEFAULT 0 NOT NULL
		  PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// Buoy Wave Data
		$table_name = $wpdb->prefix . "waf_wave_data";
		$sql = "CREATE TABLE $table_name (
			id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
			buoy_id MEDIUMINT(9) NOT NULL,
			data_points VARCHAR(4096) NOT NULL,
			timestamp BIGINT(8),
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// Buoy Wave Files
		$table_name = $wpdb->prefix . "waf_wave_files";
		$sql = "CREATE TABLE $table_name (
			id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
			buoy_id MEDIUMINT(9) NOT NULL,
			file_date_name BIGINT(8) NOT NULL,
			timestamp BIGINT(8),
			requires_update BOOLEAN DEFAULT FALSE NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// Buoy Memplot Files
		$table_name = $wpdb->prefix . "waf_wave_memplots";
		$sql = "CREATE TABLE $table_name (
			id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
			buoy_id MEDIUMINT(9) NOT NULL,
			timestamp BIGINT(8),
			full_path VARCHAR(4096) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );
		
		// Update Database Version Option
		update_option( 'waf_database_version', WAF__VERSION );

		// Notice
		add_action( 'admin_notices', 'waf_database_updated' );
	}