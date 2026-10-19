<?php
/**
 * CP Sermons WPAllImport integration.
 */

namespace CP_Library\Integrations;

use CP_Library\Admin\Settings;
use CP_Library\Controllers\Item;

/**
 * WPAllImport integration.
 */
class WPAllImport {
	/**
	 * Singleton instance.
	 *
	 * @var WPAllImport
	 */
	protected static $_instance;

	/**
	 * Get the instance.
	 *
	 * @return WPAllImport
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof WPAllImport ) {
			self::$_instance = new WPAllImport();
		}

		return self::$_instance;
	}

	/**
	 * WPAllImport constructor.
	 */
	protected function __construct() {
		$this->actions();
	}

	/**
	 * Add actions.
	 */
	protected function actions() {
		add_action( 'pmxi_update_post_meta', [ $this, 'handle_meta_values' ], 10, 3 );
	}

	public function handle_meta_values( $post_id, $meta_key, $meta_value ) {
		global $wpdb;

		$keys = [
			'cpl_speaker',
			'cpl_series',
			'cpl_service_type',
			'audio_url',
			'video_url'
		];

		if ( ! in_array( $meta_key, $keys ) ) {
			return;
		}

		if ( cp_library()->setup->post_types->item->post_type != get_post_type( $post_id ) ) {
			return;
		}

		try {
			$item = new Item( $post_id );

			switch( $meta_key ) {
				case 'cpl_speaker':
				case 'cpl_series':
				case 'cpl_service_type':
					// An unmapped or blank column is not a request to unassign. Feeds are
					// re-run to refresh other fields, and syncing an empty value here would
					// strip the sermon's existing speakers and series.
					if ( ! $this->has_value( $meta_value ) ) {
						break;
					}

					// get meta_id for key from postmeta
					$meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $meta_key ) );
					do_action( 'updated_post_meta', $meta_id, $post_id, $meta_key, $meta_value );
					break;
				case 'audio_url':
				case 'video_url':
					$item->model->update_meta_value( $meta_key, $meta_value );
					break;
				default:
					break;
			}

		} catch ( \Exception $e ) {
			// Handle exception if needed.
			return;
		}

	}

	/**
	 * Whether an imported value carries anything to sync.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	protected function has_value( $value ) {
		$values = array_filter( array_map( 'trim', array_filter( (array) $value, 'is_scalar' ) ), 'strlen' );

		return ! empty( $values );
	}

}
