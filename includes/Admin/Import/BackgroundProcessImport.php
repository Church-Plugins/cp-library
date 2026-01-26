<?php
/**
 * Utility class for handling background processing of imports.
 *
 * @package cp-library
 * @todo Potentially move to CP Core
 */

namespace CP_Library\Admin\Import;

use ChurchPlugins\Helpers;
use ChurchPlugins\Utils\WP_Background_Process;
use WP_Embed;
use WP_Error;

/**
 * Class BackgroundProcessImport
 */
abstract class BackgroundProcessImport extends WP_Background_Process {
	/**
	 * Prefix
	 *
	 * @var string
	 */
	protected $prefix = 'wp_cpl_import';

	/**
	 * Class key used for options & transients
	 *
	 * @var string
	 */
	public static $key;

	/**
	 * Stores options
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * Field mapping
	 *
	 * @var array
	 */
	protected $field_mapping = array();

	/**
	 * Accepted mime types
	 *
	 * @var array
	 */
	protected static $accepted_mime_types = [
		'text/csv',
		'text/comma-separated-values',
		'text/plain',
		'text/anytext',
		'text/*',
		'text/plain',
		'text/anytext',
		'text/*',
		'application/csv',
		'application/excel',
		'application/vnd.ms-excel',
		'application/vnd.msexcel',
	];

	public function __construct() {
		parent::__construct();

		$config = get_site_option( static::get_key() . '_config' );

		if ( $config ) {
			$this->load_mapping( $config['mapping'] );
			$this->load_options( $config['options'] );
		}

	}

	/**
	 * Load the field mapping
	 *
	 * @since 1.4.10
	 *
	 * @param array $mapping Field mapping.
	 */
	public function load_mapping( $mapping ) {
		$mapping = array_intersect_key( $mapping, $this->field_mapping ); // remove any invalid keys
		$this->field_mapping = array_merge( $this->field_mapping, $mapping );
	}

	/**
	 * Load the import options
	 *
	 * @since 1.4.10
	 *
	 * @param array $options Import options.
	 */
	public function load_options( $options ) {
		$this->options = array_merge( $this->options, $options );
	}

	/**
	 * Get the key
	 *
	 * @return string
	 */
	public static function get_key() {
		return 'cpl_import_' . static::$key;
	}

	/**
	 * Setup rest routes, actions, and filters
	 */
	public static function setup() {
		add_action( 'wp_ajax_cpl_import_' . static::$key, [ static::class, 'import' ] );
		add_action( 'wp_ajax_cpl_import_file_' . static::$key, [ static::class, 'import_upload' ] );
		add_action( 'rest_api_init', [ static::class, 'register_rest_routes' ] );
		static::load_running_processes();
	}

	/**
	 * Load running processes
	 */
	public static function load_running_processes() {
		$config = get_site_option( static::get_key() . '_config' );

		if ( ! $config ) {
			return;
		}

		$instance = new static();
		$instance->load_mapping( $config['mapping'] );
		$instance->load_options( $config['options'] );
		if ( ! $instance->is_active() ) {
			$instance->dispatch();
		}
	}

	/**
	 * Whether the import is currently in progress
	 *
	 * @since 1.4.10
	 *
	 * @return float|false
	 */
	public static function get_import_progress() {
		$current_amount = get_transient( static::get_key() . '_progress' ) ?: 0; // phpcs:ignore
		$total          = get_transient( static::get_key() . '_total' ); // phpcs:ignore

		if ( ! $total ) {
			return false;
		}

		return $current_amount / $total * 100;
	}

	/**
	 * Implement the completed callback
	 *
	 * @since 1.4.10
	 */
	protected function completed() {
		/**
		 * Complete the import of this post type.
		 *
		 * @since 1.4.10
		 */
		do_action( static::get_key() . '_complete' );
		static::clear_db();
	}

	/**
	 * Clear options and transients from database
	 */
	public static function clear_db() {
		delete_site_option( static::get_key() . '_config' );
		delete_transient( static::get_key() . '_progress' );
		delete_transient( static::get_key() . '_total' );
		delete_transient( static::get_key() . '_started_at' );
		delete_transient( static::get_key() . '_last_timestamp' );
	}

