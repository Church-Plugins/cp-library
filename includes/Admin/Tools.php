<?php

namespace CP_Library\Admin;

use CP_Library\Controllers\Item;

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
		add_action( 'cp_batch_import_class_include', [ $this, 'include_class' ] );
		add_filter( 'cp_importer_is_class_allowed', [ $this, 'importer_class' ] );
		add_filter( 'upload_mimes', [ $this, 'import_mime_type' ] );
		add_action( 'cp_export_messages', [ $this, 'export_data' ] );
	}

	public function import_mime_type( $existing_mimes ) {
		$existing_mimes['csv'] = 'text/csv';

		return $existing_mimes;
	}

	public function importer_class( $classes ) {
		$classes[] = '\CP_Library\Admin\Import\ImportSermons';
		return $classes;
	}

	public function include_class() {
		require_once( 'Import/ImportSermons.php' );
	}

	public function tools_menu() {
		$post_type = cp_library()->setup->post_types->item_type_enabled() ? cp_library()->setup->post_types->item_type->post_type : cp_library()->setup->post_types->item->post_type;

		add_submenu_page( 'edit.php?post_type=' . $post_type, __( 'CP Sermon Library Tools', 'cp-library' ), __( 'Tools', 'cp-library' ), 'manage_options', 'cp-library-tools', [
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
		
		// wp_enqueue_script( 'cp-admin-tools' );

		if ( 'import_export' === $active_tab ) {
			wp_enqueue_script( 'cp-admin-tools-import' );
			// wp_enqueue_script( 'cp-admin-tools-export' );
		}
		?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Tools', 'cp-library' ); ?></h1>
			<hr class="wp-header-end">

			<nav class="nav-tab-wrapper cp-nav-tab-wrapper"
				 aria-label="<?php esc_attr_e( 'Secondary menu', 'cp-library' ); ?>">
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

		<div class="postbox cp-import-payment-history">
			<h3><span><?php esc_html_e( 'Import Sermons', 'cp-library' ); ?></span></h3>
			<div class="inside">
				<p><?php esc_html_e( 'Import a CSV file of sermons.', 'cp-library' ); ?></p>
				<form id="cp-import-sermons" class="cp-import-form cp-import-export-form"
					  action="<?php echo esc_url( add_query_arg( 'cp_action', 'cp_upload_import_file', admin_url() ) ); ?>"
					  method="post" enctype="multipart/form-data">

					<div class="cp-import-file-wrap">
						<?php wp_nonce_field( 'cp_ajax_import', 'cp_ajax_import' ); ?>
						<input type="hidden" name="cp-import-class" value="\CP_Library\Admin\Import\ImportSermons"/>
						<p>
							<input name="cp-import-file" id="cp-payments-import-file" type="file"/>
						</p>
						<span>
						<input type="submit" value="<?php _e( 'Import CSV', 'cp-library' ); ?>"
							   class="button-secondary"/>
						<span class="spinner"></span>
					</span>
					</div>

					<div class="cp-import-options" id="cp-import-payments-options" style="display:none;">

						<p>
							<?php
							printf(
								__( 'Each column loaded from the CSV needs to be mapped to an order field. Select the column that should be mapped to each field below. Any columns not needed can be ignored. See <a href="%s" target="_blank">this guide</a> for assistance with importing payment records.', 'cp-library' ),
								'https://docs.easydigitaldownloads.com/category/1337-importexport'
							);
							?>
						</p>

						<table class="widefat edd_repeatable_table striped" width="100%" cellpadding="0"
							   cellspacing="0">
							<thead>
							<tr>
								<th><strong><?php _e( 'Message Field Field', 'cp-library' ); ?></strong></th>
								<th><strong><?php _e( 'CSV Column', 'cp-library' ); ?></strong></th>
								<th><strong><?php _e( 'Data Preview', 'cp-library' ); ?></strong></th>
							</tr>
							</thead>
							<tbody>
							<tr>
								<td><?php _e( 'Title', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[title]" class="cp-import-csv-column"
											data-field="Title">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Description', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[description]" class="cp-import-csv-column"
											data-field="Description">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>

							<tr>
								<td><?php _e( 'Date', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[date]" class="cp-import-csv-column"
											data-field="Date">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>

							<?php if ( cp_library()->setup->post_types->item_type_enabled() ) : ?>
								<tr>
									<td><?php esc_html_e( 'Series', 'cp-library' ); ?></td>
									<td>
										<select name="cp-import-field[series]" class="cp-import-csv-column"
												data-field="Series">
											<option
												value=""><?php esc_html_e( '- Ignore this field -', 'cp-library' ); ?></option>
										</select>
									</td>
									<td class="cp-import-preview-field"><?php esc_html_e( '- select field to preview data -', 'cp-library' ); ?></td>
								</tr>
							<?php endif; ?>

							<?php if ( function_exists( 'cp_locations' ) ) : ?>
								<tr>
									<td><?php _e( 'Location', 'cp-library' ); ?></td>
									<td>
										<select name="cp-import-field[location]" class="cp-import-csv-column"
												data-field="Location">
											<option
												value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
										</select>
									</td>
									<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
								</tr>
							<?php endif; ?>

							<?php if ( cp_library()->setup->post_types->speaker_enabled() ) : ?>
								<tr>
									<td><?php _e( 'Speaker', 'cp-library' ); ?></td>
									<td>
										<select name="cp-import-field[speaker]" class="cp-import-csv-column"
												data-field="Speaker">
											<option
												value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
										</select>
									</td>
									<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
								</tr>
							<?php endif; ?>

							<?php if ( cp_library()->setup->post_types->service_type_enabled() ) : ?>
								<tr>
									<td><?php _e( 'Service Type', 'cp-library' ); ?></td>
									<td>
										<select name="cp-import-field[service_type]" class="cp-import-csv-column"
												data-field="Service Type">
											<option
												value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
										</select>
									</td>
									<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
								</tr>
							<?php endif; ?>

							<tr>
								<td><?php _e( 'Topics', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[topics]" class="cp-import-csv-column"
											data-field="Topics">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Season', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[season]" class="cp-import-csv-column"
											data-field="Season">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Scripture', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[scripture]" class="cp-import-csv-column"
											data-field="Scripture">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Thumbnail', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[thumbnail]" class="cp-import-csv-column"
											data-field="Thumbnail">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Video', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[video]" class="cp-import-csv-column"
											data-field="Video">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Audio', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[audio]" class="cp-import-csv-column"
											data-field="Audio">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Study Guide', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[study_guide]" class="cp-import-csv-column"
											data-field="Study Guide">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Passage', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[passage]" class="cp-import-csv-column"
											data-field="Passage">
										<option
											value=""><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							</tbody>
						</table>
						<p class="submit">
							<button
								class="button cp-import-proceed button-primary"><?php esc_html_e( 'Process Import', 'cp-library' ); ?></button>
						</p>
					</div>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->

		<div class="postbox cp-import-payment-history">
			<h3><span><?php esc_html_e( 'Export data', 'cp-library' ) ?></span></h3>
			<div class="inside">

				<?php $action_url = esc_url( add_query_arg( 'cp_action', 'cp_export_messages', admin_url() ) ); ?>
				<form id="cpl_export_data" action="<?php echo $action_url ?>" method="POST" enctype="multipart/form-data">
					<button class="button button-primary"><?php esc_html_e( 'Export all messages as CSV', 'cp-library' ); ?></button>
				</form>
				
			</div>
		</div>

		<?php
		do_action( 'cp_library_tools_import_export_after' );
	}

	public function get_tools_tabs() {
		static $tabs = array();

		// Set tabs if empty
		if ( empty( $tabs ) ) {

			// Define all tabs
			$tabs = array(
				// 'system_info'   => __( 'System Info', 'cp-library' ),
				'import_export' => __( 'Import/Export', 'cp-library' )
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
			<h3><span><?php esc_html_e( 'System Information', 'cp-library' ); ?></span></h3>
			<div class="inside">
				<p>
					<?php esc_html_e( 'Use the system information below to help troubleshoot problems.', 'cp-library' ); ?>
				</p>

				<form id="cp-system-info"
					  action="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=cp-tools&tab=system_info' ) ); ?>"
					  method="post" dir="ltr">
				<textarea readonly="readonly" onclick="this.focus(); this.select()" id="system-info-textarea"
						  class="cp-tools-textarea" name="cp-sysinfo" style="width:100%;height: 70vh"
				><?php // echo $this->tools_sysinfo_get(); ?></textarea>

					<p>
						<input type="hidden" name="cp-action" value="download_sysinfo"/>
						<?php
						submit_button( __( 'Download System Info File', 'cp-library' ), 'primary', 'cp-download-sysinfo', false );
						submit_button( __( 'Copy to Clipboard', 'cp-library' ), 'secondary cp-inline-button', 'cp-copy-system-info', false, array( 'onclick' => "this.form['cp-sysinfo'].focus();this.form['cp-sysinfo'].select();document.execCommand('copy');return false;" ) );
						?>
					</p>
				</form>
			</div>
		</div>

		<?php
	}

	/**
	 * Exports all messages when form is submitted
	 * @return void
	 * @since 1.0.4
	 * @author Jonathan Roley
	 */
	public function export_data() {
		$return_value = [];

		$args = [
			'post_type'      =>  CP_LIBRARY_UPREFIX . "_item",
			'post_status'    => array('publish','private','draft','future'),
			'posts_per_page' => -1,
		];

		$posts = new \WP_Query( $args );

		$file_handle = fopen('php://memory', 'w');

		$header_added = false;

		foreach( $posts->posts as $post ) {

			try {
				$item = new Item( $post->ID );

				$data = $item->get_api_data();

				$formatted_data = $this->get_formatted_item( $data );

				if( ! $header_added ) {
					fputcsv($file_handle, array_keys($formatted_data));
					$header_added = true;
				}

				fputcsv($file_handle, $formatted_data);
			} catch ( \Exception $e ) {
				$return_value['error'] = $e->getMessage();
				error_log( $e->getMessage() );
			}
		}

		header("Content-Type: text/csv");
		header('Content-Disposition: attachment; filename=sermons.csv');

		rewind($file_handle);
    fpassthru($file_handle);

		// Close the file handle
    fclose($file_handle);

    // Exit to prevent WordPress from rendering anything else
    exit();
	}

	/**
	 * Formats data for exporting as CSV
	 * @param array $data
	 * @return array
	 * @since 1.0.4
	 */
	public function get_formatted_item( $data ) {

		$series = implode(',', array_map( function( $series) {
			return $series['title'];
		}, $data['types'] ) );

		$scripture = implode( ',', array_map( function( $scripture ) {
			return $scripture['name'];
		}, $data['scripture'] ) );

		$topics = implode( ',', array_map( function( $topic ) {
			return $topic['name'];
		}, $data['topics'] ) );

		$speakers = implode( ',', array_map( function( $speaker ) {
			return $speaker['title'];
		}, $data['speakers'] ) );

		$locations = implode( ',', array_map( function( $location ) {
			return $location['name'];
		}, $data['locations'] ) );

		$service_types = implode( ',', array_map( function( $type ) {
			return $type['title'];
		}, $data['service_types'] ) );

		$seasons = implode( ',', array_map( function( $season ) {
			return $season['name'];
		}, $data['seasons'] ) );

		return array(
			'Title'        => $data['title'],
			'Description'  => $data['desc'],
			'Series'       => $series,
			'Date'         => $data['date']['timestamp'],
			'Passage'      => $data['passage'],
			'Location'     => $locations,
			'Service_type' => $service_types,
			'Speaker'      => $speakers,
			'Topics'       => $topics,
			'Season'       => $seasons,
			'Scripture'    => $scripture,
			'Thumbnail'    => $data['thumb'],
			'Video'        => $data['video']['value'],
			'Audio'        => $data['audio'],
		);
	}
}
