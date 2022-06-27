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

		add_action( 'cmb2_render_item_analytics', [ $this, 'analytics_cb' ], 10, 3 );
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
			'name' => __( 'Video URL', 'cp-library' ),
			'desc' => __( 'The URL of the video to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'video_url',
			'type' => 'file',
		] );

		$cmb->add_field( [
			'name' => __( 'Audio URL', 'cp-library' ),
			'desc' => __( 'The URL of the audio to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'audio_url',
			'type' => 'file',
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
