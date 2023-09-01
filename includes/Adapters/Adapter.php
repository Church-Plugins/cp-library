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
	 * Class constructor
	 */
	public function __construct() {
		$this->action = "cpl_{$this->type}_adapter";
		$this->_cron_hook = "cpl_adapter_cron_{$this->type}";

		parent::__construct();

		if( $this->is_enabled() ) {
			$this->actions();
		}
	}

	/**
	 * Handles pulling data from API
	 *
	 * @param int $amount The number of items to pull.
	 * @param int $page The page to pull from.
	 */
	abstract public function pull( int $amount, int $page );

	/**
	 * Get model based on key
	 *
	 * @param string $key The key to get the model for.
	 */
	abstract public function get_model_from_key( $key );

	/**
	 * Handles adding an attachment to a Sermon. Implemted by child classes
	 *
	 * @param \CP_Library\Models\Item $item The item being processed.
	 * @param mixed                   $attachment The attachment to add.
	 * @param string                  $attachment_key The attachment key.
	 */
	abstract public function add_attachment( $item, $attachment, $attachment_key );


	/**
	 * Actions
	 */
	public function actions() {
		add_action( $this->_cron_hook, [ $this, 'update_check' ] );
		add_action( 'init', [ $this, 'schedule_cron' ] );
		add_action( "cpl_adapter_pull_{$this->type}", [ $this, 'update_check' ] );
		add_action( "cpl_adapter_hard_pull_{$this->type}", [ $this, 'hard_pull' ] );
	}

	/**
	 * Updates when the cron runs
	 *
	 * @return void
	 */
	public function update_check() {
		$is_json_request = isset( $_SERVER['CONTENT_TYPE'] ) && strpos( $_SERVER['CONTENT_TYPE'],  'application/json' ) === 0;

		$amount = absint( $this->get_setting( 'check_count', 50 ) );

		try {
			$this->pull( $amount, 1 );
			if( $is_json_request ) {
				wp_send_json_success( array( 'message' => 'Sermons updated' ) );
			}
		}
		catch ( \ChurchPlugins\Exception|\Exception $e ) {
			error_log( $e->getMessage() . ' ' . $e->getTraceAsString() );
			if( $is_json_request ) {
				wp_send_json_error( array( 'error' => $e->getMessage() ) );
			}
		}
	}

	/**
	 * Hard pull all items
	 * 
	 * @return void
	 */
	public function hard_pull() {
		// keep pulling until all items are saved
		try {
			$page = 1;
			$is_more = true;
			while( $is_more ) {
				$is_more = $this->pull( 100, $page );
				if( $page > 10 ) {
					wp_send_json_error( array( 'error' => 'Sermon count too large. ' . (($page - 1) * 100) . ' Sermons have been processed' ) );
					break;
				}
				$page++;
			}
			wp_send_json_success( array( 'message' => 'Update process started' ) );
		}
		catch( Exception $e ) {
			error_log( $e->getMessage() );
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Schedule the cron
	 */
	public function schedule_cron() {
		if( wp_next_scheduled( $this->_cron_hook ) ) {
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
		$args = apply_filters( "cpl_adapter_{$this->type}_cron_args", array(
			'timestamp'  => time(),
			'recurrence' => $this->get_setting( 'update_check', 'hourly' ),
		) );

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
		$external_id = $item['external_id'];
		$existing_item_id = $this->get_item_id_from_external( $external_id );

		if ( null !== $existing_item_id ) {
			$item['ID'] = $existing_item_id;
		}

		unset( $item['external_id'] );

		$post_id = wp_insert_post( $item, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		update_post_meta( $post_id, 'external_id', $external_id );

		// Will throw an error if invoked incorrectly.
		$item_model = $model::get_instance_from_origin( $post_id );

		// Gives our tables the same metadata as the post.
		if ( ! empty( $item['meta_input'] ) ) {
			foreach ( $item['meta_input'] as $key => $value ) {
				$item_model->update_meta_value( $key, $value );
			}
		}

		return $item_model;
	}

	/**
	 * Process items retrieved from the API
	 *
	 * @param bool $hard_pull Whether to do a full pull or not.
	 * @since 1.3.0
	 */
	public function process( $hard_pull = false ) {
		$this->delete_all();

		$items = apply_filters( "cpl_adapter_process_items", $this->items, $this );

		if( empty( $items ) ) {
			return;
		}

		// delete items not in the response
		foreach( $this->attachments as $attachment_key => $data ) {
			$attachment_store = $this->get_store( $attachment_key );

			foreach( $data as $attachment ) {
				$store = $attachment_store[ $attachment['external_id'] ] ?? false;

				if( $this->create_store_key( $attachment ) !== $store ) {
					$attachment = apply_filters( "cpl_{$this->type}_adapter_item", $attachment, $this );
					if( ! empty( $attachment ) ) {
						$this->push_to_queue( $attachment );
					}
				} 

				unset( $attachment_store[ $attachment['external_id'] ] );
			}

			if( $hard_pull ) {
				foreach( $attachment_store as $external_id => $hash ) {
					$this->remove_item( $external_id );
				}
			}
		
			$this->update_store( $data, $attachment_key );
		}

		$item_store = $this->get_store( 'items' );

		foreach( $items as $item ) {
			$store = $item_store[ $item['external_id'] ] ?? false;

			if( $this->create_store_key( $item ) !== $store ) {
				$item = apply_filters( "cpl_{$this->type}_adapter_item", $item, $this );
				if( ! empty( $item ) ) {
					$this->push_to_queue( $item );
				}
			}

			unset( $item_store[ $item['external_id'] ] );
		}

		if( $hard_pull ) {
			foreach( $item_store as $external_id => $hash ) {
				$this->remove_item( $external_id );
			}
		}

		$this->update_store( $items, 'items' );

		$this->items = null;
		$this->attachments = null;

		$this->save()->dispatch();
	}

	/**
	 * Processes an item that has been formatted by the adapter
	 * 
	 * @param array $itemdata
	 * @return void
	 * @since 1.3.0
	 * @author Jonathan Roley
	 */
	public function task( $item_data ) {

		if( empty( $item_data ) ) {
			error_log( "Item was empty" );
			return false;
		}

		try {
			$model = $this->get_model_from_key( $item_data[ 'post_type' ] );

			$attachments = isset( $item_data['attachments'] ) ? $item_data['attachments'] : false;
	
			unset( $item_data['attachments'] );
	
			$item = $this->load_item( $item_data, $model );
	
			if( empty( $attachments ) ) {
				return false;
			}
	
			// process attachments
			foreach( $attachments as $attachment_key => $attachment ) {
				$this->process_attachment( $item, $attachment, $attachment_key );
			}
		}
		catch( Exception|\Throwable|\TypeError|\Exception $e ) {
			error_log( "Error when loading item: {$model}" . $e->getMessage() );
		}

		return false;
	}

	/**
	 * Remove an item from the database
	 * 
	 * @param int $external_id
	 * @since 1.3.0
	 */
	public function remove_item( $external_id ) {
		$id = $this->get_item_id_from_external( $external_id );

		if( $thumbnail_id = get_post_thumbnail_id( $id ) ) {
			wp_delete_attachment( $thumbnail_id, true );
		}

		wp_delete_post( $id, true );
	}

	/**
	 * Get the post associated with the provided item
	 *
	 * @param $external_id
	 *
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
	 * @param mixed $item The item being processed
	 * @param mixed $attachments The attachments being processed
	 * @param string $attachment_key The attachment key
	 */
	public function process_attachment( $item, $attachment, $attachment_key ) {
		if( empty( $attachment ) ) {
			return;
		}
		if( is_array( $attachment ) ) {
			foreach( $attachment as $single_attachment ) {
				$this->process_attachment( $item, $single_attachment, $attachment_key );
			}
			return;
		}
		$model = $this->get_model_from_key( $attachment_key );
		$origin_id = $this->get_item_id_from_external( $attachment );

		if( ! $origin_id ) {
			error_log( "Origin ID not found for {$attachment_key} {$attachment}. This means this attachment has not yet been processed." );
			return;
		}

		$attachment_model = $model::get_instance_from_origin( $origin_id );
		$this->add_attachment( $item, $attachment_model, $attachment_key );
	}

	/**
	 * Enqueues items to be processed
	 * 
	 * @param array $items
	 */
	public function enqueue( $items ) {
		$this->items = $items;
	}

	/**
	 * Adds attachments to be processed
	 * 
	 * @param array $attachments
	 * @param string $attachment_key
	 */
	public function add_attachments( $attachments, $attachment_key ) {
		$this->attachments[ $attachment_key ] = $attachments;
	}

	/**
	 * Get hashed values from last pull
	 * 
	 * @param string $type the store type
	 * @return array;
	 * @since 1.3.0
	 */
	public function get_store( $type ) {
		return get_option( "cpl_{$this->type}_adapter_store_{$type}", [] );
	}

	/**
	 * Update the store with new values
	 * 
	 * @param array $items
	 * @since 1.3.0
	 */
	public function update_store( $items, $type ) {
		$store = [];

		foreach( $items as $item ) {
			$store[ $item['external_id'] ] = $this->create_store_key( $item );
		}

		update_option( "cpl_{$this->type}_adapter_store_{$type}", $store, false );
	}

	/**
	 * Create a store key from the hashed value of an item
	 * 
	 * @param mixed $item
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
		$cmb->add_field( array(
			'name' => __( 'API Key', 'cp-library' ),
			'id'   => $this->option_id( 'api_key' ),
			'type' => 'text',
			'desc' => __( 'Enter the API key from your Sermon Audio account here', 'cp-library' )
		) );

		$cron_schedules = wp_get_schedules();
		$schedule_options = array();
		foreach ( $cron_schedules as $key => $schedule ) {
			$schedule_options[ $key ] = $schedule['display'];
		}

		$cmb->add_field( array(
			'name'    => __( 'Update Check', 'cp-library' ),
			'desc'    => sprintf( __( 'The interval at which to check for updated %s.', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
			'id'      => $this->option_id( 'update_check' ),
			'type'    => 'select',
			'options' => $schedule_options,
			'default' => 'twicedaily'
		) );

		$cmb->add_field( array(
			'name' => __( 'Check Count', 'cp-library' ),
			'desc' => __( 'The number of sermons to check for updates each time the cron runs.', 'cp-library' ),
			'id'   => $this->option_id( 'check_count' ),
			'type' => 'text_small',
			'default' => 50,
			'attributes' => array(
				'min' => 1,
				'max' => 100,
				'step' => 1,
				'type' => 'number',
			)
		) );

		$cmb->add_field( array(
			'name'       => __( 'Check Now', 'cp-library' ),
			'id'         => $this->option_id( 'check_now' ),
			'type'       => 'cpl_submit_button',
			'desc'       => sprintf( __( 'Check for new %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
			'query_args' => array(
				'cp_action' => "cpl_adapter_pull_{$this->type}"
			)
		) );

		$cmb->add_field( array(
			'name'       => 'Hard Pull',
			'id'         => $this->option_id( 'hard_pull' ),
			'type'       => 'cpl_submit_button',
			'desc'       => __( 'Pull all sermons immediately', 'cp-library' ),
			'query_args' => array(
				'cp_action' => "cpl_adapter_hard_pull_{$this->type}"
			)
		) );
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
	 * @param mixed $default The default value to return if the setting is not found.
	 */
	public function get_setting( $key, $default = '' ) {
		return Settings::get( $this->option_id( $key ), $default, "cpl_{$this->type}_adapter_options" );
	}
}
