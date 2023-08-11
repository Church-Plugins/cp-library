<?php
namespace CP_Library\Setup\PostTypes;

use ChurchPlugins\Helpers;
use ChurchPlugins\Setup\PostTypes\PostType;
use CP_Library\Setup\Taxonomies\Scripture as ScriptureTax;

// Exit if accessed directly
use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Setup\Tables\ItemMeta;
use CP_Library\Controllers\Item as ItemController;
use CP_Library\Models\Speaker as Speaker_Model;
use CP_Library\Models\Item as Model;
use CP_Library\Util\Convenience as _C;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post type: Item
 *
 * @author costmo
 * @since 1.0
 */
class Item extends PostType  {

	protected static $_did_save = false;

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
		parent::add_actions(); // TODO: Change the autogenerated stub

		add_action( 'cmb2_render_item_analytics', [ $this, 'analytics_cb' ], 10, 3 );
		add_action( 'quick_edit_custom_box',  [ $this, 'quick_edit_field' ], 10, 2 );
		add_action( 'add_inline_data',  [ $this, 'inline_data' ] );
		add_action( 'save_post', [ $this, 'quick_edit_save' ] );
		add_action( "cp_save_{$this->post_type}", [ $this, 'process_item' ] );

		add_action( 'save_post', [ $this, 'normalize_input' ], 20 ); // Set high priority so we can act before CMB
		add_filter( "{$this->post_type}_slug", [ $this, 'custom_slug' ] );

		add_filter( "manage_{$this->post_type}_posts_columns", [ $this, 'item_data_column' ], 20 );
		add_action( "manage_{$this->post_type}_posts_custom_column", [ $this, 'item_data_column_cb' ], 10, 2 );

		if ( cp_library()->setup->variations->is_enabled() ) {
			add_filter( 'post_type_link', [ $this, 'variation_link' ], 10, 2 );
			add_action( 'pre_get_posts', [ $this, 'item_query' ] );
		}

		// give other code a chance to hook into sources
		add_action( 'save_post', function () {
			if ( self::$_did_save ) {
				return;
			}

			self::$_did_save = true;
			add_action( 'cmb2_save_post_fields_item_meta', [ $this, 'save_variations' ], 10 );
		}, 5 );

