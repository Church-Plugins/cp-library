<?php
/**
 * Adapter class
 * Imports data from external sources and adds it to CP Library
 *
 * @package CP_Library
 * @since 1.3.0
 */

namespace CP_Library\Adapters;

use ChurchPlugins\Exception;
use CP_Library\Admin\Settings;
use CP_Library\Models\Table;
use CP_Library\Models\Item;

/**
 * Adapter class
 * Imports data from external sources and adds it to CP Library
 *
 * @package CP_Library
 * @since 1.3.0
 */
abstract class Adapter extends \WP_Background_Process {
	/**
	 * Used to identify the adapter
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The display name of the adapter
	 *
	 * @var string
	 */
	public $display_name;

	/**
	 * The items to process
	 *
	 * @var array|null
	 */
	protected $items;

	/**
	 * The item attachments to process
	 *
	 * @var array|null
	 */
	protected $attachments;

	/**
	 * The string to use for the cron pull
	 *
	 * @var string
	 */
	public $_cron_hook;

	/**
	 * Dispatcher
	 *
	 * @var Dispatcher
	 */
	protected $dispatcher;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->action     = "cpl_{$this->type}_adapter";
		$this->_cron_hook = "cpl_adapter_cron_{$this->type}";
		$this->dispatcher = new Dispatcher( "cpl_dispatcher_{$this->type}" );

		parent::__construct();