	/**
	 * Register REST routes
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'cp-library/v1',
			'/import/' . static::$key . '/progress',
			[
				'methods'             => 'GET',
				'callback'            => [ static::class, 'rest_import_progress' ],
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			]
		);

		register_rest_route(
			'cp-library/v1',
			'/import/' . static::$key . '/cancel',
			[
				'methods'             => 'POST',
				'callback'            => [ static::class, 'rest_import_cancel' ],
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			]
		);
	}

	/**
	 * AJAX import file upload endpoint
	 */
	public static function import_upload() {
		check_ajax_referer( 'cp_ajax_import_file', 'cp_ajax_import_file' );

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$file = $_FILES['file'] ?? null;
		if ( ! $file ) {
			wp_send_json_error( [ 'message' => __( 'Missing import file. Please provide an import file.', 'church-plugins' ) ], 400 );
		}

		if ( empty( $file['type'] ) || ! in_array( $file['type'], self::$accepted_mime_types, true ) ) {
			wp_send_json_error( [ 'message' => __( 'The file you uploaded does not appear to be a CSV file.', 'church-plugins' ) ], 400 );
		}

		if ( ! file_exists( $file['tmp_name'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Something went wrong during the upload process, please try again.', 'church-plugins' ) ], 500 );
		}

		$import_file = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( ! $import_file || ! empty(  $import_file['error'] ) ) {
			wp_send_json_error( [ 'message' => $import_file['error'] ], 500 );
		}

		wp_send_json_success(
			[
				'file_url'  => $import_file['url'],
				'first_row' => static::get_first_row( $import_file['file'] ),
				'columns'   => static::get_columns( $import_file['file'] ),
			]
		);
	}

	/**
	 * Ajax import endpoint
	 *
	 * @since 1.4.10
	 */
	public static function import() {
		check_ajax_referer( 'cp_ajax_import', 'cp_ajax_import' );

		if ( empty( $_POST['file_url'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing import file. Please provide an import file.', 'cp-library' ) ] );
		}

		$file = $_POST['file_url'];

		// check in our uploads directory based on file URL
		$upload_dir = wp_upload_dir();

		if ( false === strpos( $file, $upload_dir['baseurl'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid file URL.', 'cp-library' ) ] );
		}

		// get file from uploads and use
		$file = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $file );

		// check if file exists and has correct mime type
		if ( ! file_exists( $file ) || ! in_array( mime_content_type( $file ), self::$accepted_mime_types, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid file.', 'cp-library' ) ] );
		}

		$mapping = $_POST[ 'mapping' ] ?: [];
		$options = $_POST[ 'options' ] ?: [];

		$options = static::parse_options( $options );

		// start import
		static::start_import( $file, $mapping, $options );

		wp_send_json_success( [ 'message' => __( 'Import started.', 'cp-library' ) ] );
	}

	/**
	 * REST import progress endpoint
	 *
	 * @since 1.4.10
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function rest_import_progress( $request ) {
		$percentage   = static::get_import_progress();
		$started_at	  = get_transient( static::get_key() . '_started_at' ) ?: time();
		$total        = get_transient( static::get_key() . '_total' ) ?: 0;
		$progress     = get_transient( static::get_key() . '_progress' ) ?: 0;
		$time_elapsed = time() - $started_at;
		return rest_ensure_response(
			[
				'percentage_complete' => false === $percentage ? 0 : $percentage,
				'started_at'          => $started_at,
				'time_elapsed'        => $time_elapsed,
				'total'               => absint( $total ),
				'progress'            => absint( $progress ),
				'in_progress'         => false !== $percentage,
			]
		);
	}

	/**
	 * REST import cancel endpoint
	 *
	 * @since 1.4.10
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 * @throws \Exception If the import is not active.
	 */
	public static function rest_import_cancel( $request ) {
		$instance = new static();
		$instance->cancel();
		$instance->delete_all();
		static::clear_db();
		set_transient( static::get_key() . '_cancelled', true );
		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Import cancelled.', 'church-plugins' ),
			]
		);
	}

	/**
	 * Start the import process.
	 *
	 * @since 1.4.10
	 *
	 * @param string $file File to import.
	 * @param array  $mapping Field mapping.
	 * @param array  $options Import options.
	 * @return void
	 */
	public static function start_import( $file, $mapping, $options ) {
		cp_library()->logging->log( __( 'Starting import process', 'cp-library' ) );

		$handle  = fopen( $file, 'r' );
		$headers = fgetcsv( $handle );
		// Convert headers to UTF-8
		$headers = array_map( [ static::class, 'convert_to_utf8' ], $headers );

		/**
		 * Batch size for processing items
		 *
		 * @since 1.4.10
		 *
		 * @param int $batch_size Batch size.
		 */
		$batch_size = apply_filters( 'cpl_import_batch_size', 25 );

		$first_batch = null;
		$queue       = [];
		$total       = 0;
		while ( true ) {
			$data = fgetcsv( $handle );

			// batch items
			if ( false === $data || count( $queue ) >= $batch_size ) {
				$process = new static();
				$process->load_mapping( $mapping );
				$process->load_options( $options );
				foreach ( $queue as $item ) {
					$process->push_to_queue( $item );
				}
				$process->save();
				$queue = []; // clear the queue
				if ( ! $first_batch ) {
					$first_batch = $process;
				}
			}

			if ( false === $data ) {
				break;
			}

			// Convert data to UTF-8
			$data = array_map( [ static::class, 'convert_to_utf8' ], $data );

			$queue[] = array_combine( $headers, $data );

			$total++;
		}

		fclose( $handle );

		update_site_option( static::get_key() . '_config', [
			'mapping' => $mapping,
			'options' => $options,
		] );
		set_transient( static::get_key() . '_progress', 0 );
		set_transient( static::get_key() . '_total', $total );
		set_transient( static::get_key() . '_started_at', time() );
		set_transient( static::get_key() . '_last_timestamp', microtime( true ) );
		delete_transient( static::get_key() . '_cancelled' );

		// dispatch first batch
		if ( $first_batch ) {
			$first_batch->dispatch();
		}
	}

	/**
	 * Parse the item before sending it to be processed
	 *
	 * @since 1.4.10
	 *
	 * @param array $item Item to parse.
	 * @return array Parsed item.
	 */
	protected function parse_item( $item ) {
		$output = [];
		foreach ( $this->field_mapping as $key => $header ) {
			$output[ $key ] = $item[ $header ] ?? '';
		}
		return $output;
	}

	/**
	 * Single item import task
	 *
	 * @since 1.4.10
	 *
	 * @param array $item Item to process.
	 */
	protected function task( $item ) {
		if ( get_transient( static::get_key() . '_cancelled' ) ) {
			return false;
		}

		// import item
		try {
			$item = $this->parse_item( $item );

			$this->import_item( $item, $this->options );

			/**
			 * Fires after each item is processed during the import process.
			 *
			 * @param array $item Item being processed.
			 * @param array $options Import options.
			 * @param BackgroundProcessImport $this Import instance.
			 * @since 1.4.10
			 */
			do_action( 'cpl_import_process_item', $item, $this->options, $this );
		} catch ( \Exception $e ) {
			cp_library()->logging->log( $e->getMessage() );
			error_log( $e->getMessage() );
		}

		// update progress
		$current_amount = absint ( get_transient( static::get_key() . '_progress' ) ); // phpcs:ignore
		set_transient( static::get_key() . '_progress', $current_amount + 1 );

		// log time
		$last_timestamp = get_transient( static::get_key() . '_last_timestamp' ) ?: microtime( true );
		$milliseconds   = round( ( microtime( true ) - $last_timestamp ) * 1000 );
		// error_log( 'Imported item in ' . $milliseconds . ' milliseconds' );
		set_transient( static::get_key() . '_last_timestamp', microtime( true ) );

		return false;
	}

	/**
	 * Import a single item
	 *
	 * @since 1.4.10
	 *
	 * @param array $item Item to import.
	 * @param array $options Import options.
	 */
	abstract protected function import_item( $item, $options ): void;

	/**
	 * Parse the import options
	 *
	 * @since 1.4.10
	 *
	 * @param array $options Import options.
	 */
	protected static function parse_options( $options ) {
		$output = [];

		foreach ( $options as $key => $value ) {
			// checkboxes
			if ( 'on' === $value ) {
				$value = true;
			} elseif ( 'off' === $value ) {
				$value = false;
			}

			$output[ $key ] = $value;
		}

		return $output;
	}

	/**
	 * Set up and store the Featured Image
	 *
	 * @since  1.4.10
	 *
	 * @param int    $post_id Post ID to attach the image to.
	 * @param string $image Image URL or path.
	 * @param int    $post_author Post author ID.
	 * @return bool|int The attachment ID on success, false on failure.
	 * @author Tanner Moushey
	 */
	public function set_image( $post_id = 0, $image = '', $post_author = 0 ) {
		$upload_dir = wp_upload_dir();

		$image    = $this->maybe_find_local_file( $image );
		$ext      = Helpers::get_file_extension( $image );
		$is_url   = false !== filter_var( $image, FILTER_VALIDATE_URL );
		$is_local = $is_url && false !== strpos( $image, site_url() );

		if ( $is_url && $is_local ) {
			$attachment_id = attachment_url_to_postid( $image ); // Image given by URL, see if we have an attachment already
		} elseif ( $is_url ) {
			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Image given by external URL
			$url = media_sideload_image( $image, $post_id, '', 'src' );

			if ( ! is_wp_error( $url ) ) {
				$attachment_id = attachment_url_to_postid( $url );
			}
		} elseif ( false === strpos( $image, '/' ) && $ext && $file = $this->get_media_by_filename( $image ) ) { // phpcs:ignore

			// We found the file, let's see if it already exists in the media library
			$guid          = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file );
			$attachment_id = attachment_url_to_postid( $guid );

			// generate the attachment record
			if ( empty( $attachment_id ) ) {

				// Doesn't exist in the media library, let's add it

				$filetype = wp_check_filetype( basename( $file ), null );

				// Prepare an array of post data for the attachment.
				$attachment = array(
					'guid'           => $guid,
					'post_mime_type' => $filetype['type'],
					'post_title'     => preg_replace( '/\.[^.]+$/', '', $image ),
					'post_content'   => '',
					'post_status'    => 'inherit',
					'post_author'    => $post_author,
				);

				// Insert the attachment.
				$attachment_id = wp_insert_attachment( $attachment, $file, $post_id );

				// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
				require_once ABSPATH . 'wp-admin/includes/image.php';

				// Generate the metadata for the attachment, and update the database record.
				$attach_data = wp_generate_attachment_metadata( $attachment_id, $file );
				wp_update_attachment_metadata( $attachment_id, $attach_data );

			}
		}

		if ( ! empty( $attachment_id ) ) {
			return set_post_thumbnail( $post_id, $attachment_id );
		}

		return false;
	}

	/**
	 * Check to see if the provided file exists locally. Return the local file if found, or the original file if not.
	 *
	 * @since  1.4.10
	 *
	 * @param string $file File to check.
	 * @return array|mixed|string|string[]
	 * @author Tanner Moushey, 5/26/23
	 */
	public function maybe_find_local_file( $file ) {
		$upload_dir = wp_upload_dir();

		if ( ! Helpers::get_file_extension( $file ) ) {
			return $file;
		}

		$filename   = explode( '/', $file );
		$filename   = array_pop( $filename );
		$found_file = $this->get_media_by_filename( $filename );

		if ( $found_file ) {
			$found_file = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $found_file );

			if ( false !== strpos( site_url(), 'https' ) ) {
				$file = str_replace( 'http://', 'https://', $found_file );
			} else {
				$file = str_replace( 'https://', 'http://', $found_file );
			}
		}

		return $file;
	}

	/**
	 * Downloads and adds file to media library from url
	 *
	 * @since 1.4.10
	 *
	 * @param int    $post_id Post ID to attach the media to.
	 * @param string $media_url URL of the media to sideload.
	 * @return string|false The sideloaded media URL on success, the false on failure.
	 * @author Jonathan Roley
	 */
	public function sideload_media_and_get_url( $post_id = 0, $media_url = '' ) {
		$media_url = $this->maybe_find_local_file( $media_url );
		$is_url    = false !== filter_var( $media_url, FILTER_VALIDATE_URL );
		$is_local  = $is_url && false !== strpos( $media_url, site_url() );

		if ( $is_url && $is_local ) {

			// Image given by URL, see if we have an attachment already
			$attachment_id = attachment_url_to_postid( $media_url );

		} elseif ( $is_url ) {

			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$tmp = download_url( $media_url );

			if ( is_wp_error( $tmp ) ) {
				return $media_url;
			}

			$file_array = array(
				'name'     => basename( $media_url ),
				'tmp_name' => $tmp,
			);

			$media_id = media_handle_sideload( $file_array, $post_id );
			@unlink( $file_array['tmp_name'] );

			if ( is_wp_error( $media_id ) ) {
				return $media_url;
			}

			$attachment_id = $media_id;

		}

		if ( ! empty( $attachment_id ) ) {
			return wp_get_attachment_url( $attachment_id );
		}

		return false;
	}

	/**
	 * Look for the provided file in the uploads directory.
	 *
	 * @since  1.4.10
	 *
	 * @param string $filename Filename to search for.
	 * @return false|mixed|string
	 * @author Tanner Moushey, 5/26/23
	 */
	public function get_media_by_filename( $filename ) {
		$ext        = Helpers::get_file_extension( $filename );
		$upload_dir = wp_upload_dir();

		if ( file_exists( trailingslashit( $upload_dir['path'] ) . $filename ) ) {
			// Look in current upload directory first
			return trailingslashit( $upload_dir['path'] ) . $filename;
		} else {
			// Now look through year/month sub folders of upload directory for files with our image's same extension
			$files = glob( $upload_dir['basedir'] . '/*/*/*' . $ext );
			foreach ( $files as $file ) {
				// Found our file
				if ( basename( $file ) === $filename ) {
					return $file;
				}
			}
		}

		return false;
	}

	/**
	 * Get the first row of a CSV file
	 *
	 * @param string $file File to get the first row from.
	 * @return array
	 */
	public static function get_first_row( $file ) {
		$handle = fopen( $file, 'r' );
		$headers   = fgetcsv( $handle );
		$first_row = fgetcsv( $handle );
		// Convert to UTF-8
		$headers   = array_map( [ static::class, 'convert_to_utf8' ], $headers );
		$first_row = array_map( [ static::class, 'convert_to_utf8' ], $first_row );
		foreach ( $first_row as $key => $value ) {
			if ( is_string( $value ) ) {
				$value = strlen( $value ) > 250 ? wp_kses( substr( $value, 0, 250 ), [] ) . '...' : $value;
			}
			$first_row[ $headers[ $key ] ] = $value;
			unset( $first_row[ $key ] );
		}
		fclose( $handle );
		return $first_row;
	}

	/**
	 * Get the columns of a CSV file
	 *
	 * @param string $file File to get columns from.
	 * @return array
	 */
	public static function get_columns( $file ) {
		$handle = fopen( $file, 'r' );
		$row    = fgetcsv( $handle );
		fclose( $handle );
		// Convert to UTF-8
		return array_map( [ static::class, 'convert_to_utf8' ], $row );
	}

	/**
	 * Convert a string to UTF-8 encoding
	 *
	 * @since 1.6.0
	 * @param string $string String to convert
	 * @return string UTF-8 encoded string
	 */
	protected static function convert_to_utf8( $string ) {
		if ( empty( $string ) ) {
			return $string;
		}

		// Check if already valid UTF-8
		if ( mb_check_encoding( $string, 'UTF-8' ) ) {
			return $string;
		}

		// Try to detect the encoding
		$detected_encoding = mb_detect_encoding( $string, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true );

		// If we detected an encoding, convert it
		if ( $detected_encoding && $detected_encoding !== 'UTF-8' ) {
			$converted = mb_convert_encoding( $string, 'UTF-8', $detected_encoding );
			if ( $converted !== false ) {
				return $converted;
			}
		}

		// Fallback: try Windows-1252 (most common source of these issues)
		$converted = mb_convert_encoding( $string, 'UTF-8', 'Windows-1252' );
		if ( $converted !== false ) {
			return $converted;
		}

		// Last resort: use iconv if available
		if ( function_exists( 'iconv' ) ) {
			$converted = iconv( 'Windows-1252', 'UTF-8//TRANSLIT//IGNORE', $string );
			if ( $converted !== false ) {
				return $converted;
			}
		}

		// If all else fails, return the original string
		return $string;
	}
}
