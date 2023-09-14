<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Migrate Sermon Manager content to CP Library
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
 * SermonManager migration class
 *
 * @since 1.3.0
 */
class SermonManager extends Migration {

	/**
	 * The single instance of the class.
	 *
	 * @var SermonManager
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The plugin name to migrate from
	 *
	 * @var string
	 */
	public $name = 'Sermons Pro';

	/**
	 * The migration type identifier
	 *
	 * @var string
	 */
	public $type = 'wpfc_sermon';

	/**
	 * The post type to migrate from
	 *
	 * @var string
	 */
	public $post_type = 'wpfc_sermon';

	/**
	 * Only make one instance of the SermonManager
	 *
	 * @return SermonManager
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof SermonManager ) {
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
	 * SermonManager actions
	 *
	 * @return void
	 */
	protected function actions() {
	}

	/**
	 * Checks for existence of SermonManager.
	 *
	 * @return bool
	 */
	public function check_for_plugin() {
		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->posts WHERE post_type = %s",
				$this->type
			)
		);

		return (bool) $exists;
	}

	/**
	 * Returns all posts from SermonManager
	 */
	public function get_migration_data() {
		global $wpdb;

		$posts = get_posts(
			array(
				'post_type'      => $this->post_type,
				'posts_per_page' => -1,
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
		$series_taxonomy  = 'wpfc_sermon_series';
		$speaker_taxonomy = 'wpfc_preacher';

		$item_post_type      = cp_library()->setup->post_types->item->post_type;
		$item_type_post_type = cp_library()->setup->post_types->item_type->post_type;

		$new_post = array(
			'post_title'    => $post->post_title,
			'post_content'  => $post->post_content,
			'post_name'     => $post->post_name,
			'post_author'   => $post->post_author,
			'post_date'     => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
			'post_status'   => 'publish',
			'post_type'     => $item_post_type,
		);

		$new_post_id = wp_insert_post( $new_post );

		if ( ! $new_post_id ) {
			return;
		}

		$meta     = get_post_meta( $post->ID );
		$series   = get_the_terms( $post->ID, $series_taxonomy );
		$speakers = get_the_terms( $post->ID, $speaker_taxonomy );

		try {
			$item = Item::get_instance_from_origin( $new_post_id );

			$scripture = $meta['bible_passage'][0] ?? false;
			$video_url = $meta['sermon_video_link'][0] ?? false;
			$audio_url = $meta['sermon_audio'][0] ?? false;

			if ( $scripture ) {
				$item->update_scripture( $scripture );
			}

			if ( $video_url ) {
				update_post_meta( $new_post_id, 'video_url', $video_url );
				$item->update_meta_value( 'video_url', $video_url );
			}

			if ( $audio_url ) {
				update_post_meta( $new_post_id, 'audio_url', $audio_url );
				$item->update_meta_value( 'audio_url', $audio_url );
			}

			if ( $series ) {
				$this->add_series( $item, $series );
			}

			if ( $speakers ) {
				$this->add_speakers( $item, $speakers );
			}
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * Creates and manages series coming from a taxonomy
	 *
	 * @param Item  $item The item being processed.
	 * @param array $series The series taxonomy terms.
	 */
	protected function add_series( $item, $series ) {
		foreach ( $series as $term ) {
			$this->add_series_term( $item, $term );
		}
	}

	/**
	 * Creates and manages series coming from a taxonomy
	 *
	 * @param Item     $item The item being processed.
	 * @param \WP_Term $term The series taxonomy term.
	 */
	protected function add_series_term( $item, $term ) {
		$series_posts = get_posts(
			array(
				'name'           => $term->slug,
				'post_type'      => cp_library()->setup->post_types->item_type->post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		$post = current( $series_posts );

		if ( ! $post ) {
			$args = array(
				'post_title'   => $term->name,
				'post_name'    => $term->slug,
				'post_type'    => cp_library()->setup->post_types->item_type->post_type,
				'post_content' => $term->description,
				'post_status'  => 'publish',
			);

			$post = wp_insert_post( $args );

			if ( ! $post ) {
				return;
			}
		} else {
			$post = $post->ID;
		}

		try {
			$item_type = ItemType::get_instance_from_origin( $post );
			$item->add_type( $item_type->id );
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Creates and manages speakers coming from a taxonomy
	 *
	 * @param Item  $item The item being processed.
	 * @param array $speakers The speakers taxonomy terms.
	 */
	protected function add_speakers( $item, $speakers ) {
		foreach ( $speakers as $term ) {
			$this->add_speaker_term( $item, $term );
		}
	}

	/**
	 * Creates and manages speakers coming from a taxonomy
	 *
	 * @param Item     $item The item being processed.
	 * @param \WP_Term $term The speaker taxonomy term.
	 */
	protected function add_speaker_term( $item, $term ) {
		$speaker_posts = get_posts(
			array(
				'name'           => $term->slug,
				'post_type'      => cp_library()->setup->post_types->speaker->post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		$post = current( $speaker_posts );

		if ( ! $post ) {
			$args = array(
				'post_title'   => $term->name,
				'post_name'    => $term->slug,
				'post_type'    => cp_library()->setup->post_types->speaker->post_type,
				'post_content' => $term->description,
				'post_status'  => 'publish',
			);

			$post = wp_insert_post( $args );

			if ( ! $post ) {
				return;
			}
		} else {
			$post = $post->ID;
		}

		try {
			$speaker = Speaker::get_instance_from_origin( $post );
			$item->update_speakers( array( $speaker->id ) );
		} catch ( \Exception $e ) {
			return;
		}
	}
}
