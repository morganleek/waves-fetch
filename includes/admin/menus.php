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
	}

	// Hooks
	add_action('admin_menu', 'waf_options_page');

	//
	// Menu HTML
	//

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
					?>
					<table class="form-table">
						<tbody>
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
							<?php
								foreach( $s3_fields as $field ) {
									print '<tr>';
										print '<th scope="row"><label for="waf_s3[' . $field['name'] . ']">' . $field['label'] . '</label></th>';
										print '<td>';
											print '<input name="waf_s3[' . $field['name'] . ']" type="' . $field['type'] . '" id="waf_s3[' . $field['name'] . ']" value="' . esc_attr( isset( $s3[$field['name']] ) ? $s3[$field['name']] : '' ) . '" class="regular-text">';
											print isset( $field['description'] ) ? '<p>' . $field['description'] . '</p>' : '';
										print '</td>';
									print '</tr>';
								}
							?>
						</tbody>
					</table>
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
					<table class="form-table">
						<tbody>
							<?php
								foreach( $spotter_fields as $field ) {
									print '<tr>';
										print '<th scope="row"><label for="waf_spotter[' . $field['name'] . ']">' . $field['label'] . '</label></th>';
										print '<td>';
											switch( $field['type'] ) {
												case 'checkbox':
													print '<input name="waf_spotter[' . $field['name'] . ']" type="checkbox" id="waf_spotter[' . $field['name'] . ']" value="1" ' . checked( $spotter[$field['name']], '1', false ) . ' class="regular-text">';
													break;
												case 'text':
												default:
													print '<input name="waf_spotter[' . $field['name'] . ']" type="text" id="waf_spotter[' . $field['name'] . ']" value="' . esc_attr( isset( $spotter[$field['name']] ) ? $spotter[$field['name']] : '' ) . '" class="regular-text">';
													break;
											}
											print isset( $field['description'] ) ? '<p>' . $field['description'] . '</p>' : '';
										print '</td>';
									print '</tr>';
								}
							?>
						</tbody>
					</table>
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

	// Migrate Buoy Data
	function waf_options_page_migrate_html() {
		if (!current_user_can('manage_options')) {
			return;
		}

		global $wpdb;
		$buoys = $wpdb->get_results("
			SELECT * FROM {$wpdb->prefix}waf_buoys
			ORDER BY `web_display_name`
		");

		$buoys_select = '';
		if( $buoys ) {
			foreach( $buoys as $buoy ) {
				$buoys_select .= '<option value="' . $buoy->id . '">';
				$buoys_select .= $buoy->web_display_name . " &mdash; " . $buoy->id;
				$buoys_select .= '</option>';
			}
		}

		// Messages
		settings_errors( 'waf-buoy-options-migrate' );
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
								<td><select name="waf_migrate[waf_migrate_from]"><?php print $buoys_select; ?></select></td>
							</tr>
							<tr>
								<th scope="row"><label for="">To Buoy</label></th>
								<td><select name="waf_migrate[waf_migrate_to]"><?php print $buoys_select; ?></select></td>
							</tr>
							<tr>
								<th scope="row"><label for="">Start Date</label></th>
								<td><input name="waf_migrate[waf_start_date]" id="waf_start_date" type="datetime-local" value=""></td>
							</tr>
							<tr>
								<th scope="row"><label for="">End Date</label></th>
								<td><input name="waf_migrate[waf_end_date]" id="waf_end_date" type="datetime-local" value=""></td>
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