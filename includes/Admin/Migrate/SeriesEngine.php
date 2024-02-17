<?php
/**
 * Migrate content from Series Engine.
 *
 * @package CP_Library
 */

namespace CP_Library\Admin\Migrate;

use CP_Library\Models\Item;

/**
 * Series Engine migration class
 *
 * @since 1.4.2
 */
class SeriesEngine extends Migration {

	/**
	 * Class instance
	 *
	 * @var SeriesEngine
	 */
	protected static $_instance;

	/**
	 * The plugin name to migrate from
	 *
	 * @var string
	 */
	public $name = 'Series Engine';

	/**
	 * The migration type identifier
	 *
	 * @var string
	 */
	public $type = 'series_engine';

	/**
	 * The post type to migrate from
	 *
	 * @var string
	 */
	public $post_type = 'enmse_message';

	/**
	 * Only make one instance of the SeriesEngine
	 *
	 * @return SeriesEngine
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof SeriesEngine ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Get the number of items found, 0 if none
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
	 * Get migration data
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
	 * Migrate a single item
	 *
	 * @param \stdClass $post The item to migrate
	 */
	public function migrate_item( $post ) {
		$new_post_id = $this->maybe_insert_post( $post );

		if ( ! $new_post_id ) {
			return;
		}

		global $wpdb;

		// gets message from custom table directly
		$se_message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}se_messages WHERE wp_post_id = %d",
				$post->ID
			)
		);

		$se_series = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}se_series AS series
				 INNER JOIN {$wpdb->prefix}se_series_message_matches AS matches
				 ON matches.series_id = series.series_id
				 AND matches.message_id = %d",
				$se_message->message_id
			)
		);

		$se_scripture = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT scripture.text FROM {$wpdb->prefix}se_scriptures AS scripture
				 INNER JOIN {$wpdb->prefix}se_scripture_message_matches AS matches
				 ON matches.scripture_id = scripture.scripture_id
				 AND matches.message_id = %d",
				$se_message->message_id
			)
		);

		$se_topics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT topic.name FROM {$wpdb->prefix}se_topics AS topic
				 INNER JOIN {$wpdb->prefix}se_message_topic_matches AS matches
				 ON matches.topic_id = topic.topic_id
				 AND matches.message_id = %d",
				$se_message->message_id
			)
		);

		$se_files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT file.file_name, file.file_url FROM {$wpdb->prefix}se_files AS file
				 INNER JOIN {$wpdb->prefix}se_message_file_matches AS matches
				 ON matches.file_id = file.file_id
				 AND matches.message_id = %d",
				$se_message->message_id
			)
		);

		$thumb     = $se_message->message_thumbnail;
		$speaker   = $se_message->speaker;
		$audio_url = $se_message->audio_url;
		$video_url = $se_message->video_url;
		$embed     = $se_message->embed_code;
		$alt_embed = $se_message->alternate_embed;

		try {
			$item = Item::get_instance_from_origin( $new_post_id );

			if ( ! empty( $thumb ) ) {
				$thumb_id = attachment_url_to_postid( $thumb );

				if ( $thumb_id ) {
					set_post_thumbnail( $new_post_id, $thumb_id );
				}
			}

			if ( ! empty( $se_series ) ) {
				foreach ( $se_series as $series ) {
					$this->migrate_series( $series, $item );
				}
			}

			if ( ! empty( $speaker ) ) {
				$this->migrate_speaker( $speaker, $item );
			}

			if ( ! empty( $se_scripture ) ) {
				$item->update_scripture( array_map( fn( $scripture ) => $scripture->text, $se_scripture ) );
			}

			if ( ! empty( $se_topics ) ) {
				$taxonomy = cp_library()->setup->taxonomies->topic->taxonomy;

				foreach ( $se_topics as $topic ) {
					$existing_topic = get_term_by( 'name', sanitize_title( $topic->name ), $taxonomy );

					if ( ! $existing_topic ) {
						$term = wp_insert_term( $topic->name, $taxonomy, array( 'slug' => sanitize_title( $topic->name ) ) );

						if ( ! is_wp_error( $term ) ) {
							$term_id = $term['term_id'];
						}
					} else {
						$term_id = $existing_topic->term_id;
					}

					wp_set_object_terms( $new_post_id, $term_id, $taxonomy, true );
				}
			}

			if ( ! empty( $se_files ) ) {
				$downloads = array();
				foreach ( $se_files as $file ) {
					$download = array(
						'file' => $file->file_url,
						'name' => $file->file_name,
					);

					if ( $attachment_id = attachment_url_to_postid( $file->file_url ) ) {
						$download['file_id'] = $attachment_id;
					}

					$downloads[] = $download;
				}

				update_post_meta( $new_post_id, 'downloads', $downloads );
			}

			// If we have a video url, use it, same with audio.
			// If we don't have a video url, use the embed if it exists and fallback to the alt embed.
			// If the video isn't using the embed and the audio isn't set, use the embed. Otherwise use the alt embed.
			$fallback_video = $embed ? $embed : $alt_embed;
			$fallback_audio = $embed && $video_url ? $embed : $alt_embed;

			$video_url = $video_url ? $video_url : $fallback_video;
			$audio_url = $audio_url ? $audio_url : $fallback_audio;

			if ( $video_url ) {
				update_post_meta( $new_post_id, 'video_url', $video_url );
				$item->update_meta_value( 'video_url', $video_url );
			}

			if ( $audio_url ) {
				update_post_meta( $new_post_id, 'audio_url', $audio_url );
				$item->update_meta_value( 'audio_url', $audio_url );
			}
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		$noop = false;
	}

	/**
	 * Adds a speaker by title
	 *
	 * @param string                  $title The speaker title
	 * @param \CP_Library\Models\Item $item The item to add the speaker to
	 */
	public function migrate_speaker( $title, $item ) {
		global $wpdb;
		$speaker = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->posts WHERE post_title = %s AND post_type = %s",
				$title,
				cp_library()->setup->post_types->speaker->post_type
			)
		);

		if ( ! $speaker ) {
			$speaker = array(
				'post_title'   => $title,
				'post_type'    => cp_library()->setup->post_types->speaker->post_type,
				'post_status'  => 'publish',
				'post_content' => '',
				'cpl_data'     => array(),
			);

			$speaker_id = wp_insert_post( $speaker );

			if ( $speaker_id ) {
				$speaker = get_post( $speaker_id );
			}
		}

		if ( $speaker ) {
			try {
				$speaker_modal = \CP_Library\Models\Speaker::get_instance_from_origin( $speaker->ID );
				$item->update_speakers( [ $speaker_modal->id ] );
			} catch ( \Exception $e ) {
				error_log( $e->getMessage() );
			}
		}
	}

	/**
	 * Migrate a series
	 *
	 * @param \stdClass               $se_series The series to migrate
	 * @param \CP_Library\Models\Item $item The item to add the series to
	 */
	public function migrate_series( $se_series, $item ) {
		global $wpdb;

		$series = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->posts WHERE post_title = %s AND post_type = %s",
				$se_series->s_title,
				cp_library()->setup->post_types->item_type->post_type
			)
		);

		if ( ! $series ) {
			$args = array(
				'post_title'   => $se_series->s_title,
				'post_type'    => cp_library()->setup->post_types->item_type->post_type,
				'post_status'  => 'publish',
				'post_content' => $se_series->s_description,
				'publish_date' => empty( $se_series->start_date ) ? null : $se_series->start_date,
			);

			$series_id = wp_insert_post( $args );

			if ( $series_id ) {
				$series = get_post( $series_id );
			}
		}

		if ( $series ) {
			try {
				$series_modal = \CP_Library\Models\ItemType::get_instance_from_origin( $series->ID );
				$item->add_type( $series_modal->id );
			} catch ( \Exception $e ) {
				error_log( $e->getMessage() );
			}
		}
	}
}
