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
			1 
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
								'name' => 'key'
							),
							array( 
								'label' => 'S3 Secret',
								'name' => 'secret'
							),
							array( 
								'label' => 'S3 Region',
								'name' => 'region',
								'description' => 'AWS region \'Asia Pacific (Sydney) ap-southeast-2 is <strong>ap-southeast-2</strong>\''
							),
							array( 
								'label' => 'S3 Bucket',
								'name' => 'bucket'
							),
							array( 
								'label' => 'S3 Buoy Root',
								'name' => 'buoy_root'
							),
							array( 
								'label' => 'S3 Buoy CSV',
								'name' => 'buoy_csv',
								'description' => 'Full path to file \'waves/buoys.csv\''
							)
						);
					?>
					<table class="form-table">
						<tbody>
							<h2>Cron Commands</h2>
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
									<code>/usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes/ajax-cli.php "action=waf_count_wave_file_requires_download" | xargs seq | xargs -Iz /usr/local/bin/php <?php print WAF__PLUGIN_DIR; ?>includes ajax-cli.php "action=waf_fetch_wave_file"</code><br>
									3,33&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;*&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<em>Grabs all buoys marked as requiring update (one at a time)</em>
								</li>
							</ol>
							<h2>AWS</h2>
							<?php
								foreach( $s3_fields as $field ) {
									print '<tr>';
										print '<th scope="row"><label for="waf_s3[' . $field['name'] . ']">' . $field['label'] . '</label></th>';
										print '<td>';
											print '<input name="waf_s3[' . $field['name'] . ']" type="text" id="waf_s3[' . $field['name'] . ']" value="' . esc_attr( isset( $s3[$field['name']] ) ? $s3[$field['name']] : '' ) . '" class="regular-text">';
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