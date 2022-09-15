<?php
namespace CP_Library\Setup\PostTypes;

use ChurchPlugins\Models\Log;
use ChurchPlugins\Setup\PostTypes\PostType;

// Exit if accessed directly
use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Setup\Tables\ItemMeta;
use CP_Library\Templates;
use CP_Library\Controllers\Item as ItemController;
use CP_Library\Util\Convenience;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post type: Item
 *
 * @author costmo
 * @since 1.0
 */
class Item extends PostType  {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	protected function __construct() {
		$this->post_type = CP_LIBRARY_UPREFIX . "_item";

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", Settings::get_item( 'singular_label', 'Message' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", Settings::get_item( 'plural_label', 'Messages' ) );

		parent::__construct( 'CP_Library' );
	}

	public function add_actions() {
		add_action( 'cmb2_render_item_analytics', [ $this, 'analytics_cb' ], 10, 3 );
		add_action( 'quick_edit_custom_box',  [ $this, 'quick_edit_field' ], 10, 2 );
		add_action( 'add_inline_data',  [ $this, 'inline_data' ] );
		add_action( 'save_post', [ $this, 'quick_edit_save' ] );


		add_action( 'save_post', [ $this, 'normalize_input' ], 20 ); // Set high priority so we can act before CMB
		add_filter( "{$this->post_type}_slug", [ $this, 'custom_slug' ] );

		add_filter( "manage_{$this->post_type}_posts_columns", [ $this, 'item_data_column' ], 20 );
		add_action( "manage_{$this->post_type}_posts_custom_column", [ $this, 'item_data_column_cb' ], 10, 2 );

		parent::add_actions(); // TODO: Change the autogenerated stub
	}

	/**

	 * Normalizes form input and saves before moving on in the admin UI
	 *
	 * @param int $post_id
	 * @return void
	 * @author costmo
	 */
	public function normalize_input( $post_id ) {

		if( empty( $_REQUEST ) || !is_array( $_REQUEST ) || empty( $_REQUEST['post_type'] ) || 'cpl_item' !== $_REQUEST['post_type'] ) {
			return;
		}

		// Normalize the "Sermon starts here" timestamp
		if( !empty( $_REQUEST['message_timestamp'] ) ) {
			$normalized = Convenience::normalize_timestamp( $_REQUEST['message_timestamp'] );
			if( !empty( $normalized ) ) {
				update_post_meta( $post_id, 'message_timestamp', $normalized );
			}
		}
  }

	 * Allow for user defined slug
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function custom_slug() {
		return Settings::get_item( 'slug', strtolower( sanitize_title( $this->plural_label ) ) );
	}

	/**
	 * Add our own custom data
	 *
	 * @param $post
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function inline_data( $post ) {
		if ( $this->post_type !== $post->post_type ) {
			return;
		}

		echo '<div class="message-timestamp">' . get_post_meta( $post->ID, 'message_timestamp', true ) . '</div>';
	}

	/**
	 * Save quick edit
	 *
	 * @param $post_id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function quick_edit_save( $post_id ) {

		// check inlint edit nonce
		if ( empty( $_POST['_inline_edit'] ) || ! wp_verify_nonce( $_POST['_inline_edit'], 'inlineeditnonce' ) ) {
			return;
		}

		// update the timestamp
		$timestamp = ! empty( $_POST['message_timestamp'] ) ? sanitize_text_field( $_POST['message_timestamp'] ) : '';
		$timestamp = Convenience::normalize_timestamp( $timestamp );

		update_post_meta( $post_id, 'message_timestamp', $timestamp );
	}

	public function quick_edit_field( $column_name, $post_type ) {
		if ( 'item_data' !== $column_name ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-left">&nbsp;</fieldset>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php _e( 'Timestamp', 'cp-library' ) ?></span>
					<input type="text" name="message_timestamp"><span class="description">(mm:ss) Sermon video timestamp.</span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * @param $columns
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function item_data_column( $columns ) {
		$new_columns = [];
		foreach( $columns as $key => $column ) {
			if ( 'date' === $key ) {
				$new_columns['item_data'] = $this->single_label . ' ' . __( 'Data', 'cp-library' );
			}

			$new_columns[ $key ] = $column;
		}

		// in case date isn't set
		if ( ! isset( $columns['date'] ) ) {
			$new_columns['item_data'] = $this->single_label . ' ' . __( 'Data', 'cp-library' );
		}

		return $new_columns;
	}

	/**
	 * Callback for message data
	 *
	 * @param $column
	 * @param $post_id
	 *
	 * @throws \ChurchPlugins\Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function item_data_column_cb( $column, $post_id ) {
		switch( $column ) {
			case 'item_data' :
				try {
					$item = new ItemController( $post_id );

					printf( 'Video: %s <br />Audio: %s', ( $item->get_video()['value'] ) ? 'Yes' : 'No', ( $item->get_audio() ) ? 'Yes' : 'No' );
				} catch ( Exception $e ) {
					error_log( $e );
				}
				break;
		}
	}

	/**
	 * Return custom meta keys
	 *
	 * @return array|mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_keys() {
		return ItemMeta::get_keys();
	}

	/**
	 * Setup arguments for this CPT
	 *
	 * @return array
	 * @author costmo
	 */
	public function get_args() {
		$args              = parent::get_args();
		$args['menu_icon'] = apply_filters( "{$this->post_type}_icon", 'dashicons-format-video' );

		return $args;
	}

	public function register_metaboxes() {
		$this->meta_details();
		$this->analytics();
	}

	protected function meta_details() {
		$cmb = new_cmb2_box( [
			'id' => 'item_meta',
			'title' => $this->single_label . ' ' . __( 'Details', 'cp-library' ),
			'object_types' => [ $this->post_type ],
			'context' => 'normal',
			'priority' => 'high',
			'show_names' => true,
		] );

		$cmb->add_field( [
			'name' => __( 'Audio URL', 'cp-library' ),
			'desc' => __( 'The URL of the audio to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'audio_url',
			'type' => 'file',
		] );

		$cmb->add_field( [
			'name' => __( 'Video URL', 'cp-library' ),
			'desc' => __( 'The URL of the video to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'video_url',
			'type' => 'file',
		] );

		$cmb->add_field( [
			'name' => __( 'Sermon Timestamp', 'cp-library' ),
			'desc' => __( 'Enter the timestamp (mm:ss or hh:mm:ss) where the sermon begins to show a quick navigation link on the video player', 'cp-library' ),
			'id'   => 'message_timestamp',
			'type' => 'text',
			'time_format' => 'H:i:s',
		] );

		if ( apply_filters( "{$this->post_type}_use_facebook", false ) ) {
			$cmb->add_field( [
				'name' => __( 'Facebook video permalink', 'cp-library' ),
				'id'   => 'video_id_facebook',
				'type' => 'text_medium',
			] );
		}

		if ( apply_filters( "{$this->post_type}_use_youtube", false ) ) {
			$cmb->add_field( [
				'name' => __( 'Youtube video permalink', 'cp-library' ),
				'id'   => 'video_id_youtube',
				'type' => 'text_medium',
			] );
		}

		if ( apply_filters( "{$this->post_type}_use_vimeo", false ) ) {
			$cmb->add_field( [
				'name' => __( 'Vimeo video id', 'cp-library' ),
				'id'   => 'video_id_vimeo',
				'type' => 'text_medium',
			] );
		}
	}

	protected function analytics() {
		$cmb = new_cmb2_box( [
			'id'           => 'item_analytics',
			'title'        => $this->single_label . ' ' . __( 'Analytics', 'cp-library' ),
			'object_types' => [ $this->post_type ],
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => false,
		] );

		$cmb->add_field( [
			'id' => 'analytics',
			'type' => 'item_analytics',
		] );
	}

	public function analytics_cb( $field, $escaped_value, $object_id ) {
		if ( ! $object_id || 'auto-draft' == get_post_status( $object_id ) ) {
			return;
		}

		try {
			$item = new ItemController( $object_id );
			$analytics = $item->get_analytics_count();
		} catch ( Exception $e ) {
			error_log( $e );
			return;
		}

		if ( empty( $analytics ) ) {
			_e( 'There are no analytics to show yet.', 'cp-library' );
			return;
		}

		?>

		<table class="striped" style="min-width: 50%;">
			<tbody>
				<?php foreach( $analytics as $metric ) : ?>
					<tr>
						<td><?php echo ucwords( str_replace( '_', ' ', $metric->action ) ); ?></td>
						<td><?php echo $metric->count; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
	}
}