		if ( $this->is_enabled() ) {
			$this->actions();
		}
	}

	/**
	 * Actions
	 */
	public function actions() {
		add_action( $this->_cron_hook, array( $this, 'update_check' ) );
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( "cpl_adapter_pull_{$this->type}", array( $this, 'update_check' ) );
		add_action( "cpl_adapter_import_{$this->type}", array( $this, 'do_full_import' ) );
		add_filter( "cpl_dispatcher_{$this->type}_make_request", [ $this, 'fetch_batch' ], 10, 2 );
		add_action( "cpl_dispatcher_{$this->type}_done", [ $this, 'fetch_complete' ], 10, 2 );
	}

	/**
	 * Format and start processing items
	 *
	 * @param array $items The items to format and process.
	 * @return void
	 */
	abstract public function format_and_process( $items );

	/**
	 * Get next batch of items to process
	 *
	 * @param int $batch The batch number to get the next batch for.
	 * @return array|false The next batch of items to process, or false if there are no more items to process.
	 */
	abstract public function get_next_batch( $batch );

	/**
	 * Get most recently updated items
	 *
	 * @param int $amount The amount of items to get.
	 * @return array
	 */
	abstract public function get_recent_items( $amount );

	/**
	 * Get model based on key
	 *
	 * @param string $key The key to get the model for.
	 */
	abstract public function get_model_from_key( $key );

	/**
	 * Handles adding an attachment to a Sermon. Implemented by child classes
	 *
	 * @param Item   $item The item being processed.
	 * @param mixed  $attachment The attachment to add.
	 * @param string $attachment_key The attachment key.
	 */
	abstract public function add_attachment( $item, $attachment, $attachment_key );

	/**
	 * Process cpl_data
	 *
	 * @param Table  $item The item to process.
	 * @param array  $cpl_data The cpl_data to process.
	 * @param string $post_type The post type of the item being processed.
	 */
	abstract public function process_cpl_data( $item, $cpl_data, $post_type );

	/**
	 * Updates when the cron runs
	 *
	 * @return void
	 */
	public function update_check() {
		$is_json_request = isset( $_SERVER['CONTENT_TYPE'] ) && strpos( $_SERVER['CONTENT_TYPE'], 'application/json' ) === 0; // phpcs:ignore

		$amount = absint( $this->get_setting( 'check_count', 50 ) );

		try {
			$items = $this->get_recent_items( $amount );
			$this->format_and_process( $items );
			if ( $is_json_request ) {
				wp_send_json_success( array( 'message' => 'Sermons updated' ) );
			}
		} catch ( \ChurchPlugins\Exception | \Exception $e ) {
			cp_library()->logging->log( $e->getMessage() . ' ' . $e->getTraceAsString(), true );
			if ( $is_json_request ) {
				wp_send_json_error( array( 'error' => $e->getMessage() ) );
			}
		}
	}

	/**
	 * Handles a full import
	 *
	 * @return void
	 */
	public function do_full_import() {
		$this->delete_all(); // delete any queued items

		$this->dispatcher->set_batch( 1 )->dispatch(); // start the fetching process

		// delete store data
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'cpl_{$this->type}_adapter_store_%'" );

		update_option( "cpl_{$this->type}_adapter_import_complete", false );
		update_option( "cpl_{$this->type}_adapter_import_in_progress", true );

		wp_send_json_success( array( 'message' => 'Import started' ) );
	}

	/**
	 * Runs when all fetching with dispatcher is complete.
	 *
	 * @param int $batch The batch number.
	 * @return void
	 */
	public function fetch_complete() {
		update_option( "cpl_{$this->type}_adapter_import_complete", true );
		update_option( "cpl_{$this->type}_adapter_import_in_progress", false );
	}

	/**
	 * Process a batch
	 *
	 * @param bool $done Whether the batch is done or not.
	 * @param int  $batch The batch number.
	 * @return bool Whether we can stop fetching batches.
	 */
	public function fetch_batch( $done, $batch ) {

		try {
			$batch = $this->get_next_batch( $batch );

			if ( ! $batch ) {
				return true;
			}

			$this->format_and_process( $batch );
		} catch ( \ChurchPlugins\Exception | \Exception $e ) {
			cp_library()->logging->log( $e->getMessage() . ' ' . $e->getTraceAsString(), true );
			return true;
		}

		return $done;
	}

	/**
	 * Schedule the cron
	 */
	public function schedule_cron() {
		if ( wp_next_scheduled( $this->_cron_hook ) ) {
			return;
		}

		/**
		 * The default cron aruments
		 *
		 * @since 1.3.0
		 *
		 * @param array $args {
		 *  @type int $timestamp The timestamp to start the cron
		 *  @type string $recurrence The recurrence of the cron
		 * }
		 */
		$args = apply_filters(
			"cpl_adapter_{$this->type}_cron_args",
			array(
				'timestamp'  => time(),
				'recurrence' => $this->get_setting( 'update_check', 'hourly' ),
			)
		);

		wp_schedule_event( $args['timestamp'], $args['recurrence'], $this->_cron_hook );
	}

	/**
	 * Load an item into the database
	 *
	 * @template T of Table
	 *
	 * @param array           $item The formatted item to load.
	 * @param class-string<T> $model The CP Library model to use.
	 * @throws Exception If the item could not be loaded.
	 *
	 * @return T the created item
	 * @since 1.3.0
	 */
	public function load_item( $item, $model ) {
		$external_id      = $item['external_id'];
		$existing_item_id = $this->get_item_id_from_external( $external_id );

		if ( null !== $existing_item_id ) {
			$item['ID'] = $existing_item_id;
		}

		$cpl_data = $item['cpl_data'] ?? array();

		unset( $item['external_id'] );
		unset( $item['cpl_data'] );

		$post_id = wp_insert_post( $item, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( esc_html( $post_id->get_error_message() ) );
		}

		update_post_meta( $post_id, 'external_id', $external_id );

		if ( isset( $cpl_data['featured_image'] ) ) {
			$this->maybe_sideload_thumbnail( $cpl_data['featured_image'], $post_id );
		}

		// Will throw an error if invoked incorrectly.
		$item_model = $model::get_instance_from_origin( $post_id );

		// Gives our tables the same metadata as the post.
		if ( ! empty( $item['meta_input'] ) ) {
			foreach ( $item['meta_input'] as $key => $value ) {
				$item_model->update_meta_value( $key, $value );
			}
		}

		$this->process_cpl_data( $item_model, $cpl_data, $item['post_type'] );

		return $item_model;
	}

	/**
	 * Process items retrieved from the API
	 *
	 * @since 1.3.0
	 */
	public function process_batch() {
		$this->items = apply_filters( 'cpl_adapter_process_items', $this->items, $this );

		if ( empty( $this->items ) ) {
			return;
		}

		foreach ( $this->attachments as $attachment_key => $attachments ) {
			$attachments = apply_filters( 'cpl_adapter_process_items', $attachments, $this );
			$this->add_items_to_queue( $attachments, $attachment_key );
		}

		$this->add_items_to_queue( $this->items, 'items' );

		$this->items       = null;
		$this->attachments = null;

		$this->save()->dispatch();
	}

	/**
	 * Adds items to the queue and updates the store
	 *
	 * @param array  $items The items to add to the queue.
	 * @param string $store_key The store key to use.
	 */
	public function add_items_to_queue( $items, $store_key ) {
		$store_data   = $this->get_store( $store_key );
		$needs_update = false;

		foreach ( $items as $item ) {
			$store = $store_data[ $item['external_id'] ] ?? false;

			if ( $this->create_store_key( $item ) !== $store ) {
				$item = apply_filters( "cpl_{$this->type}_adapter_item", $item, $this );
				if ( ! empty( $item ) ) {
					$this->push_to_queue( $item );
				}
				$needs_update = true;
			}

			unset( $store_data[ $item['external_id'] ] );
		}

		if ( $needs_update ) {
			$this->update_store( $items, $store_key );
		}
	}

	/**
	 * Processes an item that has been formatted by the adapter
	 *
	 * @param array $item_data The item data to process.
	 * @return mixed
	 * @since 1.3.0
	 * @author Jonathan Roley
	 */
	public function task( $item_data ) {
		if ( empty( $item_data ) ) {
			error_log( 'Item was empty' );
			return false;
		}

		try {
			$model = $this->get_model_from_key( $item_data['post_type'] );

			$attachments = isset( $item_data['attachments'] ) ? $item_data['attachments'] : false;

			unset( $item_data['attachments'] );

			$item = $this->load_item( $item_data, $model );

			if ( empty( $attachments ) ) {
				return false;
			}

			// process attachments.
			foreach ( $attachments as $attachment_key => $attachment ) {
				$this->process_attachment( $item, $attachment, $attachment_key );
			}
		} catch ( Exception | \Throwable | \TypeError | \Exception $e ) {
			error_log( "Error when loading item: {$model}" . $e->getMessage() );
		}

		return false;
	}

	/**
	 * Remove an item from the database
	 *
	 * @param int $external_id The external ID of the item to remove.
	 * @since 1.3.0
	 */
	public function remove_item( $external_id ) {
		$id = $this->get_item_id_from_external( $external_id );

		$thumbnail_id = get_post_thumbnail_id( $id );
		if ( $thumbnail_id ) {
			wp_delete_attachment( $thumbnail_id, true );
		}

		wp_delete_post( $id, true );
	}

	/**
	 * Get the post associated with the provided item
	 *
	 * @param string|int $external_id The external ID.
	 * @return string|null
	 * @since  1.3.0
	 */
	public function get_item_id_from_external( $external_id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'external_id' AND meta_value = %s", $external_id ) );
	}

	/**
	 * Process attachments
	 *
	 * @param mixed  $item The item being processed.
	 * @param mixed  $attachment The attachments being processed.
	 * @param string $attachment_key The attachment key.
	 */
	public function process_attachment( $item, $attachment, $attachment_key ) {
		if ( empty( $attachment ) ) {
			return;
		}
		if ( is_array( $attachment ) ) {
			foreach ( $attachment as $single_attachment ) {
				$this->process_attachment( $item, $single_attachment, $attachment_key );
			}
			return;
		}
		$model     = $this->get_model_from_key( $attachment_key );
		$origin_id = $this->get_item_id_from_external( $attachment );

		if ( ! $origin_id ) {
			error_log( "Origin ID not found for {$attachment_key} {$attachment}. This means this attachment has not yet been processed." );
			return;
		}

		$attachment_model = $model::get_instance_from_origin( $origin_id );
		$this->add_attachment( $item, $attachment_model, $attachment_key );
	}

	/**
	 * Enqueues items to be processed
	 *
	 * @param array $items The items to enqueue.
	 */
	public function enqueue( $items ) {
		$this->items = $items;
	}

	/**
	 * Adds attachments to be processed
	 *
	 * @param array  $attachments The attachments to add.
	 * @param string $attachment_key The attachment key.
	 */
	public function add_attachments( $attachments, $attachment_key ) {
		$this->attachments[ $attachment_key ] = $attachments;
	}

	/**
	 * Get hashed values from last pull
	 *
	 * @param string $type The store key.
	 * @return array
	 * @since 1.3.0
	 */
	public function get_store( $type ) {
		return get_option( "cpl_{$this->type}_adapter_store_{$type}", [] );
	}

	/**
	 * Update the store with new values
	 *
	 * @param array  $items The items to update the store with.
	 * @param string $type The store key.
	 * @since 1.3.0
	 */
	public function update_store( $items, $type ) {
		$store = $this->get_store( $type );

		foreach ( $items as $item ) {
			$store[ $item['external_id'] ] = $this->create_store_key( $item );
		}

		update_option( "cpl_{$this->type}_adapter_store_{$type}", $store, false );
	}

	/**
	 * Create a store key from the hashed value of an item
	 *
	 * @param mixed $item The formatted item to create a store key for.
	 * @return string
	 * @since 1.3.0
	 */
	public function create_store_key( $item ) {
		return md5( serialize( $item ) );
	}

	/**
	 * Whether the adapter is enabled or not
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) Settings::get_advanced( "cpl_{$this->type}_adapter_enabled", false );
	}

	/**
	 * Create CMB2 Options page
	 */
	public function options_page() {
		$args = array(
			'id'           => "cpl_{$this->type}_adapter_options",
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => "cpl_{$this->type}_adapter_options",
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => $this->display_name,
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$cmb = new_cmb2_box( $args );

		$this->register_settings( $cmb );
	}

	/**
	 * Register settings
	 *
	 * @param CMB2 $cmb The CMB2 object to register settings fields with.
	 * @since 1.3.0
	 */
	public function register_settings( $cmb ) {

		$cmb->add_field(
			array(
				'name' => __( 'API Key', 'cp-library' ),
				'id'   => 'api_key',
				'type' => 'text',
				'desc' => __( 'Enter your API Key here. You can find this at <a href="http://www.sermonaudio.com/members">sermonaudio.com/members</a>.', 'cp-library' ),
			)
		);

		$cmb->add_field(
			array(
				'name' => __( 'Broadcaster ID', 'cp-library' ),
				'id'   => 'broadcaster_id',
				'type' => 'text',
				'desc' => __( 'Enter your broadcaster ID from Sermon Audio here.', 'cp-library' ),
			)
		);

		$cmb->add_field(
			array(
				'name' => sprintf( __( 'Ignore %s Before', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
				'id'   => 'ignore_before',
				'type' => 'text_date',
				'desc' => sprintf( __( 'Ignore all %s before specified date.', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
			)
		);

		$import_in_progress = get_option( "cpl_{$this->type}_adapter_import_in_progress", false );
		$cmb->add_field(
			array(
				'name'       => __( 'Start full import', 'cp-library' ),
				'id'         => 'start_initial_import',
				'type'       => 'cpl_submit_button',
				'desc'       => __( 'Save any unsaved changes before running.', 'cp-library' ),
				'query_args' => array(
					'cp_action' => "cpl_adapter_import_{$this->type}",
				),
				'disabled'   => $import_in_progress,
			)
		);

		$cron_schedules   = wp_get_schedules();
		$schedule_options = array();
		foreach ( $cron_schedules as $key => $schedule ) {
			$schedule_options[ $key ] = $schedule['display'];
		}

		$cmb->add_field(
			array(
				'name'    => __( 'Update Check', 'cp-library' ),
				'desc'    => sprintf( __( 'The interval at which to check for updated %s.', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
				'id'      => 'update_check',
				'type'    => 'select',
				'options' => $schedule_options,
				'default' => 'twicedaily',
			)
		);

		$cmb->add_field(
			array(
				'name'       => __( 'Check Now', 'cp-library' ),
				'id'         => 'check_now',
				'type'       => 'cpl_submit_button',
				'desc'       => sprintf( __( 'Check for new %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
				'query_args' => array(
					'cp_action' => "cpl_adapter_pull_{$this->type}",
				),
			)
		);

		$cmb->add_field(
			array(
				'name'       => __( 'Check Count', 'cp-library' ),
				'desc'       => __( 'The number of sermons to check for updates each time the cron runs.', 'cp-library' ),
				'id'         => 'check_count',
				'type'       => 'text_small',
				'default'    => 50,
				'attributes' => array(
					'min'  => 1,
					'max'  => 100,
					'step' => 1,
					'type' => 'number',
				),
			)
		);
	}

	/**
	 * Returns a namespaced option id
	 *
	 * @param string $key The key to namespace.
	 */
	public function option_id( $key ) {
		return "cpl_{$this->type}_adapter_{$key}";
	}

	/**
	 * Get a setting for this adapter
	 *
	 * @param string $key The key to get the setting for.
	 * @param mixed  $default The default value to return if the setting is not found.
	 */
	public function get_setting( $key, $default = '' ) {
		return Settings::get( $key, $default, "cpl_{$this->type}_adapter_options" );
	}


	/**
	 * Upload and attach a featured image to a post
	 *
	 * @param string $url The URL of the image to upload.
	 * @param int    $post_id The post ID to attach the image to.
	 */
	public function maybe_sideload_thumbnail( $url, $post_id ) {
		global $wpdb;

		$existing_attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'external_url' AND meta_value = %s", $url ) );

		if ( $existing_attachment_id && get_post( $existing_attachment_id ) ) {
			set_post_thumbnail( $post_id, $existing_attachment_id );
			return;
		}

		$filename    = basename( $url );
		$wp_filetype = wp_check_filetype( $filename, null );

		if ( strpos( $wp_filetype['type'], 'image/' ) === false ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$image_data = wp_remote_get( $url );
		$image_data = wp_remote_retrieve_body( $image_data );

		if ( empty( $image_data ) ) {
			return;
		}

		if ( wp_mkdir_p( $upload_dir['path'] ) ) {
			$file = $upload_dir['path'] . '/' . $filename;
		} else {
			$file = $upload_dir['basedir'] . '/' . $filename;
		}

		file_put_contents( $file, $image_data );

		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );

		if ( is_wp_error( $attach_id ) ) {
			return;
		}

		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

		wp_update_attachment_metadata( $attach_id, $attach_data );
		set_post_thumbnail( $post_id, $attach_id );
		update_post_meta( $attach_id, 'external_url', $url );
	}
}
