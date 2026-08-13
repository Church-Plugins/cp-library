<?php
/**
 * Admin UI for the full-site Export/Import engine.
 *
 * Renders two postboxes on the Tools → Import/Export tab (via the
 * cp_library_tools_import_export_after action, so Tools.php is untouched) and
 * powers them with nonce-protected AJAX endpoints:
 *
 * - Export: streams a gzipped NDJSON download in a single request.
 * - Import: uploads the file to a protected working directory, then processes
 *   it in bounded AJAX ticks so 10,000+ sermons never hit a PHP timeout.
 *
 * @package CP_Library
 * @since   1.6.3
 */

namespace CP_Library\Admin\ImportExport;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Admin
 *
 * @since 1.6.3
 */
class Admin {

	/**
	 * Option storing resumable import state between AJAX ticks.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'cpl_migration_import_state';

	/**
	 * Nonce action shared by the migration endpoints.
	 *
	 * @var string
	 */
	const NONCE = 'cpl_migration_tools';

	/**
	 * Records processed per AJAX tick.
	 *
	 * @var int
	 */
	const TICK_RECORDS = 100;

	/**
	 * Class instance
	 *
	 * @var Admin
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Admin
	 *
	 * @return Admin
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Admin ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		add_action( 'cp_library_tools_import_export_after', array( $this, 'render' ) );
		add_action( 'wp_ajax_cpl_migration_export', array( $this, 'handle_export' ) );
		add_action( 'wp_ajax_cpl_migration_upload', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_cpl_migration_tick', array( $this, 'handle_tick' ) );
		add_action( 'wp_ajax_cpl_migration_cancel', array( $this, 'handle_cancel' ) );
	}

	/**
	 * Render the migration postboxes and their inline JS.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state = get_option( self::STATE_OPTION );
		$nonce = wp_create_nonce( self::NONCE );
		?>

		<div class="postbox">
			<h3><span><?php esc_html_e( 'Full Migration Export', 'cp-library' ); ?></span></h3>
			<div class="inside">
				<p><?php esc_html_e( 'Download everything CP Sermon Library knows about — sermons (with variations, timestamps and transcripts), series, speakers, service types, templates and taxonomy terms — as a single portable file for importing into another WordPress site.', 'cp-library' ); ?></p>
				<p>
					<label>
						<input type="checkbox" id="cpl-migration-export-settings" />
						<?php esc_html_e( 'Include plugin settings', 'cp-library' ); ?>
					</label>
				</p>
				<p>
					<button type="button" class="button button-primary" id="cpl-migration-export"><?php esc_html_e( 'Download Export File', 'cp-library' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'For very large libraries (roughly 5,000+ sermons) the WP-CLI command is recommended: wp cpl export sermons.ndjson.gz', 'cp-library' ); ?></p>
			</div>
		</div>

		<div class="postbox">
			<h3><span><?php esc_html_e( 'Full Migration Import', 'cp-library' ); ?></span></h3>
			<div class="inside">
				<p><?php esc_html_e( 'Import a migration file exported from another site. Content is matched by its original ID, so importing the same file twice updates instead of duplicating. The import runs in small batches — leave this page open until it completes.', 'cp-library' ); ?></p>

				<div id="cpl-migration-import-form" <?php echo $state ? 'style="display:none;"' : ''; ?>>
					<p><input type="file" id="cpl-migration-file" accept=".gz,.ndjson" /></p>
					<p>
						<label>
							<input type="checkbox" id="cpl-migration-download-media" />
							<?php esc_html_e( 'Download media files into this site (slower; without it, media keeps pointing at the source site)', 'cp-library' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="checkbox" id="cpl-migration-match-slug" />
							<?php esc_html_e( 'Update existing content that shares a URL slug', 'cp-library' ); ?>
						</label>
						<br />
						<span class="description"><?php esc_html_e( 'Only for re-importing content that originally came from the source site. Existing posts matching an imported slug are overwritten in place — title, content, dates and meta — and this cannot be undone. Leave off to import them as new posts.', 'cp-library' ); ?></span>
					</p>
					<p>
						<button type="button" class="button button-primary" id="cpl-migration-import"><?php esc_html_e( 'Upload and Import', 'cp-library' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'For very large libraries the WP-CLI command is recommended: wp cpl import sermons.ndjson.gz', 'cp-library' ); ?></p>
				</div>

				<div id="cpl-migration-progress" <?php echo $state ? '' : 'style="display:none;"'; ?>>
					<p>
						<strong id="cpl-migration-status"><?php echo $state ? esc_html__( 'An import is in progress. Click Resume to continue it.', 'cp-library' ) : ''; ?></strong>
					</p>
					<p>
						<?php if ( $state ) : ?>
							<button type="button" class="button button-primary" id="cpl-migration-resume"><?php esc_html_e( 'Resume Import', 'cp-library' ); ?></button>
						<?php endif; ?>
						<button type="button" class="button" id="cpl-migration-cancel"><?php esc_html_e( 'Cancel Import', 'cp-library' ); ?></button>
					</p>
				</div>
			</div>
		</div>

		<script>
		(function($){
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var ticking = false;

			$('#cpl-migration-export').on('click', function(){
				var url = ajaxurl + '?action=cpl_migration_export&nonce=' + encodeURIComponent(nonce);
				if ( $('#cpl-migration-export-settings').is(':checked') ) {
					url += '&include_settings=1';
				}
				window.location = url;
			});

			function setStatus(text){ $('#cpl-migration-status').text(text); }

			function showProgress(){
				$('#cpl-migration-import-form').hide();
				$('#cpl-migration-progress').show();
			}

			function tick(){
				if ( ticking ) { return; }
				ticking = true;

				$.post(ajaxurl, { action: 'cpl_migration_tick', nonce: nonce })
					.done(function(resp){
						ticking = false;

						if ( ! resp || ! resp.success ) {
							setStatus(resp && resp.data && resp.data.message ? resp.data.message : <?php echo wp_json_encode( __( 'Import failed. Check the Tools → Log tab.', 'cp-library' ) ); ?>);
							return;
						}

						var d = resp.data;

						if ( d.done ) {
							setStatus(d.message);
							$('#cpl-migration-cancel, #cpl-migration-resume').hide();
							return;
						}

						setStatus(d.message);
						tick();
					})
					.fail(function(){
						ticking = false;
						// Transient failure (proxy timeout etc.) — retry after a pause.
						setStatus(<?php echo wp_json_encode( __( 'Connection hiccup — retrying…', 'cp-library' ) ); ?>);
						setTimeout(tick, 4000);
					});
			}

			$('#cpl-migration-import').on('click', function(){
				var file = $('#cpl-migration-file')[0].files[0];

				if ( ! file ) {
					window.alert(<?php echo wp_json_encode( __( 'Choose an export file first.', 'cp-library' ) ); ?>);
					return;
				}

				var data = new FormData();
				data.append('action', 'cpl_migration_upload');
				data.append('nonce', nonce);
				data.append('file', file);
				data.append('download_media', $('#cpl-migration-download-media').is(':checked') ? 1 : 0);
				data.append('match_by_slug', $('#cpl-migration-match-slug').is(':checked') ? 1 : 0);

				showProgress();
				setStatus(<?php echo wp_json_encode( __( 'Uploading…', 'cp-library' ) ); ?>);

				$.ajax({ url: ajaxurl, method: 'POST', data: data, processData: false, contentType: false })
					.done(function(resp){
						if ( ! resp || ! resp.success ) {
							setStatus(resp && resp.data && resp.data.message ? resp.data.message : <?php echo wp_json_encode( __( 'Upload failed.', 'cp-library' ) ); ?>);
							return;
						}
						tick();
					})
					.fail(function(){ setStatus(<?php echo wp_json_encode( __( 'Upload failed.', 'cp-library' ) ); ?>); });
			});

			$('#cpl-migration-resume').on('click', function(){
				$(this).hide();
				tick();
			});

			$('#cpl-migration-cancel').on('click', function(){
				if ( ! window.confirm(<?php echo wp_json_encode( __( 'Cancel this import? Content already imported will remain.', 'cp-library' ) ); ?>) ) {
					return;
				}

				$.post(ajaxurl, { action: 'cpl_migration_cancel', nonce: nonce }).always(function(){
					window.location.reload();
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Verify the request nonce + capability, ending the request on failure.
	 *
	 * @return void
	 */
	protected function verify_request() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cp-library' ) ), 403 );
		}
	}

	/**
	 * Stream a full export as a file download.
	 *
	 * @return void
	 */
	public function handle_export() {
		$this->verify_request();

		Util::cleanup_stale_files();

		$dir = Util::get_working_dir();

		if ( is_wp_error( $dir ) ) {
			wp_die( esc_html( $dir->get_error_message() ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$file = $dir . '/export-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false ) . '.ndjson.gz';

		$exporter = new Exporter(
			array(
				'include_settings' => ! empty( $_GET['include_settings'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in verify_request().
			)
		);

		try {
			$exporter->export_to_file( $file );
		} catch ( \Exception $e ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			wp_die( esc_html( $e->getMessage() ) );
		}

		// The client can abort mid-download, which kills the script inside readfile()
		// before anything after it runs. A shutdown function still fires, so the file —
		// a complete dump of the library, and the plugin's options when settings are
		// included — never survives in uploads.
		register_shutdown_function(
			function () use ( $file ) {
				if ( file_exists( $file ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		);

		nocache_headers();
		header( 'Content-Type: application/gzip' );
		header( 'Content-Disposition: attachment; filename="cp-library-export-' . gmdate( 'Ymd' ) . '.ndjson.gz"' );
		header( 'Content-Length: ' . (string) filesize( $file ) );

		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Receive the uploaded export file and initialize import state.
	 *
	 * @return void
	 */
	public function handle_upload() {
		$this->verify_request();

		if ( get_option( self::STATE_OPTION ) ) {
			wp_send_json_error( array( 'message' => __( 'An import is already in progress. Cancel it before starting another.', 'cp-library' ) ) );
		}

		// A partially-received upload (UPLOAD_ERR_PARTIAL) still leaves a readable file
		// in tmp_name, and a truncated gzip stream ends without raising anything — the
		// import would stop early and report success. Trust the error code first.
		$upload_error = isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( UPLOAD_ERR_OK !== $upload_error ) {
			wp_send_json_error( array( 'message' => $this->upload_error_message( $upload_error ) ) );
		}

		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_send_json_error( array( 'message' => __( 'No file received. The file may exceed the server upload limit — use the WP-CLI command instead.', 'cp-library' ) ) );
		}

		Util::cleanup_stale_files();

		$dir = Util::get_working_dir();

		if ( is_wp_error( $dir ) ) {
			wp_send_json_error( array( 'message' => $dir->get_error_message() ) );
		}

		$upload = $dir . '/import-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false ) . '.upload';
		$file   = $dir . '/import-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false ) . '.ndjson';

		if ( ! move_uploaded_file( $_FILES['file']['tmp_name'], $upload ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.VIP.FileSystemWritesDisallow.file_ops_move_uploaded_file
			wp_send_json_error( array( 'message' => __( 'Could not store the uploaded file.', 'cp-library' ) ) );
		}

		$expected = isset( $_FILES['file']['size'] ) ? (int) $_FILES['file']['size'] : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $expected && filesize( $upload ) !== $expected ) {
			unlink( $upload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			wp_send_json_error( array( 'message' => __( 'The upload was incomplete. Try again, or use the WP-CLI command for large files.', 'cp-library' ) ) );
		}

		// Peek at the header before unpacking, so a file that isn't an export doesn't
		// get inflated onto disk first.
		if ( ! $this->peek_is_export( $upload ) ) {
			unlink( $upload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			wp_send_json_error( array( 'message' => __( 'This file is not a CP Library export.', 'cp-library' ) ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$total = $this->inflate_to_working_file( $upload, $file );

		unlink( $upload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		if ( is_wp_error( $total ) ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}

			wp_send_json_error( array( 'message' => $total->get_error_message() ) );
		}

		// Validate: first line must be a CP Library export header.
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$header = $handle ? json_decode( trim( (string) fgets( $handle, 1048576 ) ), true ) : null;

		if ( $handle ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		if ( empty( $header['format'] ) || Util::FORMAT !== $header['format'] ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			wp_send_json_error( array( 'message' => __( 'This file is not a CP Library export.', 'cp-library' ) ) );
		}

		if ( (int) $header['format_version'] > Util::FORMAT_VERSION ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			wp_send_json_error( array( 'message' => __( 'This export was created by a newer version of CP Sermon Library. Update this site first.', 'cp-library' ) ) );
		}

		update_option(
			self::STATE_OPTION,
			array(
				'file'           => $file,
				'download_media' => ! empty( $_POST['download_media'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in verify_request().
				'match_by_slug'  => ! empty( $_POST['match_by_slug'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in verify_request().
				'offset'         => 0, // Byte offset into the decompressed working file.
				'processed'      => 0,
				'total'          => $total,
				'importer'       => array(),
			),
			false
		);

		wp_send_json_success( array( 'total' => $total ) );
	}

	/**
	 * Whether a file's first line is a CP Library export header.
	 *
	 * @param string $file Absolute path (gzipped or plain).
	 * @return bool
	 */
	protected function peek_is_export( $file ) {
		$handle = gzopen( $file, 'rb' );

		if ( ! $handle ) {
			return false;
		}

		$header = json_decode( trim( (string) gzgets( $handle, 1048576 ) ), true );
		gzclose( $handle );

		return ! empty( $header['format'] ) && Util::FORMAT === $header['format'];
	}

	/**
	 * Decompress the upload into the plain-text working file and count its records.
	 *
	 * Ticks resume by byte offset. gzseek() emulates seeking by re-inflating
	 * everything before the target, which makes a resumable import quadratic in file
	 * size — a 10,000-sermon file spends more time seeking than importing and
	 * eventually times out on every tick. Inflating once up front makes each resume
	 * an O(1) fseek().
	 *
	 * The pass also verifies the archive is whole: gzip stores the uncompressed
	 * length in its trailer, so a mismatch means the file was truncated and the
	 * import must not run and report success over a partial library.
	 *
	 * @param string $source Uploaded file (gzipped or plain NDJSON).
	 * @param string $target Working file to write.
	 * @return int|\WP_Error Line count, or an error.
	 */
	protected function inflate_to_working_file( $source, $target ) {
		$is_gzip = Util::is_gzip( $source );
		$in      = $is_gzip ? gzopen( $source, 'rb' ) : fopen( $source, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out     = fopen( $target, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $in || ! $out ) {
			if ( $in ) {
				$is_gzip ? gzclose( $in ) : fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}

			if ( $out ) {
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}

			return new \WP_Error( 'cpl_import_unreadable', __( 'Could not read the uploaded file.', 'cp-library' ) );
		}

		$lines = 0;
		$bytes = 0;

		while ( true ) {
			$line = $is_gzip ? gzgets( $in, 16777216 ) : fgets( $in, 16777216 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets

			if ( false === $line ) {
				break;
			}

			$bytes += strlen( $line );
			$lines++;

			if ( false === fwrite( $out, $line ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				$is_gzip ? gzclose( $in ) : fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

				return new \WP_Error( 'cpl_import_disk', __( 'Ran out of space while unpacking the import file.', 'cp-library' ) );
			}
		}

		$is_gzip ? gzclose( $in ) : fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! $lines ) {
			return new \WP_Error( 'cpl_import_empty', __( 'The import file is empty.', 'cp-library' ) );
		}

		if ( ! Util::gzip_is_complete( $source, $bytes ) ) {
			return new \WP_Error(
				'cpl_import_truncated',
				__( 'The import file is incomplete — it was cut short in transfer or when it was created. Re-download the export and try again.', 'cp-library' )
			);
		}

		return $lines;
	}

	/**
	 * Human-readable message for a PHP upload error code.
	 *
	 * @param int $code One of the UPLOAD_ERR_* constants.
	 * @return string
	 */
	protected function upload_error_message( $code ) {
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'The file is larger than this server allows. Use the WP-CLI command instead: wp cpl import sermons.ndjson.gz', 'cp-library' );

			case UPLOAD_ERR_PARTIAL:
				return __( 'Only part of the file arrived. Try again, or use the WP-CLI command for large files.', 'cp-library' );

			case UPLOAD_ERR_NO_FILE:
				return __( 'No file received. Choose an export file and try again.', 'cp-library' );

			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'The server could not write the upload to disk. Check the temporary directory permissions.', 'cp-library' );

			default:
				return __( 'The upload failed. Try again, or use the WP-CLI command instead.', 'cp-library' );
		}
	}

	/**
	 * Process one bounded batch of records and persist resumable state.
	 *
	 * @return void
	 */
	public function handle_tick() {
		$this->verify_request();

		$state = get_option( self::STATE_OPTION );

		if ( empty( $state['file'] ) || ! file_exists( $state['file'] ) ) {
			delete_option( self::STATE_OPTION );
			wp_send_json_error( array( 'message' => __( 'No import in progress (the working file is missing).', 'cp-library' ) ) );
		}

		// The working file is plain NDJSON (handle_upload() inflated it), so resuming is
		// a real seek rather than a re-inflate of everything before the offset.
		$handle = fopen( $state['file'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			wp_send_json_error( array( 'message' => __( 'Could not open the import file.', 'cp-library' ) ) );
		}

		if ( $state['offset'] > 0 && -1 === fseek( $handle, $state['offset'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_send_json_error( array( 'message' => __( 'Could not seek in the import file.', 'cp-library' ) ) );
		}

		$importer = new Importer(
			array(
				'download_media' => ! empty( $state['download_media'] ),
				'match_by_slug'  => ! empty( $state['match_by_slug'] ),
				'batch_size'     => self::TICK_RECORDS,
				'logger'         => function ( $message, $level ) {
					if ( 'warning' === $level && function_exists( 'cp_library' ) && isset( cp_library()->logging ) ) {
						cp_library()->logging->log( 'Migration import: ' . $message );
					}
				},
			)
		);
		$importer->set_state( is_array( $state['importer'] ) ? $state['importer'] : array() );
		$importer->begin();

		$count = 0;
		$error = '';

		try {
			while ( $count < self::TICK_RECORDS && false !== ( $line = fgets( $handle, 16777216 ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets
				$importer->process_line( $line );
				$count++;
			}
		} catch ( \Exception $e ) {
			// Invalid header — unusable file.
			$error = $e->getMessage();
		} finally {
			$state['offset'] = (int) ftell( $handle );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$importer->end();
		}

		if ( $error ) {
			unlink( $state['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			delete_option( self::STATE_OPTION );
			wp_send_json_error( array( 'message' => $error ) );
		}

		$state['processed'] += $count;
		$state['importer']   = $importer->get_state();

		if ( $count < self::TICK_RECORDS ) {
			// Reached EOF — done.
			unlink( $state['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			delete_option( self::STATE_OPTION );

			wp_send_json_success(
				array(
					'done'    => true,
					'message' => sprintf(
						/* translators: 1: record count, 2: error count. */
						__( 'Import complete — %1$d records processed, %2$d errors. Details are in Tools → Log.', 'cp-library' ),
						$state['processed'],
						$importer->get_error_count()
					),
				)
			);
		}

		update_option( self::STATE_OPTION, $state, false );

		wp_send_json_success(
			array(
				'done'    => false,
				'message' => sprintf(
					/* translators: 1: processed count, 2: total count. */
					__( 'Importing… %1$d of %2$d records.', 'cp-library' ),
					$state['processed'],
					$state['total']
				),
			)
		);
	}

	/**
	 * Cancel an in-progress import and remove its working file.
	 *
	 * @return void
	 */
	public function handle_cancel() {
		$this->verify_request();

		$state = get_option( self::STATE_OPTION );

		if ( ! empty( $state['file'] ) && file_exists( $state['file'] ) ) {
			unlink( $state['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		delete_option( self::STATE_OPTION );

		wp_send_json_success();
	}
}
