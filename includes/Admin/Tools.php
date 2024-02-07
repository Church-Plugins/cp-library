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
		add_action( 'cp_export_items', [ $this, 'export_data' ] );
		add_action( 'wp_ajax_cpl_merge_speakers', [ $this, 'merge_speakers' ] );
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
		$post_type = Settings::get_advanced( 'default_menu_item', 'item_type' ) === 'item_type' ? cp_library()->setup->post_types->item_type->post_type : cp_library()->setup->post_types->item->post_type;

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
			<h3><span><?php echo sprintf( esc_html__( 'Import %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label); ?></span></h3>
			<div class="inside">
				<p><?php echo sprintf( __( 'Import a CSV file of %s. <a href="%s">Download a sample csv file.</a>', 'cp-library' ), cp_library()->setup->post_types->item->plural_label, CP_LIBRARY_PLUGIN_URL . '/templates/__sample/import-sermons.csv'); ?></p>
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
//							printf(
//								__( 'Each column loaded from the CSV needs to be mapped to an order field. Select the column that should be mapped to each field below. Any columns not needed can be ignored. See <a href="%s" target="_blank">this guide</a> for assistance with importing payment records.', 'cp-library' ),
//								'https://docs.easydigitaldownloads.com/category/1337-importexport'
//							);
							_e( 'Each column loaded from the CSV needs to be mapped to an order field. Select the column that should be mapped to each field below. Any columns not needed can be ignored.', 'cp-library' );

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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
												value="" selected><?php esc_html_e( '- Ignore this field -', 'cp-library' ); ?></option>
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
												value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
												value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
										</select>
									</td>
									<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
								</tr>
							<?php endif; ?>

							<?php if ( cp_library()->setup->post_types->service_type_enabled() && cp_library()->setup->variations->get_source() != cp_library()->setup->post_types->service_type->post_type ) : ?>
								<tr>
									<td><?php _e( 'Service Type', 'cp-library' ); ?></td>
									<td>
										<select name="cp-import-field[service_type]" class="cp-import-csv-column"
												data-field="Service Type">
											<option
												value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
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
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Downloads', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[downloads]" class="cp-import-csv-column"
											data-field="Downloads">
										<option
											value="" selected><?php _e( '- Ignore this field -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Variation', 'cp-library' ); ?></td>
								<td>
									<select name="cp-import-field[variation]" class="cp-import-csv-column"
											data-field="Variation">
										<option
											value="" selected><?php _e( '- Do not detect Variations -', 'cp-library' ); ?></option>
									</select>
								</td>
								<td class="cp-import-preview-field"><?php _e( '- select field to preview data -', 'cp-library' ); ?></td>
							</tr>


							</tbody>
						</table>

						<h4><span><?php esc_html_e( 'Additional Options', 'cp-library' ); ?></span></h4>
						<p>
							<input type='checkbox' id='sideload-audio-urls' name='sideload-audio-urls' checked>
							<label for='sideload-audio-urls'><?php esc_html_e( 'Attempt to import mp3 files to the Media Library', 'cp-library' ); ?></label>
						</p>

						<p>
							<input type='checkbox' id='sideload-downloads' name='sideload-downloads' checked>
							<label for='sideload-downloads'><?php esc_html_e( 'Attempt to import downloadable files to the Media Library', 'cp-library' ); ?></label>
						</p>

						<p>
							<input type='checkbox' id='stop-on-error' name='stop-on-error' checked>
							<label for='stop-on-error'><?php esc_html_e( 'Stop if the importer encounters an error', 'cp-library' ); ?></label>
						</p>

						<?php do_action( 'cp_library_tools_import_additional_options' ); ?>

						<p class="submit">
							<button class="button cp-import-proceed button-primary"><?php esc_html_e( 'Process Import', 'cp-library' ); ?></button>
						</p>
					</div>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->

		<div class="postbox cp-import-payment-history">
			<h3><span><?php esc_html_e( 'Export data', 'cp-library' ) ?></span></h3>
			<div class="inside">

				<?php $action_url = esc_url( add_query_arg( 'cp_action', 'cp_export_items', admin_url() ) ); ?>
				<form id="cpl_export_data" action="<?php echo $action_url ?>" method="POST" enctype="multipart/form-data">
					<button class="button button-primary"><?php echo sprintf( esc_html__( 'Export all %s as CSV', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ); ?></button>
				</form>

			</div>
		</div>

		<div class="postbox">
			<h3><span><?php esc_html_e( 'Merge Duplicate Speakers', 'cp-library' ); ?></span></h3>
			<div class="inside">
				<p><?php esc_html_e( 'Remove duplicate speakers, transferring sermons to a single speaker.', 'cp-library' ); ?></p>
				<div>
					<?php $ajaxurl = esc_url( admin_url( 'admin-ajax.php' ) ); ?>
					<?php $nonce = wp_create_nonce( 'cpl_merge_speakers' ); ?>
					<button data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="button button-primary" id="cpl_merge_speakers"><?php esc_html_e( 'Merge Speakers', 'cp-library' ); ?></button>
				</div>
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
	 * Exports all Sermons when form is submitted
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

		$upload_dir = wp_upload_dir();
		// WP-CLI may need to find a fallback directory
		if( empty( $upload_dir ) || empty( $upload_dir['path'] ) ) {
			$upload_dir['path'] = dirname( __FILE__ );
		} else {
			wp_mkdir_p( $upload_dir['path'] );
		}

		$filename = sanitize_file_name( sprintf( "%s_" . date( 'Y-m-d' ) . ".csv", cp_library()->setup->post_types->item->plural_label ) );
		$file_path = trailingslashit( $upload_dir['path'] ) . $filename;
		$file_handle = fopen( $file_path, 'w');

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

		fclose($file_handle);

		header("Content-Type: text/csv; charset=utf-8");
		header( "Content-disposition: attachment; filename=\"" . $filename . "\"" );

		if ( isset( $headers['content-length'] ) ) {
			header( "Content-Length: " . $headers['content-length'] );
		}

		session_write_close();
		readfile( rawurldecode( $file_path ) );

    exit();
	}

	/**
	 * Formats data for exporting as CSV
	 * @param array $data
	 * @return array
	 * @since 1.0.4
	 */
	public function get_formatted_item( $data ) {

		$strings = array(
			'types'        => 'title',
			'scripture'     => 'name',
			'topics'        => 'name',
			'speakers'      => 'title',
			'locations'     => 'title',
			'service_types' => 'title',
			'seasons'       => 'name',
		);

		$values = [];

		foreach ( $strings as $string => $type ) {
			$values[ $string ] = '';

			if ( ! empty( $data[ $string ] ) ) {
				$values[ $string ] = $this->get_csv_string( $data[ $string ], $type );
			}
		}

		$downloads = '';

		if ( ! empty( $data['downloads'] ) ) {
			$downloads = [];
			foreach ( $data['downloads'] as $download ) {
				if ( empty( $download['name'] ) ) {
					$download['name'] = '';
				}

				$downloads[] = $download['name'] . '|' . $download['file'];
			}

			$downloads = implode( ',', $downloads );
		}

		extract( $values );

		return array(
			'Title'        => $data['title'],
			'Description'  => $data['desc'],
			'Series'       => $types,
			'Date'         => $data['date']['timestamp'],
			'Passage'      => $data['passage'],
			'Location'     => $locations,
			'Service Type' => $service_types,
			'Speaker'      => $speakers,
			'Topics'       => $topics,
			'Season'       => $seasons,
			'Scripture'    => $scripture,
			'Thumbnail'    => $data['thumb'],
			'Video'        => $data['video']['value'],
			'Audio'        => $data['audio'],
			'Downloads'    => $downloads,
		);
	}

	/**
	 * Gets data from an array and converts it to a comma seperated string
	 *
	 * @param mixed $data
	 * @param string $key
	 * @return string
	 */
	public function get_csv_string( $data, $key ) {
		if( ! is_array( $data )  ) {
			$data = empty( $data ) ? array() : array( $data );
		}

		return html_entity_decode( implode( ',', wp_list_pluck( $data, $key ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Merge speakers
	 *
	 * @since 1.4.1
	 */
	public function merge_speakers() {
		global $wpdb;

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpl_merge_speakers' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid nonce.', 'cp-library' ) ) );
		}

		// get speakers with duplicate names
		$sql = $wpdb->prepare(
			"SELECT post_title, COUNT({$wpdb->posts}.ID) AS speaker_count
			FROM {$wpdb->posts}
			WHERE post_type='cpl_speaker'
			GROUP BY post_title
			HAVING speaker_count > 1"
		);

		$speakers = $wpdb->get_results( $sql );

		foreach ( $speakers as $speaker ) {
			$this->merge_speaker( $speaker->post_title );
		}

		$html = '<div>' . esc_html__( 'Speakers merged successfully.', 'cp-library' ) . '</div>';

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Merge a single speaker by name
	 *
	 * @param string $name The name of the speaker to merge.
	 * @since 1.4.1
	 */
	public function merge_speaker( $name ) {
		global $wpdb;
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name
				FROM {$wpdb->posts}
				WHERE post_type='cpl_speaker'
				AND post_title=%s",
				$name
			)
		);

		$callback = fn( $a, $b ) => $this->get_post_name_value( $a->post_name ) - $this->get_post_name_value( $b->post_name );

		usort( $posts, $callback );

		$first_speaker = array_shift( $posts );
		$first_speaker = \CP_Library\Models\Speaker::get_instance_from_origin( $first_speaker->ID );
		$first_post    = get_post( $first_speaker->origin_id );

		foreach ( $posts as $speaker ) {
			$post     = get_post( $speaker->ID );
			$speaker  = \CP_Library\Models\Speaker::get_instance_from_origin( $speaker->ID );
			$messages = $speaker->get_all_items();

			if ( empty( $first_post->post_content ) && ! empty( $post->post_content ) ) {
				$first_post->post_content = $post->post_content;
				wp_update_post( $first_post );
			}

			// copy over taxonomies
			$taxonomies = get_object_taxonomies( cp_library()->setup->post_types->speaker->post_type );
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_object_terms( $post->ID, $taxonomy );

				if ( ! empty( $terms ) ) {
					wp_set_object_terms( $first_post->ID, wp_list_pluck( $terms, 'term_id' ), $taxonomy, true );
				}
			}

			// copy over thumbnail if first speaker doesn't have one
			if ( ! get_post_thumbnail_id( $first_post ) ) {
				$thumbnail_id = get_post_thumbnail_id( $post->ID );

				if ( $thumbnail_id ) {
					set_post_thumbnail( $first_post, $thumbnail_id );
				}
			}

			foreach ( $messages as $message ) {
				try {
					$message = \CP_Library\Models\Item::get_instance_from_origin( absint( $message ) );
					$message_speakers   = $message->get_speakers();
					$message_speakers   = array_diff( $message_speakers, array( $speaker->id ) );
					$message_speakers[] = $first_speaker->id;
					$message->update_speakers( array_unique( $message_speakers ) );
				} catch ( \ChurchPlugins\Exception $e ) {
					continue;
				}
			}

			wp_delete_post( $speaker->origin_id, true );
		}
	}

	/**
	 * Returns an integer sort value for a post name.
	 * If the post name does not end with '-#', the sort value is -1.
	 * Otherwise, the sort value is the number after the dash.
	 *
	 * @param string $name The post name to sort.
	 * @return int
	 */
	public function get_post_name_value( $name ) {
		$match = preg_match( '/-(\d+)$/', $name, $matches );

		if ( $match ) {
			return intval( $matches[1] );
		}

		return -1;
	}
}
