<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Migrate Sermon Manager content to CP Sermons
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
	public $name = 'Sermon Manager';

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
		add_action( 'cpl_migration_series_created', array( $this, 'set_series_image' ), 10, 2 );
		add_action( 'cpl_migration_speaker_created', array( $this, 'set_speaker_image' ), 10, 2 );
	}

	/**
	 * Get the number of items to migrate, 0 if none.
	 *
	 * @return bool
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
	 * Returns all posts from SermonManager
	 */
	public function get_migration_data() {
		global $wpdb;

		$posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_type = %s",
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
		global $wpdb;

		if ( is_numeric( $post ) ) {
			$post = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->posts WHERE ID = %d",
					$post
				)
			);
		}

		if ( empty( $post ) ) {
			cp_library()->logging->log( 'No post found for ID: ' . $post );
			return;
		}

		$new_post_id = $this->maybe_insert_post( $post );

		if ( ! $new_post_id ) {
			return;
		}

		$meta     = get_post_meta( $post->ID );
		$series   = $this->get_terms( 'wpfc_sermon_series', $post->ID );
		$speakers = $this->get_terms( 'wpfc_preacher', $post->ID );
		$topics   = $this->get_terms( 'wpfc_sermon_topics', $post->ID );
		$thumb    = get_post_thumbnail_id( $post->ID );

		$notes = (array) get_post_meta( $post->ID, 'sermon_notes', true );
		$notes = array_merge( $notes, (array) get_post_meta( $post->ID, 'sermon_notes_multiple', true ) );
		$notes = array_unique( array_filter( $notes ) );

		$bulletins = (array) get_post_meta( $post->ID, 'sermon_bulletin', true );
		$bulletins = array_merge( $bulletins, (array) get_post_meta( $post->ID, 'sermon_bulletin_multiple', true ) );
		$bulletins = array_unique( array_filter( $bulletins ) );

		$downloads = array();

		foreach ( $notes as $note ) {
			$downloads[] = array(
				'file' => $note,
				'name' => 'Notes',
			);
		}

		foreach ( $bulletins as $bulletin ) {
			$downloads[] = array(
				'file' => $bulletin,
				'name' => 'Bulletin',
			);
		}

		try {
			$item = Item::get_instance_from_origin( $new_post_id );

			$scripture = $meta['bible_passage'][0] ?? false;
			$video_url = $meta['sermon_video_link'][0] ?? false;
			$audio_url = $meta['sermon_audio'][0] ?? false;

			if ( $thumb ) {
				set_post_thumbnail( $new_post_id, $thumb );
			}

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

			if ( ! empty( $downloads ) ) {
				update_post_meta( $new_post_id, 'downloads', $downloads );
			}

			if ( $series ) {
				$this->add_series_from_terms( $item, $series );
			}

			if ( $speakers ) {
				$this->add_speakers_from_terms( $item, $speakers );
			}

			if ( $topics ) {
				$this->add_topics_to_item( $item, $topics );
			}
		} catch ( Exception $e ) {
			cp_library()->logging->log_exception( $e );
		}
	}

	/**
	 * Migrates a series image
	 *
	 * @param ItemType  $series The series item.
	 * @param \stdClass $term The term.
	 * @return void
	 * @since 1.4.1
	 * @todo Make more DRY by merging with set_speaker_image.
	 */
	public function set_series_image( $series, $term ) {
		$associations = get_option( 'sermon_image_plugin' );
		$sanitized    = array();
		foreach ( $associations as $key => $value ) {
			$key   = absint( $key );
			$value = absint( $value );

			if ( $key && $value ) {
				$sanitized[ $key ] = $value;
			}
		}

		$attachment_id = $sanitized[ $term->term_id ] ?? false;

		if ( $attachment_id ) {
			set_post_thumbnail( $series->origin_id, $attachment_id );
		}
	}

	/**
	 * Migrates a speaker image
	 *
	 * @param Speaker   $speaker The speaker item.
	 * @param \stdClass $term The term.
	 * @return void
	 * @since 1.4.1
	 */
	public function set_speaker_image( $speaker, $term ) {
		$associations = get_option( 'sermon_image_plugin' );
		$sanitized    = array();
		foreach ( $associations as $key => $value ) {
			$key   = absint( $key );
			$value = absint( $value );

			if ( $key && $value ) {
				$sanitized[ $key ] = $value;
			}
		}

		$attachment_id = $sanitized[ $term->term_id ] ?? false;

		if ( $attachment_id ) {
			set_post_thumbnail( $speaker->origin_id, $attachment_id );
		}
	}
}