		if ( empty( $_GET['cpl-recovery'] ) ) {
			add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );
		}
	}

	/**
	 * Return the parent link for variations
	 *
	 * @since  1.1.0
	 *
	 * @param $link
	 * @param $post
	 *
	 * @return false|string
	 * @author Tanner Moushey, 6/16/23
	 */
	public function variation_link( $link, $post ) {
		if ( get_post_type( $post ) != $this->post_type ) {
			return $link;
		}

		if ( ! $post->post_parent ) {
			return $link;
		}

		return get_permalink( $post->post_parent );
	}

	/**
	 * Customizations to the Item query
	 *
	 * @since  1.1.0
	 *
	 * @param $query
	 *
	 * @author Tanner Moushey, 5/6/23
	 */
	public function item_query( $query ) {

		if ( $this->post_type != $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! empty( $_GET['speaker'] ) ) {
			return;
		}

		// if we are filtering the variation, don't filter out the parents
		if ( cp_library()->setup->post_types->service_type->post_type === cp_library()->setup->variations->get_source()
			 && ! empty( $_GET['service-type'] ) ) {
			return;
		}

		// hide child items in queries (both frontend and admin)
		if ( ! $query->get( 'post_parent' ) && ! isset( $_GET['show-child-items'] ) ) {
			$query->set( 'post_parent', 0 );
		}

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
			$normalized = _C::normalize_timestamp( $_REQUEST['message_timestamp'] );
			if( !empty( $normalized ) ) {
				update_post_meta( $post_id, 'message_timestamp', $normalized );
			}
		}
  }

	 /* Allow for user defined slug
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
		$timestamp = _C::normalize_timestamp( $timestamp );

		update_post_meta( $post_id, 'message_timestamp', $timestamp );
	}

	/**
	 * Handle processes that should happen after every save
	 *
	 * @since  1.0.4
	 *
	 * @param $post_id
	 *
	 * @author Tanner Moushey, 4/13/23
	 */
	public function process_item( $post_id ) {
		try {
			$item = new ItemController( $post_id );
			$item->do_enclosure();
		} catch ( \ChurchPlugins\Exception $e ) {
			error_log( $e );
		}
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
		$args['supports'][] = 'excerpt';

		// make hierarchical if we are using variations
		if ( cp_library()->setup->variations->is_enabled() ) {
			$args['hierarchical'] = true;
			$args['supports'][] = 'page-attributes';
		}

		return $args;
	}

	public function register_metaboxes() {
		$this->analytics();
		$this->meta_details();
	}

	/**
	 * Default Item metaboxes
	 *
	 * @since  1.0.0
	 */
	protected function meta_details() {
		$has_parent = false;
		$id         = Helpers::get_param( $_GET, 'post', Helpers::get_request( 'post_ID' ) );

		Helpers::get_request( 'post' );
		if ( $id && get_post( $id )->post_parent ) {
			$has_parent = true;
		}

		$cmb = new_cmb2_box( [
			'id'           => 'item_meta',
			'title'        => $this->single_label . ' ' . __( 'Details', 'cp-library' ),
			'object_types' => [ $this->post_type ],
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true,
			'show_in_rest' => \WP_REST_Server::READABLE
		] );

		if ( ! $has_parent && cp_library()->setup->variations->is_enabled() ) {
			$cmb->add_field( [
				'name' => __( 'Add Variations', 'cp-library' ),
				'desc' => __( 'Convert this to a group and add variations.', 'cp-library' ),
				'id'   => '_cpl_has_variations',
				'type' => 'checkbox',
			] );
		}

		if ( $id && ! $has_parent && get_post_meta( $id, '_cpl_has_variations', true ) && cp_library()->setup->variations->is_enabled() ) {
			$sources_found = [];

			try {
				$item       = new ItemController( $id );
				$variations = $item->get_variations();
			} catch ( Exception $e ) {
				error_log( $e );
				return;
			}

			// Add a source_items filter so that we can control which fields are output for adding variations
			$source_items = apply_filters( 'cpl_item_meta_details_source_items', cp_library()->setup->variations->get_source_items() );

			foreach( $source_items as $source => $label ) {
				$sources_found[] = $source;

				$group_field_id = $cmb->add_field( [
					'id'         => '_cpl_item_variation_' . $source,
					'type'       => 'group',
					'repeatable' => false,
					'options'    => [
						'group_title'    => $label,
						'closed'         => false,
					],
				] );

				$this->repeater_fields( $cmb, $group_field_id, true );

				$cmb->add_group_field( $group_field_id, [ 'id' => 'variation_id', 'type' => 'hidden', 'default' => $source ] );
				$cmb->add_group_field( $group_field_id, [ 'id' => 'variation_type', 'type' => 'hidden', 'default' => cp_library()->setup->variations->get_source() ] );
			}

			// Loop through variants, and print any that were not included above
			foreach( $variations as $variation_id ) {
				try {
					$variant = new ItemController( $variation_id );
				} catch ( Exception $e ) {
					error_log( $e );
					continue;
				}

				if ( ! $variant->get_variation_source() ) {
					continue;
				}

				// only add fields for a source once.
				if ( in_array( $variant->get_variation_source_id(), $sources_found ) ) {
					continue;
				}

				$group_field_id = $cmb->add_field( [
					'id'         => '_cpl_item_variation_' . $variant->get_variation_source_id(),
					'type'       => 'group',
					'repeatable' => false,
					'options'    => [
						'group_title' => $variant->get_variation_source_label(),
						'closed'      => false,
					],
				] );

				$this->repeater_fields( $cmb, $group_field_id, true );

				$cmb->add_group_field( $group_field_id, [ 'id' => 'variation_id', 'type' => 'hidden', 'default' => $variant->get_variation_source_id() ] );
				$cmb->add_group_field( $group_field_id, [ 'id' => 'variation_type', 'type' => 'hidden', 'default' => $variant->get_variation_source_type() ] );
			}
		} else {
			$this->item_meta_fields( $cmb );
		}

	}

	protected function item_meta_fields( $cmb ) {
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

		if ( cp_library()->setup->podcast->is_enabled() ) {
			$cmb->add_field( [
				'name' => __( 'Exclude from Podcast', 'cp-library' ),
				'desc' => __( 'Check to exclude this sermon from the Podcast feed.', 'cp-library' ),
				'id'   => 'podcast_exclude',
				'type' => 'checkbox',
			] );
		}

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

	/**
	 * Meta override
	 *
	 * @param $data
	 * @param $object_id
	 * @param $data_args
	 * @param $field
	 *
	 * @return array|null
	 * @since  1.1.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_get_override( $data, $object_id, $data_args, $field ) {

		// look for a source suffix
		$source = str_replace( '_cpl_item_variation_', '', $data_args['field_id'] );

		// if we didn't find the source, break early.
		if ( $source == $data_args['field_id'] ) {
			return $data;
		}

		try {
			return $this->get_variation_item( $data, $object_id, $source );
		} catch ( \ChurchPlugins\Exception $e ) {
			error_log( $e );
		}

		return $data;
	}

	/**
	 * @param $data
	 * @param $object_id
	 * @param $source String
	 *
	 * @return array
	 * @throws \ChurchPlugins\Exception
	 * @since  1.1.0
	 *
	 * @author Tanner Moushey
	 */
	protected function get_variation_item( $data, $object_id, $source = '' ) {
		$item = new ItemController( $object_id );
		$variations = $item->get_variations();

		$data   = [];

		foreach ( $variations as $variation_id ) {

			$variant = new ItemController( $variation_id );

			if ( $variant->get_variation_source_id() != $source ) {
				continue;
			}

			// Allow custom sources to filter out items
			if ( ! apply_filters( 'cpl_item_get_variants_use_item', true, $variant, $source, $object_id, $data ) ) {
				continue;
			}

			$variant_data = [
				'id'             => $variant->model->origin_id,
				'date'           => date( 'Y-m-d\TH:i:s', $variant->get_publish_date() ),
				'variation_id'   => $variant->get_variation_source_id(),
				'variation_type' => $variant->get_variation_source_type(),
			];

			$meta = [ 'video_url', 'audio_url', 'video_id_facebook', 'video_id_vimeo' ];
			foreach ( $meta as $key ) {
				$variant_data[ $key ] = $variant->model->get_meta_value( $key );
			}

			if ( cp_library()->setup->post_types->speaker_enabled() ) {
				$variant_data['speakers'] = $variant->model->get_speakers();
			}

			$data[] = $variant_data;
			break;
		}

		return $data;
	}

	/**
	 * Save the variation item
	 *
	 * @since  1.1.0
	 *
	 * @param $object_id
	 *
	 * @throws \ChurchPlugins\Exception
	 * @author Tanner Moushey, 5/6/23
	 */
	public function save_variations( $object_id ) {
		$post = get_post( $object_id );

		// shouldn't ever happen, but better safe than sorry.
		if ( $post->post_parent ) {
			return;
		}

		// make sure we don't run this multiple times
		remove_action( 'cmb2_save_post_fields_item_meta', [ $this, 'save_variations' ] );

		// make sure CMB2 doesn't trigger anything while saving
		add_filter( 'cmb2_can_save', '__return_false' );

		$meta = get_post_meta( $object_id );
		$item = new ItemController( $object_id );

		foreach( $meta as $key => $value ) {
			if ( false === strpos( $key, '_cpl_item_variation' ) ) {
				continue;
			}

			try {
				$item_data = maybe_unserialize( $value[0] )[0];
				$has_content = false;

				foreach( $item_data as $k => $v ) {
					if ( in_array( $k, [ 'variation_type', 'variation_id', 'id' ] ) ) {
						continue;
					}

					$has_content = true;
				}

				// if there is no content for this item, delete if applicable and continue
				if ( ! $has_content ) {

					// if there is an id set, then we need to delete this variation
					if ( ! empty( $item_data['id'] ) ) {
						wp_delete_post( $item_data['id'] );
					}

					continue;
				}

				$item->model->update_variant( $item_data );

			} catch ( Exception $e ) {
				error_log( $e );
			}
		}

		// flush the cache
		$item->model->update_cache();

		remove_filter( 'cmb2_can_save', '__return_false' );

	}

	/**
	 * These are the fields to use when in a repeater context (Series / Variations)
	 *
	 * @since  1.1.0
	 *
	 * @param $cmb
	 * @param $group_field_id
	 * @param $is_variant
	 *
	 * @author Tanner Moushey, 5/5/23
	 */
	public function repeater_fields( $cmb, $group_field_id, $is_variant = false ) {
		$cmb->add_group_field( $group_field_id, [
			'id'   => 'id',
			'type' => 'hidden',
		] );

		if ( ! $is_variant ) {
			$cmb->add_group_field( $group_field_id, [
				'name'       => 'Title',
				'id'         => 'title',
				'type'       => 'text',
				'attributes' => [
					'placeholder' => Item::get_instance()->single_label . ' Title',
				]
			] );

			$cmb->add_group_field( $group_field_id, [
				'name'         => 'Thumbnail',
				'id'           => 'thumbnail',
				'type'         => 'file',
				'options'      => [
					'url' => false,
				],
				'query_args'   => array(
					'type' => array(
						'image/gif',
						'image/jpeg',
						'image/png',
					),
				),
				'preview_size' => 'medium',
			] );
		}


		if ( cp_library()->setup->post_types->speaker_enabled() ) {

			$speakers = Speaker_Model::get_all_speakers();

			if ( empty( $speakers ) ) {
				$cmb->add_group_field( $group_field_id, [
					'desc' => sprintf( __( 'No %s have been created yet. <a href="%s">Create one here.</a>', 'cp-library' ), Speaker::get_instance()->plural_label, add_query_arg( [ 'post_type' => Speaker::get_instance()->post_type ], admin_url( 'post-new.php' ) ) ),
					'id'   => 'cpl_no_speakers',
					'type' => 'title'
				] );
			} else {
				$speakers = array_combine( wp_list_pluck( $speakers, 'id' ), wp_list_pluck( $speakers, 'title' ) );

				$cmb->add_group_field( $group_field_id, [
					'name'              => Speaker::get_instance()->single_label,
					'id'                => 'speakers',
					'type'              => 'pw_multiselect',
					'select_all_button' => false,
					'options'           => $speakers,
					//					'desc' => sprintf( __( '<br />Create a new %s <a href="%s">here</a>.', 'cp-library' ), Speaker::get_instance()->plural_label, add_query_arg( [ 'post_type' => Speaker::get_instance()->post_type ], admin_url( 'post-new.php' ) ) ),
				] );
			}

		}

		$cmb->add_group_field( $group_field_id, [
			'name' => 'Date',
			'id'   => 'date',
			'type' => 'text_datetime_timestamp'
		] );

		if ( ! $is_variant ) {
			$cmb->add_group_field( $group_field_id, [
				'name' => __( 'Content', 'cp-library' ),
				'desc' => __( 'The content to display alongside with this item, leave blank to hide this field.', 'cp-library' ),
				'id'   => 'content',
				'type' => 'wysiwyg',
			] );
		}

		$cmb->add_group_field( $group_field_id, [
			'name' => __( 'Video URL', 'cp-library' ),
			'desc' => __( 'The URL of the video to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'video_url',
			'type' => 'file',
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => __( 'Audio URL', 'cp-library' ),
			'desc' => __( 'The URL of the audio to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'audio_url',
			'type' => 'file',
		] );

		if ( ! $is_variant ) {
			foreach ( cp_library()->setup->taxonomies->get_objects() as $tax ) {
				/** @var $tax \ChurchPlugins\Setup\Taxonomies\Taxonomy */
				$cmb->add_group_field( $group_field_id, [
					'name'              => $tax->plural_label,
					'id'                => $tax->taxonomy,
					'type'              => 'pw_multiselect',
					'select_all_button' => false,
					'options'           => $tax->get_terms_for_metabox(),
				] );
			}
		}

	}
}
