<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Migrate Church Content sermons to CP Sermons
 *
 * @package CP_Library
 * @since 1.3.0
 */

namespace CP_Library\Admin\Migrate;

use CP_Library\Models\Item;
use CP_Library\Models\ItemType;
use ChurchPlugins\Exception;
use CP_Library\Models\Speaker;

/**
 * ChurchContent migration class
 *
 * @since 1.3.0
 */
class ChurchContent extends Migration {

	/**
	 * The single instance of the class.
	 *
	 * @var ChurchContent
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The plugin name to migrate from
	 *
	 * @var string
	 */
	public $name = 'Church Content';

	/**
	 * The migration type identifier
	 *
	 * @var string
	 */
	public $type = 'ctc_sermon';

	/**
	 * The post type to migrate from
	 *
	 * @var string
	 */
	public $post_type = 'ctc_sermon';

	/**
	 * Only make one instance of the ChurchContent
	 *
	 * @return ChurchContent
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof ChurchContent ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		parent::__construct();
		$this->actions();
	}

	/**
	 * ChurchContent actions
	 *
	 * @return void
	 */
	protected function actions() {
	}

	/**
	 * Gets count of items to migrate, 0 of none.
	 *
	 * @return int
	 */
	public function get_item_count() {
		global $wpdb;

		$item_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s",
				$this->post_type
			)
		);

		return absint( $item_count );
	}

	/**
	 * Returns all posts from ChurchContent
	 */
	public function get_migration_data() {
		global $wpdb;

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->posts WHERE post_type = %s",
				$this->post_type
			)
		);

		return $posts;
	}

	/**
	 * Migrates a single item.
	 *
	 * @param mixed $post The post to migrate.
	 */
	public function migrate_item( $post ) {
		$new_post_id = $this->maybe_insert_post( $post );

		if ( ! $new_post_id ) {
			return;
		}

		$meta     = get_post_meta( $post->ID );
		$series   = $this->get_terms( 'ctc_sermon_series', $post->ID );
		$speakers = $this->get_terms( 'ctc_sermon_speaker', $post->ID );
		$books    = $this->get_terms( 'ctc_sermon_book', $post->ID );
		$topics   = $this->get_terms( 'ctc_sermon_topic', $post->ID );

		try {
			$item = Item::get_instance_from_origin( $new_post_id );

			if ( ! $item ) {
				error_log( 'Unable to get item from origin: ' . $new_post_id );
				return;
			}

			$video_url = $meta['_ctc_sermon_video'][0] ?? false;
			$audio_url = $meta['_ctc_sermon_audio'][0] ?? false;

			if ( $video_url ) {
				update_post_meta( $new_post_id, 'video_url', $video_url );
				$item->update_meta_value( 'video_url', $video_url );
			}

			if ( $audio_url ) {
				update_post_meta( $new_post_id, 'audio_url', $audio_url );
				$item->update_meta_value( 'audio_url', $audio_url );
			}

			if ( count( $series ) ) {
				$this->add_series_from_terms( $item, $series );
			}

			if ( count( $speakers ) ) {
				$this->add_speakers_from_terms( $item, $speakers );
			}

			if ( count( $books ) ) {
				$item->update_scripture( wp_list_pluck( $books, 'name' ) );
			}

			if ( count( $topics ) ) {
				$this->add_topics_to_item( $item, $topics );
			}
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}
	}
}
