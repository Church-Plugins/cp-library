<?php

namespace CP_Library\Admin;

/**
 * Plugin Tools
 *
 */
class Tools {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * Only make one instance of \CP_Library\Tools
	 *
	 * @return Tools
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Tools ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor. Add admin hooks and actions
	 *
	 */
	protected function __construct() {
		add_action( 'admin_menu', [ $this, 'tools_menu' ], 10 );
		add_action( 'cp_library_tools_tab_import_export', [ $this, 'import_export_display' ] );
	}

	public function tools_menu() {
		$post_type = cp_library()->setup->post_types->item_type_enabled() ? cp_library()->setup->post_types->item_type->post_type : cp_library()->setup->post_types->item->post_type;

		add_submenu_page( 'edit.php?post_type=' . $post_type, __( 'CP Sermon Library Tools', 'cp-library' ), __( 'Tools', 'easy-digital-downloads' ), 'manage_options', 'cp-library-tools', [
			$this,
			'page_callback'
		] );
	}

	public function page_callback() {

		// Get tabs and active tab
		$tabs = $this->get_tools_tabs();

		$active_tab = isset( $_GET['tab'] )
			? sanitize_key( $_GET['tab'] )
			: 'import_export';

//		wp_enqueue_script( 'edd-admin-tools' );

		if ( 'import_export' === $active_tab ) {
			wp_enqueue_script( 'cp-admin-tools-import' );
//			wp_enqueue_script( 'edd-admin-tools-export' );
		}
		?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Tools', 'easy-digital-downloads' ); ?></h1>
			<hr class="wp-header-end">

			<nav class="nav-tab-wrapper edd-nav-tab-wrapper"
				 aria-label="<?php esc_attr_e( 'Secondary menu', 'easy-digital-downloads' ); ?>">
				<?php

				foreach ( $tabs as $tab_id => $tab_name ) {

					$tab_url = cp_library()->admin->get_admin_url(
						array(
							'page' => 'cp-library-tools',
							'tab'  => sanitize_key( $tab_id ),
						)
					);

					$tab_url = remove_query_arg(
						array(
							'cp-message',
						),
						$tab_url
					);

					$active = ( $active_tab === $tab_id )
						? ' nav-tab-active'
						: '';

					echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $tab_name ) . '</a>';
				}

				?>
			</nav>

			<div class="metabox-holder">
				<?php
				do_action( 'cp_library_tools_tab_' . esc_attr( $active_tab ) );
				?>
			</div><!-- .metabox-holder -->
		</div><!-- .wrap -->

