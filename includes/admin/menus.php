<?php
	// Register Admin Menus
	function waf_options_page() {
    add_menu_page(
      'Wave Fetch Dashboard',
      'Wave Fetch',
      'manage_options',
      'waf',
      'waf_options_page_html',
      'dashicons-post-status',
      20
    );

		add_submenu_page( 
			'waf', 
			'Refresh', 
			'Refresh', 
			'manage_options', 
			'refresh', 
			'waf_options_page_refresh_html', 
			2 
		);

		add_submenu_page( 
			'waf', 
			'Migrate', 
			'Migrate', 
			'manage_options', 
			'migrate', 
			'waf_options_page_migrate_html', 
			2 
		);

		add_submenu_page( 
			'waf', 
			'Charts', 
			'Charts', 
			'manage_options', 
			'charts', 
			'waf_options_page_charts_html', 
			2 
		);
	}

	// Hooks
	add_action('admin_menu', 'waf_options_page');

	//
	// Menu HTML
	//
	function waf_settings_table( $label, $fields, $values, $return = false ) {
		$html = '';

		$html .= '<table class="form-table">';
			$html .= '<tbody>';
				foreach( $fields as $field ) {
					$html .= '<tr>';
						$html .= '<th scope="row"><label for="' . $label . '[' . $field['name'] . ']">' . $field['label'] . '</label></th>';
						$html .= '<td>';
							switch( $field['type'] ) {
								case 'checkbox':
									$html .= '<input name="' . $label . '[' . $field['name'] . ']" type="checkbox" id="' . $label . '[' . $field['name'] . ']" value="1" ' . checked( $values[$field['name']], '1', false ) . ' class="regular-text">';
									break;
								case 'text':
								default:
									$html .= '<input name="' . $label . '[' . $field['name'] . ']" type="text" id="' . $label . '[' . $field['name'] . ']" value="' . esc_attr( isset( $values[$field['name']] ) ? $values[$field['name']] : '' ) . '" class="regular-text">';
									break;
							}
							$html .= isset( $field['description'] ) ? '<p>' . $field['description'] . '</p>' : '';
						$html .= '</td>';
					$html .= '</tr>';
				}
			$html .= '</tbody>';
		$html .= '</table>';

		if( $return ) {
			return $html;
		}
		print $html;
	}

	// Top Level Options Menu
	function waf_options_page_html() {
		if (!current_user_can('manage_options')) {
			return;
		}
		?>
			<div class="wrap">
				<h1><?= esc_html(get_admin_page_title()); ?></h1>
				<form method="post" action="options.php"> 
					<?php 
						settings_fields( 'waf-buoy-options' ); 
						do_settings_sections( 'waf-buoy-options' );

						$s3 = get_option('waf_s3');
						$s3_fields = array(
							array( 
								'label' => 'S3 Key',
								'name' => 'key',
								'type' => 'text',
							),
							array( 
								'label' => 'S3 Secret',
								'name' => 'secret',
								'type' => 'text',
							),
							array( 
								'label' => 'S3 Region',
								'name' => 'region',
								'type' => 'text',
								'description' => 'AWS region \'Asia Pacific (Sydney) ap-southeast-2 is <strong>ap-southeast-2</strong>\''
							),
							array( 
								'label' => 'S3 Bucket',
								'name' => 'bucket',
								'type' => 'text',
							),
							array( 
								'label' => 'S3 Buoy Root',
								'name' => 'buoy_root',
								'type' => 'text',
							),
							array( 
								'label' => 'S3 Buoy CSV',
								'name' => 'buoy_csv',
								'type' => 'text',
								'description' => 'Full path to file \'waves/buoys.csv\''
							)
						);

						$spotter = get_option('waf_spotter');
						$spotter_fields = array(
							array( 
								'label' => 'Spotter API Key',
								'name' => 'key',
								'type' => 'text'
							),
							array(
								'label' => 'Manage Buoys Locally',
								'name' => 'manage-locally',
								'type' => 'checkbox'
							)
						);

						$local_csv = get_option('waf_local_csv');
						$local_csv_fields = array(
							array( 
								'label' => 'CSV Directory Path',
								'name' => 'path',
								'type' => 'text',
								'description' => isset( $local_csv['path'] ) ? ( ( file_exists( $local_csv['path'] ) ) ? '<span style="color: #00a32a;">Directory found</span>' : '<strong style="color: #d63638;">Directory doesn\'t exist</strong>' ) : ''
							),
							array( 
								'label' => 'Buoy ID',
								'name' => 'bouy_id',
								'type' => 'text'
							)
						);

						$willy_weather = get_option('waf_willy_weather');
						$willy_weather_fields = array(
							array( 
								'label' => 'API Key',
								'name' => 'api_key',
								'type' => 'text'
							)
						);
					?>
					<h2>AWS</h2>
					<h4>Cron Commands</h4>
					<p>Add the following commands to your servers cron to automate buoy fetch. You may need to adjust the path to PHP depending on your systems configuration.</p>
					<ol>
						<li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_fetch_buoys_csv"</code><br>
							0,30&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Checks the buoys.csv for updates</em>
						</li>
						<li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_update_flagged_buoys"</code><br>
							2,32&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Checks each buoys to see if there is new data</em>
						</li>
						<!-- <li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_fetch_wave_file"</code><br>
							*/3&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Grabs a single buoys new/updated file</em>
						</li> -->
						<li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_count_wave_file_requires_download" | xargs seq | xargs -Iz /usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_fetch_wave_file"</code><br>
							3,33&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Grabs all buoys marked as requiring update (one at a time)</em>
						</li>
						<li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_fetch_wave_jpgs"</code><br>
							3,33&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Grabs all buoy memplots not already downloaded</em>
						</li>
					</ol>
					<?php waf_settings_table( 'waf_s3', $s3_fields, $s3 ); ?>
					
					<h2>Spotter</h2>
					<p>Use this method to grab records directly from Spotter</p>
					<ol>
						<li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_spotter_fetch_devices"</code><br>
							0&nbsp;&nbsp;0,12&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Update device list in the DB</em>
						</li>
						<li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_spotter_needs_update"</code><br>
							1,31&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Checks each buoys to see if there is new data</em>
						</li>
						<li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_spotter_fetch_updates"</code><br>
							3,18,33,48&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Grabs buoys with new data</em>
						</li>
					</ol>
					<?php waf_settings_table( 'waf_spotter', $spotter_fields, $spotter ); ?>
					<p>Cron setup to automated this process</p>
					<ol>
						<li>
							<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_local_fetch_updates"</code><br>
							0&nbsp;&nbsp;0,15,45&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Update device list in the DB</em>
						</li>
					</ol>
					<h2>Local CSV Directory</h2>
					<p>For example <code><?php print WAF__PLUGIN_DIR; ?>csv_directoy/</code></p>
					<?php waf_settings_table( 'waf_local_csv', $local_csv_fields, $local_csv ); ?>

					<h2>WillyWeather API</h2>
					<?php waf_settings_table( 'waf_willy_weather', $willy_weather_fields, $willy_weather ); ?>

					<?php submit_button(); ?>
				</form>
			</div>
		<?php
	}

	// Manage Buoys
	/*
	// function waf_options_page_buoys_html() {
	// 	if (!current_user_can('manage_options')) {
	// 		return;
	// 	}
	// 	settings_errors( 'waf-buoy-options-manage-buoys' );

	// 	?>
	// 		<div class="wrap">
	// 			<h1><?= esc_html(get_admin_page_title()); ?></h1>

	// 		</div>
	// 	<?php
	// }
	*/

	function waf_buoys_select_list( $selected = 0 ) {
		global $wpdb;
		$buoys = $wpdb->get_results("
			SELECT * FROM {$wpdb->prefix}waf_buoys
			ORDER BY `web_display_name`
		");

		$buoys_select = '';
		if( $buoys ) {
			foreach( $buoys as $buoy ) {
				$buoys_select .= '<option value="' . $buoy->id . '" ' . selected( $selected, $buoy->id, false ) . '>';
				$buoys_select .= $buoy->web_display_name . " &mdash; " . $buoy->id;
				$buoys_select .= '</option>';
			}
		}

		return $buoys_select;
	}

	// Migrate Buoy Data
	function waf_options_page_migrate_html() {
		if (!current_user_can('manage_options')) {
			return;
		}

		// Defaults
		$waf_start_date = "";
		$waf_end_date = "";
		$waf_migrate_from = 0;
		$waf_migrate_to = 0;

		// Messages
		settings_errors( 'waf-buoy-options-migrate' );

		$returned_data = get_settings_errors( 'waf-buoy-options-migrate' );
		
		foreach( $returned_data as $data ) {
			if( $data['code'] == 'waf-migrate-test' ) {
				$waf_migrate_from = $data['message']['waf_migrate_from'];
				$waf_migrate_to = $data['message']['waf_migrate_to'];
				$waf_start_date = $data['message']['waf_start_date'];
				$waf_end_date = $data['message']['waf_end_date'];
			}
		}
		
		?>
			<div class="wrap">
				<h1><?= esc_html(get_admin_page_title()); ?></h1>
				<form method="post" action="options.php"> 
					<?php
						settings_fields( 'waf-buoy-options-migrate' ); 
						do_settings_sections( 'waf-buoy-options-migrate' );
					?>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="">From Buoy</label></th>
								<td><select name="waf_migrate[waf_migrate_from]"><?php print waf_buoys_select_list( $waf_migrate_from ); ?></select></td>
							</tr>
							<tr>
								<th scope="row"><label for="">To Buoy</label></th>
								<td><select name="waf_migrate[waf_migrate_to]"><?php print waf_buoys_select_list( $waf_migrate_to ); ?></select></td>
							</tr>
							<tr>
								<th scope="row"><label for="">Start Date</label></th>
								<td><input name="waf_migrate[waf_start_date]" id="waf_start_date" type="datetime-local" value="<?php print $waf_start_date; ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="">End Date</label></th>
								<td><input name="waf_migrate[waf_end_date]" id="waf_end_date" type="datetime-local" value="<?php print $waf_end_date; ?>"></td>
							</tr>
						</tbody>
					</table>
					<p class="sumit">
						<?php submit_button( 'Test', 'secondary', 'test', false ); ?>
						<?php submit_button( 'Migrate', 'primary', 'submit', false ); ?>
					</p>
				</form>
			</div>
		<?php
	}

	// Refresh a Buoy
	function waf_options_page_refresh_html() {
		if (!current_user_can('manage_options')) {
			return;
		}

		// Messages
		settings_errors( 'waf-buoy-options-refresh' );
		?>
			<div class="wrap">
				<h1><?= esc_html(get_admin_page_title()); ?></h1>
				<form method="post" action="options.php"> 
					<?php
						settings_fields( 'waf-buoy-options-refresh' ); 
						do_settings_sections( 'waf-buoy-options-refresh' );
					?>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="">Buoy to refresh</label></th>
								<td>
									<?php
										global $wpdb;
										$buoys = $wpdb->get_results("
											SELECT * FROM {$wpdb->prefix}waf_buoys
											ORDER BY `web_display_name`
										");

										if( $buoys ) {
											print '<select name="waf_refresh[buoy]">';
												foreach( $buoys as $buoy ) {
													print '<option value="' . $buoy->id . '">';
														print $buoy->web_display_name . " &mdash; " . $buoy->id;
													print '</option>';
												}
											print '</select>';
										}
									?>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( 'Refresh' ); ?>
				</form>
			</div>
		<?php
	}