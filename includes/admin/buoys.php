<?php
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
	}

	class Buoy_Info_List extends WP_List_Table {

		/** Class constructor */
		public function __construct() {

			parent::__construct( [
				'singular' => __( 'Buoy', 'waves-fetch' ), //singular name of the listed records
				'plural'   => __( 'Buoys', 'waves-fetch' ), //plural name of the listed records
				'ajax'     => false //does this table support ajax?
			] );
		}


		/**
		 * Retrieve buoys data from the database
		 *
		 * @param int $per_page
		 * @param int $page_number
		 *
		 * @return mixed
		 */
		public static function get_buoys( $per_page = 5, $page_number = 1 ) {

			global $wpdb;

			$sql = "SELECT * FROM {$wpdb->prefix}waf_buoys";

			if ( ! empty( $_REQUEST['orderby'] ) ) {
				$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
				$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
			}

			$sql .= " LIMIT $per_page";
			$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


			$result = $wpdb->get_results( $sql, 'ARRAY_A' );

			return $result;
		}


		/**
		 * Delete a buoy record.
		 *
		 * @param int $id buoy ID
		 */
		public static function delete_buoy( $id ) {
			global $wpdb;

			$wpdb->delete(
				"{$wpdb->prefix}waf_buoys",
				[ 'id' => $id ],
				[ '%d' ]
			);
		}


		/**
		 * Returns the count of records in the database.
		 *
		 * @return null|string
		 */
		public static function record_count() {
			global $wpdb;

			$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}waf_buoys";

			return $wpdb->get_var( $sql );
		}


		/** Text displayed when no buoy data is available */
		public function no_items() {
			_e( 'No buoys avaliable.', 'waves-fetch' );
		}


		/**
		 * Render a column when no column specific method exist.
		 *
		 * @param array $item
		 * @param string $column_name
		 *
		 * @return mixed
		 */
		public function column_default( $item, $column_name ) {
			switch ( $column_name ) {
				case 'id':
				case 'label':
				case 'web_display_name':
					return $item[ $column_name ];
				case 'last_update':
					return ( $item[ $column_name ] == '0' ) ? '&ndash;' : date('d-m-Y H:i:s', $item[ $column_name ]);
				// case 'lat_lng':
				// 	if(!empty($item[ 'custom_lat' ]) && !empty($item['custom_lng'])) {
				// 		return $item[ 'custom_lat' ] . ' / ' . $item['custom_lng'];
				// 	}
				// 	return 'N/A';
				case 'is_enabled':
					switch ( $item[ 'is_enabled' ] ) {
						case 0:
							return 'Disabled';
						case 1:
							return 'Enabled';
						case 2: 
							return 'Historic';
						default:
							return 'Disabled';
					}

					// return ( $item[ 'is_enabled' ] == 3 ) ? 'Historic' : ( ($item[ 'is_enabled' ] == 1) ? 'Enabled' : 'Disabled' );
				// case 'hide_location':
				// 	return ($item[ $column_name ] == 1) ? 'Yes' : 'No';
				default:
					return print_r( $item, true ); //Show the whole array for troubleshooting purposes
			}
		}

		/**
		 * Render the bulk edit checkbox
		 *
		 * @param array $item
		 *
		 * @return string
		 */
		function column_cb( $item ) {
			return sprintf(
				'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
			);
		}


		/**
		 * Method for name column
		 *
		 * @param array $item an array of DB data
		 *
		 * @return string
		 */
		function column_name( $item ) {

			$delete_nonce = wp_create_nonce( 'waves_fetch_delete_buoy' );

			$title = '<strong>' . $item['id'] . '</strong>';

			$actions = [
				'edit' => sprintf( '<a href="?page=%s&action=%s&buoy=%s">Edit</a>', esc_attr( $_REQUEST['page'] ), 'edit', absint( $item['id'] )),
				'delete' => sprintf( '<a href="?page=%s&action=%s&buoy=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce )
			];

			return $title . $this->row_actions( $actions );
		}


		/**
		 *  Associative array of columns
		 *
		 * @return array
		 */
		function get_columns() {
			$columns = [
				'cb'      => '<input type="checkbox" />',
				'name'    => __( 'ID', 'waves-fetch' ),
				'web_display_name' => __( 'Web Display Name' ),
				'label' => __( 'Label', 'waves-fetch' ),
				'is_enabled'    => __( 'Status', 'waves-fetch' ),
				'last_update' => 'Last Update'
			];

			return $columns;
		}


		/**
		 * Columns to make sortable.
		 *
		 * @return array
		 */
		public function get_sortable_columns() {
			$sortable_columns = array(
				'name' => array('id', true),
				'label' => array('label', true),
				'web_display_name' => array( 'web_display_name', true ),
				'is_enabled' => array( 'is_enabled', true )
				// 'buoy_order' => array( 'buoy_order', true )
				// 'city' => array( 'city', false )
			);

			return $sortable_columns;
		}

		/**
		 * Returns an associative array containing the bulk action
		 *
		 * @return array
		 */
		public function get_bulk_actions() {
			$actions = [
				'bulk-delete' => 'Delete'
			];

			return $actions;
		}


		/**
		 * Handles data query and filter, sorting, and pagination.
		 */
		public function prepare_items() {
			$this->_column_headers = $this->get_column_info();
			
			
			/** Process bulk action */
			$this->process_bulk_action();
			
			$per_page     = $this->get_items_per_page( 'buoys_per_page', 20 );
			$current_page = $this->get_pagenum();
			$total_items  = self::record_count();
			
			
			$this->set_pagination_args( [
				'total_items' => $total_items, //WE have to calculate the total number of items
				'per_page'    => $per_page //WE have to determine how many items to show on a page
			] );

			$this->items = self::get_buoys( $per_page, $current_page );
		}

		public function process_bulk_action() {
			// Detect when a bulk action is being triggered...
			if ( 'delete' === $this->current_action() ) {
				
				// In our file that handles the request, verify the nonce.
				$nonce = esc_attr( $_REQUEST['_wpnonce'] );
				
				if ( ! wp_verify_nonce( $nonce, 'waves_fetch_delete_buoy' ) ) {
					die( 'Go get a life script kiddies' );
				}
				else {
					self::delete_buoy( absint( $_REQUEST['buoy'] ) );
				}
			}

			// If the delete bulk action is triggered
			if ( ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'bulk-delete' )
				|| ( isset( $_REQUEST['action2'] ) && $_REQUEST['action2'] == 'bulk-delete' )
			) {
				$delete_ids = esc_sql( $_REQUEST['bulk-delete'] );
				
				// loop over the array of record IDs and delete them
				foreach ( $delete_ids as $id ) {
					self::delete_buoy( $id );
				}
				
				// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
				// add_query_arg() return the current url
				// wp_redirect( esc_url_raw(add_query_arg()) );
				// exit;
			}
		}

	}


	class Buoy_Info_Plugin {

		// class instance
		static $instance;

		// Buoy WP_List_Table object
		public $buoys_obj;

		// class constructor
		public function __construct() {
			add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
			add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
		}


		public static function set_screen( $status, $option, $value ) {
			return $value;
		}

		public function plugin_menu() {

			// Check if buoys are being managed locally
			$spotter = get_option('waf_spotter');
			if( $spotter['manage-locally'] == '1' ) {
				$hook = add_submenu_page( 
					'waf', 
					'Buoys', 
					'Buoys', 
					'manage_options', 
					'buoys', 
					[$this, 'plugin_settings_page']
				);

				add_action( "load-$hook", [ $this, 'screen_option' ] );

				// $hook = add_submenu_page( 
				// 	'waf', 
				// 	'Migrate', 
				// 	'Migrate', 
				// 	'manage_options', 
				// 	'migrate', 
				// 	[$this, 'plugin_migration_page']
				// );

				// add_action( "load-$hook", [ $this, 'screen_option' ] );
			}


			// $hook = add_submenu_page(
			// 	'waves-fetch',
			// 	'Spoondrift Dashboard',
			// 	'Buoys Info',
			// 	'manage_options',
			// 	'uwa-buoy-info',
			// 	[$this, 'plugin_settings_page']
			// );

			

		}

		public function plugin_migration_page() {
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

			

			?>
			<div class="wrap">
				<h2>Migrate Buoy Data</h2>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="">From Buoy</label></th>
							<td><select name="waf_migrate_from"><?php print $buoys_select; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="">To Buoy</label></th>
							<td><select name="waf_migrate_to"><?php print $buoys_select; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="">Start Date</label></th>
							<td><input name="waf_start_date" id="waf_start_date" type="datetime-local" value=""></td>
						</tr>
						<tr>
							<th scope="row"><label for="">End Date</label></th>
							<td><input name="waf_end_date" id="waf_end_date" type="datetime-local" value=""></td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-secondary" value="Test">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Migrate">
				</p>
			</div>
			<?php
		}

		/**
		 * Plugin settings page
		 */
		public function plugin_settings_page() {
			?>
			<div class="wrap">
				<h2>Buoys Info</h2>
				<div id="col-container" class="wp-clearfix">
					<div id="col-left">
						<div id="col-wrap">
							<div class="form-wrap">
								<?php
									global $wpdb;
									
									$title = 'Add New Buoy';

									// All fields in the form and db
									$fields = array(
										array(
											'field' => 'id', 
											'form_field' => 'id',
											'input' => 'text',
											'value' => '',
											'label' => 'ID',
											'type' => '%d'
										),
										array(
											'field' => 'web_display_name', 
											'form_field' => 'web-display-name',
											'input' => 'text',
											'value' => '',
											'label' => 'Web Display Name',
											'type' => '%s'
										),
										array(
											'field' => 'label', 
											'form_field' => 'label',
											'input' => 'text',
											'value' => '',
											'label' => 'Label',
											'type' => '%s'
										),
										array(
											'field' => 'type', 
											'form_field' => 'type',
											'input' => 'text',
											'value' => '',
											'label' => 'Type',
											'type' => '%s'
										),
										array(
											'field' => 'is_enabled', 
											'form_field' => 'is-enabled',
											'input' => 'select',
											'value' => '',
											'label' => 'Is enabled',
											'type' => '%d',
											'options' => array(
												'Disabled',
												'Enabled',
												'Historic'
											)
										),
										array(
											'field' => 'menu_order', 
											'form_field' => 'menu-order',
											'input' => 'text',
											'value' => '0',
											'label' => 'Menu Order',
											'type' => '%d'
										),
										array(
											'field' => 'data', 
											'form_field' => 'data',
											'input' => 'textarea',
											'value' => '',
											'label' => 'Data',
											'type' => '%s'
										),
										array(
											'field' => 'start_date', 
											'form_field' => 'start-date',
											'input' => 'text',
											'value' => '',
											'label' => 'Start Date',
											'type' => '%d'
										),
										array(
											'field' => 'end_date', 
											'form_field' => 'end-date',
											'input' => 'text',
											'value' => '',
											'label' => 'End Date',
											'type' => '%d'
										),
										array(
											'field' => 'first_update', 
											'form_field' => 'first-update',
											'input' => 'text',
											'value' => '',
											'label' => 'First Update',
											'type' => '%d'
										),
										array(
											'field' => 'last_update', 
											'form_field' => 'last-update',
											'input' => 'text',
											'value' => '',
											'label' => 'Last Date',
											'type' => '%d'
										),
										array(
											'field' => 'requires_update', 
											'form_field' => 'requires-update',
											'input' => 'checkbox',
											'value' => '',
											'label' => 'Requires Update',
											'type' => '%d'
										),
										array(
											'field' => 'start_after_id', 
											'form_field' => 'start-after-id',
											'input' => 'text',
											'value' => '',
											'label' => 'Start After ID',
											'type' => '%d'
										),
										array(
											'field' => 'lat', 
											'form_field' => 'lat',
											'input' => 'text',
											'value' => '',
											'label' => 'Lat',
											'type' => '%s'
										),
										array(
											'field' => 'lng', 
											'form_field' => 'lng',
											'input' => 'text',
											'value' => '',
											'label' => 'Long',
											'type' => '%s'
										),
										array(
											'field' => 'drifting', 
											'form_field' => 'drifting',
											'input' => 'checkbox',
											'value' => '',
											'label' => 'Drifting',
											'type' => '%d'
										),
										array(
											'field' => 'download_text', 
											'form_field' => 'download-text',
											'input' => 'textarea',
											'value' => '',
											'label' => 'Download Text',
											'type' => '%s'
										),
										array(
											'field' => 'description', 
											'form_field' => 'description',
											'input' => 'textarea',
											'value' => '',
											'label' => 'Description',
											'type' => '%s'
										),
										array(
											'field' => 'image', 
											'form_field' => 'image',
											'input' => 'text',
											'value' => '',
											'label' => 'Image URL',
											'type' => '%s'
										),
										array(
											'field' => 'download_enabled', 
											'form_field' => 'download-enabled',
											'input' => 'checkbox',
											'value' => '1',
											'label' => 'Download Enabled',
											'type' => '%d'
										),
										array(
											'field' => 'download_requires_details', 
											'form_field' => 'download-requires-details',
											'input' => 'checkbox',
											'value' => '',
											'label' => 'Download Requires Details',
											'type' => '%d'
										)
									);
									
									if(isset($_REQUEST['buoy-info'])) {
										// nonce check
										$data = array();
										$format = array();
										
										// Add each value
										foreach( $fields as $field ) {
											switch ($field['input']) {
												case 'checkbox':
													$value = ( $_REQUEST[$field['form_field']] == "1" ) ? 1 : 0;
													break;
												case 'select':
												default:
													$value = $_REQUEST[$field['form_field']];
													break;
											}
											$data[$field['field']] = $value;
											array_push( $format, $field['type'] );
										}

										// Existing
										if(isset($_REQUEST['hidden-id']) && !empty($_REQUEST['hidden-id'])) {
											$where = array( 'id' => $_REQUEST['buoy'] );
											$where_format = array( '%d' );

											$wpdb->update( 
												"{$wpdb->prefix}waf_buoys",
												$data,
												$where,
												$format,
												$where_format
											);
										}	
										// New
										else {
											$wpdb->insert(
												"{$wpdb->prefix}waf_buoys",
												$data,
												$format
											);
										}
									}
									else if(isset($_REQUEST['action'])) {
										
										if($_REQUEST['action'] === 'edit' && $_REQUEST['buoy']) {										
											// Grab buoy info
											$buoy = $wpdb->get_row(
												$wpdb->prepare("SELECT * FROM {$wpdb->prefix}waf_buoys WHERE id = %d", $_REQUEST['buoy']),
												'ARRAY_A'
											);

											// Transfer into fields values
											array_walk( $fields, function( &$field, $key, $buoy ) {
												if( isset( $buoy[ $field['field'] ] ) ) {
													$field['value'] = $buoy[ $field['field'] ];
												}
											}, $buoy );
											
											$title = 'Edit Existing Buoy';
											// unset( $fields[0] ); // Remove ID for update
										}
									}
									
									// Form
									$form_data = array();

								?>
								<h2><?php print $title; ?></h2>
								<form method="post">
									<input type="hidden" name="buoy-info" value="1">
									<input type="hidden" name="hidden-id" value="<?php print ( isset( $_REQUEST['buoy'] ) ) ? $_REQUEST['buoy'] : ''; ?>"> 
									<?php 
										foreach( $fields as $field ) {
											print '<div class="form-field form-required ' . $field['form_field'] . '">';
												print '<label for="' . $field['form_field'] . '">' . $field['label'] . '</label>';
												$value = $field['value']; // isset( $form_data[ $field['field'] ] ) ? $form_data[ $field['field'] ] : $field['default'];
												switch ($field['input']) {
													case 'select':
														print '<select name="' . $field['form_field'] . '" id="' . $field['form_field'] . '">';
															foreach( $field['options'] as $k => $v ) {
																print '<option ' . selected( $k, $field['value'], false ) . ' value="' . $k . '">' . $v . '</option>';
															}
														print '</select>';
														break;
													case 'checkbox':
														print '<input name="' . $field['form_field'] . '" id="' . $field['form_field'] . '" type="checkbox" ' . checked( 1, $value, false ) . ' value="1">';
														break;
													case 'textarea':
														print '<textarea name="' . $field['form_field'] . '" id="' . $field['form_field'] . '" rows="5" cols="40">' . $value . '</textarea>';
														break;
													default:
														print '<input name="' . $field['form_field'] . '" id="' . $field['form_field'] . '" type="text" value="' . $value . '" size="40" aria-required="true">';
														break;
												}
											print '</div>';
										}
									?>
									<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php print ( isset( $_REQUEST['buoy'] ) ) ? 'Update Buoy' : 'Add New Buoy'; ?>"></p>
								</form>
							</div>
						</div>
					</div>
					<div id="col-right">
						<div id="col-wrap">
							<form method="post">
								<?php
									$this->buoys_obj->prepare_items();
									$this->buoys_obj->display(); 
								?>
							</form>
						</div>
					</div>
				</div>
			</div>
		<?php
		}

		/**
		 * Screen options
		 */
		public function screen_option() {

			$option = 'per_page';
			$args   = [
				'label'   => 'buoys',
				'default' => 5,
				'option'  => 'buoys_per_page'
			];

			add_screen_option( $option, $args );

			$this->buoys_obj = new Buoy_Info_List();
		}


		/** Singleton instance */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

	}


	add_action( 'plugins_loaded', function () {
		Buoy_Info_Plugin::get_instance();
	} );