		<?php
	}

	public function import_export_display() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		do_action( 'cp_library_tools_import_export_before' );
		?>

		<div class="postbox edd-import-payment-history">
			<h3><span><?php esc_html_e( 'Import Sermons', 'easy-digital-downloads' ); ?></span></h3>
			<div class="inside">
				<p><?php esc_html_e( 'Import a CSV file of sermons.', 'easy-digital-downloads' ); ?></p>
				<form id="cp-import-sermons" class="cp-import-form cp-import-export-form"
					  action="<?php echo esc_url( add_query_arg( 'cp_library_action', 'upload_import_file', admin_url() ) ); ?>"
					  method="post" enctype="multipart/form-data">

					<div class="edd-import-file-wrap">
						<?php wp_nonce_field( 'cp_library_ajax_import', 'cp_library_ajax_import' ); ?>
						<input type="hidden" name="edd-import-class" value="EDD_Batch_Payments_Import"/>
						<p>
							<input name="edd-import-file" id="edd-payments-import-file" type="file"/>
						</p>
						<span>
						<input type="submit" value="<?php _e( 'Import CSV', 'easy-digital-downloads' ); ?>"
							   class="button-secondary"/>
						<span class="spinner"></span>
					</span>
					</div>

					<div class="edd-import-options" id="edd-import-payments-options" style="display:none;">

						<p>
							<?php
							printf(
								__( 'Each column loaded from the CSV needs to be mapped to an order field. Select the column that should be mapped to each field below. Any columns not needed can be ignored. See <a href="%s" target="_blank">this guide</a> for assistance with importing payment records.', 'easy-digital-downloads' ),
								'https://docs.easydigitaldownloads.com/category/1337-importexport'
							);
							?>
						</p>

						<table class="widefat edd_repeatable_table striped" width="100%" cellpadding="0"
							   cellspacing="0">
							<thead>
							<tr>
								<th><strong><?php _e( 'Payment Field', 'easy-digital-downloads' ); ?></strong></th>
								<th><strong><?php _e( 'CSV Column', 'easy-digital-downloads' ); ?></strong></th>
								<th><strong><?php _e( 'Data Preview', 'easy-digital-downloads' ); ?></strong></th>
							</tr>
							</thead>
							<tbody>
							<tr>
								<td><?php _e( 'Currency Code', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[currency]" class="edd-import-csv-column"
											data-field="Currency">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Email', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[email]" class="edd-import-csv-column"
											data-field="Email">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Name', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[name]" class="edd-import-csv-column"
											data-field="Name">
										<option
											value=""><?php esc_html_e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php esc_html_e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'First Name', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[first_name]" class="edd-import-csv-column"
											data-field="First Name">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Last Name', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[last_name]" class="edd-import-csv-column"
											data-field="Last Name">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Customer ID', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[customer_id]" class="edd-import-csv-column"
											data-field="Customer ID">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Discount Code(s)', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[discounts]" class="edd-import-csv-column"
											data-field="Discount Code">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'IP Address', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[ip]" class="edd-import-csv-column"
											data-field="IP Address">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Mode (Live|Test)', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[mode]" class="edd-import-csv-column"
											data-field="Mode (Live|Test)">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Parent Payment ID', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[parent_payment_id]" class="edd-import-csv-column"
											data-field="">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Payment Method', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[gateway]" class="edd-import-csv-column"
											data-field="Payment Method">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Payment Number', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[number]" class="edd-import-csv-column"
											data-field="Payment Number">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Date', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[date]" class="edd-import-csv-column"
											data-field="Date">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Purchase Key', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[key]" class="edd-import-csv-column"
											data-field="Purchase Key">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Purchased Product(s)', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[downloads]" class="edd-import-csv-column"
											data-field="Products (Raw)">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Status', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[status]" class="edd-import-csv-column"
											data-field="Status">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Subtotal', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[subtotal]" class="edd-import-csv-column"
											data-field="">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Tax', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[tax]" class="edd-import-csv-column"
											data-field="Tax ($)">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Total', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[total]" class="edd-import-csv-column"
											data-field="Amount ($)">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Transaction ID', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[transaction_id]" class="edd-import-csv-column"
											data-field="Transaction ID">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'User', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[user_id]" class="edd-import-csv-column"
											data-field="User">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Address Line 1', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[line1]" class="edd-import-csv-column"
											data-field="Address">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Address Line 2', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[line2]" class="edd-import-csv-column"
											data-field="Address (Line 2)">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'City', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[city]" class="edd-import-csv-column"
											data-field="City">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'State / Province', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[state]" class="edd-import-csv-column"
											data-field="State">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Zip / Postal Code', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[zip]" class="edd-import-csv-column"
											data-field="Zip / Postal Code">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Country', 'easy-digital-downloads' ); ?></td>
								<td>
									<select name="edd-import-field[country]" class="edd-import-csv-column"
											data-field="Country">
										<option
											value=""><?php _e( '- Ignore this field -', 'easy-digital-downloads' ); ?></option>
									</select>
								</td>
								<td class="edd-import-preview-field"><?php _e( '- select field to preview data -', 'easy-digital-downloads' ); ?></td>
							</tr>
							</tbody>
						</table>
						<p class="submit">
							<button
								class="button edd-import-proceed button-primary"><?php esc_html_e( 'Process Import', 'easy-digital-downloads' ); ?></button>
						</p>
					</div>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->

		<?php
		do_action( 'cp_library_tools_import_export_after' );
	}

	public function get_tools_tabs() {
		static $tabs = array();

		// Set tabs if empty
		if ( empty( $tabs ) ) {

			// Define all tabs
			$tabs = array(
//				'system_info'   => __( 'System Info', 'easy-digital-downloads' ),
'import_export' => __( 'Import/Export', 'easy-digital-downloads' )
			);

		}

		// Filter & return
		return apply_filters( 'cp_library_tools_tabs', $tabs );
	}

	public function tools_sysinfo_display() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>

		<div class="postbox">
			<h3><span><?php esc_html_e( 'System Information', 'easy-digital-downloads' ); ?></span></h3>
			<div class="inside">
				<p>
					<?php esc_html_e( 'Use the system information below to help troubleshoot problems.', 'easy-digital-downloads' ); ?>
				</p>

				<form id="edd-system-info"
					  action="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-tools&tab=system_info' ) ); ?>"
					  method="post" dir="ltr">
				<textarea readonly="readonly" onclick="this.focus(); this.select()" id="system-info-textarea"
						  class="edd-tools-textarea" name="edd-sysinfo" style="width:100%;height: 70vh"
				><?php // echo $this->tools_sysinfo_get(); ?></textarea>

					<p>
						<input type="hidden" name="edd-action" value="download_sysinfo"/>
						<?php
						submit_button( __( 'Download System Info File', 'easy-digital-downloads' ), 'primary', 'edd-download-sysinfo', false );
						submit_button( __( 'Copy to Clipboard', 'easy-digital-downloads' ), 'secondary edd-inline-button', 'edd-copy-system-info', false, array( 'onclick' => "this.form['edd-sysinfo'].focus();this.form['edd-sysinfo'].select();document.execCommand('copy');return false;" ) );
						?>
					</p>
				</form>
			</div>
		</div>

		<?php
	}

}
