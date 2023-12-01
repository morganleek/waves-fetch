<?php
	function waf_options_page_charts_html() {
		global $wpdb; 

		if (!current_user_can('manage_options')) {
			return;
		}

		// Messages
		settings_errors( 'waf-buoy-options-charts' );
		?>
			<div class="wrap">
				<h1><?= esc_html(get_admin_page_title()); ?></h1>
				<form method="post"> 
					<?php
						settings_fields( 'waf-buoy-options-charts' ); 
						do_settings_sections( 'waf-buoy-options-charts' );
					?>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="">Buoy to chart</label></th>
								<td>
									<?php
										global $wpdb;
										$buoys = $wpdb->get_results("
											SELECT * FROM {$wpdb->prefix}waf_buoys
											ORDER BY `web_display_name`
										");

										if( $buoys ) {
											$selected = $_REQUEST['waf_chart'];
											print '<select name="waf_chart">';
												foreach( $buoys as $buoy ) {
													print '<option value="' . $buoy->id . '" ' . selected( $selected, $buoy->id, false ) . '>';
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
					<?php submit_button( 'Chart' ); ?>
				</form>
				<?php 
					if( isset( $_REQUEST['waf_chart'] ) ) {
						$buoy_id = $_REQUEST['waf_chart'];
						// SELECT FROM_UNIXTIME( timestamp, '%Y-%m-%d' ) AS date, COUNT(id) FROM `wp_waf_wave_data` WHERE `buoy_id` = 316 GROUP BY YEAR(date), MONTH(date)
						$data = $wpdb->get_results(
							$wpdb->prepare( "SELECT FROM_UNIXTIME( timestamp, '%%Y-%%m-%%d' ) AS date, COUNT(id) as count
								FROM `{$wpdb->prefix}waf_wave_data` WHERE `buoy_id` = %d 
								GROUP BY date
								ORDER BY date",
								$buoy_id	
							), OBJECT_K
						);
			
						if( $data ) {
							$first = strtotime( $data[array_key_first($data)]->date );
							$last = strtotime( $data[array_key_last($data)]->date );
							$now = $first;

							print '<style>
								.custom-chart > div { 
									width: 1px; 
									background: #727272; 
									display: inline-block; 
									position: relative;
								}
								.custom-chart > div::after { 
									content: attr(data-info);
									opacity: 0;
									position: absolute;
									bottom: 0;
									left: 0;
									white-space: nowrap;
									background: #000;
									color: #fff;
									padding: 2px;
									z-index: 100;
									transform: translate(0, 110%);
								}
								.custom-chart > div:hover {
									background: #000;
								}
								.custom-chart > div:hover::after { 
									opacity: 1;
								}
							</style>';

							print '<p>Date (' . date( 'Y-m-d', $first ) . ') &mdash; (' . date( 'Y-m-d', $last ) . ')</p>';

							print '<div class="custom-chart">';
							while( $now < $last ) {
								$today = date( 'Y-m-d', $now );
								if( array_key_exists( $today, $data ) ) {
									$height = $data[$today]->count * 3;
									print '<div data-info="' . $today . ' (' . $now . ')' . ': ' . $data[$today]->count . ' entries" style="height: ' . $height . 'px;"></div>';
								}
								else {
									print '<div data-info="' . $today . ': 0" style="height: 0px;"></div>';
								}
								$now = strtotime( '+1 day', $now );
							}	
							print '</div>';
						}
					}
				?>
			</div>
		<?php
	}
