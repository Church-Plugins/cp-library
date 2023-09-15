<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Migrate Church Content sermons to CP Library
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
		$series_taxonomy  = 'ctc_sermon_series';
		$speaker_taxonomy = 'ctc_sermon_speaker';
		$book_taxonomy    = 'ctc_sermon_book';

		$item_post_type      = cp_library()->setup->post_types->item->post_type;
		$item_type_post_type = cp_library()->setup->post_types->item_type->post_type;

		$existing_item = get_posts(
			array(
				'meta_key'    => 'migration_id',
				'meta_value'  => $post->ID,
				'post_type'   => $item_post_type,
				'numberposts' => 1,
			)
		);

		if ( current( $existing_item ) ) {
			return;
		}

		$new_post = array(
			'post_title'    => $post->post_title,
			'post_content'  => $post->post_content,
			'post_name'     => $post->post_name,
			'post_author'   => $post->post_author,
			'post_date'     => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
			'post_status'   => 'publish',
			'post_type'     => $item_post_type,
			'meta_input'    => array(
				'migration_id' => $post->ID,
			),
		);

		$new_post_id = wp_insert_post( $new_post, true );

		if ( is_wp_error( $new_post_id ) ) {
			error_log( 'Error creating post: ' . $new_post_id->get_error_message() );
			return;
		}

		$meta     = get_post_meta( $post->ID );
		$series   = get_the_terms( $post->ID, $series_taxonomy );
		$speakers = get_the_terms( $post->ID, $speaker_taxonomy );
		$books    = get_the_terms( $post->ID, $book_taxonomy );

		try {
			$item = Item::get_instance_from_origin( $new_post_id );

			$video_url = $meta['_ctc_sermon_video'][0] ?? false;
			$audio_url = $meta['_ctc_sermon_audio'][0] ?? false;

			if ( $books && ! is_wp_error( $books ) ) {
				$item->update_scripture( wp_list_pluck( $books, 'name' ) );
			}

			if ( $video_url ) {
				update_post_meta( $new_post_id, 'video_url', $video_url );
				$item->update_meta_value( 'video_url', $video_url );
			}

			if ( $audio_url ) {
				update_post_meta( $new_post_id, 'audio_url', $audio_url );
				$item->update_meta_value( 'audio_url', $audio_url );
			}

			if ( $series && ! is_wp_error( $series ) ) {
				$this->add_series( $item, $series );
			}

			if ( $speakers && ! is_wp_error( $series ) ) {
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
