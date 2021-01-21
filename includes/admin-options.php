<?php
	// Admin Options
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
								'name' => 'region'
							),
							array( 
								'label' => 'S3 Bucket',
								'name' => 'bucket'
							),
							array( 
								'label' => 'S3 Buoy CSV',
								'name' => 'buoy_csv'
							)
						);
					?>
					<table class="form-table">
						<tbody>
							<h2>AWS</h2>
							<?php
								foreach( $s3_fields as $field ) {
									print '<tr>';
										print '<th scope="row"><label for="waf_s3[' . $field['name'] . ']">' . $field['label'] . '</label></th>';
										print '<td><input name="waf_s3[' . $field['name'] . ']" type="text" id="waf_s3[' . $field['name'] . ']" value="' . esc_attr( isset( $s3[$field['name']] ) ? $s3[$field['name']] : '' ) . '" class="regular-text"></td>';
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
	}

	function waf_register_settings() {
		// Register Settings Options
		register_setting( 
			'waf-buoy-options', 
			'waf_s3',
			'waf_sanitize_options'
		);		
	}

	function waf_sanitize_options( $option ) {
		// Sanitize Settings Options
		// todo
		return $option;
	}

	// Hooks
	add_action('admin_menu', 'waf_options_page');
	add_action('admin_init', 'waf_register_settings');