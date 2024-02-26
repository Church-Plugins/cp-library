<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Base class for handling migrations
 *
 * @package CP_Library
 * @since 1.3.0
 */

namespace CP_Library\Admin\Migrate;

use CP_Library\Models\Item;
use CP_Library\Models\ItemType;
use CP_Library\Models\Speaker;
use ChurchPlugins\Exception;

/**
 * Base class for handling migrations
 *
 * @since 1.3.0
 */
abstract class Migration extends \WP_Background_Process {
	/**
	 * The plugin name to migrate from
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The migration type identifier
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The class constructor
	 */
	protected function __construct() {
		$this->action = "cpl_migration_{$this->type}";
		parent::__construct();
		add_action( "wp_ajax_cpl_poll_migration_{$this->type}", array( $this, 'send_progress' ) );
		add_action( "wp_ajax_cpl_start_migration_{$this->type}", array( $this, 'start_migration' ) );
		add_action( "wp_ajax_cpl_pause_migration_{$this->type}", array( $this, 'pause_migration' ) );
		add_action( "wp_ajax_cpl_resume_migration_{$this->type}", array( $this, 'resume_migration' ) );
	}

	/**
	 * Check for the count of items to migrate, 0 if none.
	 *
	 * @return int
	 */
	abstract public function get_item_count();

	/**
	 * Gets all data to migrate.
	 *
	 * @return mixed[]
	 */
	abstract public function get_migration_data();

	/**
	 * Migrate a single item
	 *
	 * @param mixed $post The data to migrate.
	 * @return void
	 */
	abstract public function migrate_item( $post );

	/**
	 * Handles the task
	 *
	 * @param mixed $item The data to migrate.
	 */
	public function task( $item ) {
		$status = get_transient( "cpl_migration_status_{$this->type}" );
		if ( ! $status ) {
			return false;
		}

		$failed = false;
		try {
			$this->migrate_item( $item );
		} catch ( \ChurchPlugins\Exception $e ) {
			error_log( $e->getMessage() );
			$failed = true;
			return false;
		}

		$status['progress']++;
		if ( $status['progress'] >= $status['migration_count'] ) {
			$status['status'] = 'complete';
		}
		if ( $failed ) {
			$status['failed']++;
		}

		set_transient( "cpl_migration_status_{$this->type}", $status, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Starts the migration
	 *
	 * @return void
	 */
	public function start_migration() {
		try {
			$items = $this->get_migration_data();
		} catch ( \ChurchPlugins\Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		set_transient(
			"cpl_migration_status_{$this->type}",
			array(
				'status'          => count( $items ) ? 'in_progress' : 'complete',
				'migration_count' => count( $items ),
				'progress'        => 0,
				'failed'          => 0,
			),
			HOUR_IN_SECONDS
		);

		foreach ( $items as $item ) {
			$this->push_to_queue( $item );
		}

		if ( count( $items ) > 0 ) {
			$this->save()->dispatch();
		}

		wp_send_json_success();
	}

	/**
	 * It's not possible to use get_the_terms() once a plugin has been deactivated, due to the taxonomy not being registered.
	 * This gets the terms directly from the database instead of using WordPress functions.
	 *
	 * @param string $taxonomy The taxonomy to get terms for.
	 * @param int    $post_id The post ID to get terms for.
	 * @return \stdClass[] The terms for the post.
	 */
	public function get_terms( $taxonomy, $post_id ) {
		global $wpdb;

		$query = "
			SELECT t.*, tt.*
			FROM {$wpdb->terms} AS t
			INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->posts} AS p ON p.ID = tr.object_id
			WHERE tt.taxonomy = %s AND tr.object_id = %d
		";

		$terms = $wpdb->get_results( $wpdb->prepare( $query, $taxonomy, $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Checks if a post name exists
	 *
	 * @param string $post_type The post type to check.
	 * @param string $name The post name to check.
	 */
	public function post_name_exists( $post_type, $name ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare( 'SELECT 1 FROM %s WHERE post_name = %s AND post_type = %s', $wpdb->posts, $name, $post_type )
		);
	}

	/**
	 * Creates and manages series coming from a taxonomy
	 *
	 * @param Item        $item The item being processed.
	 * @param \stdClass[] $series The series taxonomy terms.
	 */
	protected function add_series_from_terms( $item, $series ) {
		foreach ( $series as $term ) {
			$this->add_series_from_term( $item, $term );
		}
	}

	/**
	 * Creates and manages series coming from a taxonomy
	 *
	 * @param Item      $item The item being processed.
	 * @param \stdClass $term The series taxonomy term.
	 *
	 * @updated 1.4.1 added a filter for the post type arguments + an action hook for the newly created post
	 * @todo Make more DRY by merging with add_speaker_from_term.
	 */
	protected function add_series_from_term( $item, $term ) {
		$args = array(
			'post_type'      => cp_library()->setup->post_types->item_type->post_type,
			'posts_per_page' => 1,
			'post_status'    => 'any',
		);

		$series_posts = get_posts( array_merge( $args, array( 'name' => $term->slug ) ) );
		$post         = current( $series_posts );

		if ( ! $post ) {
			$series_posts = get_posts( array_merge( $args, array( 'title' => $term->name ) ) );
			$post         = current( $series_posts );
		}

		if ( ! $post ) {
			$args = array(
				'post_title'   => $term->name,
				'post_name'    => $term->slug,
				'post_type'    => cp_library()->setup->post_types->item_type->post_type,
				'post_content' => $term->description,
				'post_status'  => 'publish',
			);

			/**
			 * Creates a series from a term
			 *
			 * @param array     $args The arguments for creating the series.
			 * @param \stdClass $term The term to create the series from.
			 * @return array
			 * @since 1.4.1
			 */
			$args = apply_filters( 'cpl_migration_series_from_term_args', $args, $term );

			$post_id = wp_insert_post( $args );

			if ( ! $post_id ) {
				return;
			}
		} else {
			$post_id = $post->ID;
		}

		try {
			$item_type = ItemType::get_instance_from_origin( $post_id );
			$item->add_type( $item_type->id );

			/**
			 * Fires when a series has been successfully migrated from a term
			 *
			 * @param ItemType  $item_type The item type that was created.
			 * @param \stdClass $term      The term that was used to create the series.
			 * @param Item      $item      The item that was created.
			 * @since 1.4.1
			 */
			do_action( 'cpl_migration_series_created', $item_type, $term, $item );
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Manages migrating topics from another plugin
	 *
	 * @param Item  $item The item being processed.
	 * @param array $topics The topics to migrate.
	 */
	public function add_topics_to_item( $item, $topics ) {
		foreach ( $topics as $topic ) {
			$this->add_topic_to_item( $item, $topic );
		}
	}

	/**
	 * Manages migrating a topic from another plugin
	 *
	 * @param Item   $item The item being processed.
	 * @param object $topic The topic to migrate.
	 */
	public function add_topic_to_item( $item, $topic ) {
		$existing_topic = get_term_by( 'slug', $topic->slug, 'cpl_topic' );

		if ( $existing_topic ) {
			$topic_id = $existing_topic->term_id;
		} else {
			$args = array(
				'name'        => $topic->name,
				'slug'        => $topic->slug,
				'description' => $topic->description,
			);

			$topic_id = wp_insert_term( $topic->name, 'cpl_topic', $args );

			if ( is_wp_error( $topic_id ) ) {
				error_log( 'Error creating topic: ' . $topic_id->get_error_message() );
				return false;
			}

			$topic_id = $topic_id['term_id'];
		}

		wp_set_post_terms( $item->origin_id, array( $topic_id ), 'cpl_topic', true );
	}

	/**
	 * Inserts a new post if a migration hasn't already been performed
	 *
	 * @param mixed $post The post to migrate.
	 * @return int|false The new post ID or false if the post already exists or an error occurs.
	 */
	public function maybe_insert_post( $post ) {
		$item_post_type      = cp_library()->setup->post_types->item->post_type;
		$item_type_post_type = cp_library()->setup->post_types->item_type->post_type;

		$existing_item = current(
			get_posts(
				array(
					'meta_key'    => 'migration_id',
					'meta_value'  => $post->ID,
					'post_type'   => $item_post_type,
					'numberposts' => 1,
				)
			)
		);

		if ( $existing_item ) {
			return false;
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

		$new_post_id = wp_insert_post( $new_post );

		if ( is_wp_error( $new_post_id ) ) {
			error_log( 'Error creating post: ' . $new_post_id->get_error_message() );
			return false;
		}

		return $new_post_id;
	}

	/**
	 * Creates and manages speakers coming from a taxonomy
	 *
	 * @param Item  $item The item being processed.
	 * @param array $speakers The speakers taxonomy terms.
	 */
	protected function add_speakers_from_terms( $item, $speakers ) {
		foreach ( $speakers as $term ) {
			$this->add_speaker_from_term( $item, $term );
		}
	}

	/**
	 * Creates and manages speakers coming from a taxonomy
	 *
	 * @param Item   $item The item being processed.
	 * @param object $term The speaker taxonomy term.
	 *
	 * @updated 1.4.1 added a filter for the post type arguments + an action hook for the newly created post
	 */
	protected function add_speaker_from_term( $item, $term ) {
		$args = array(
			'post_type'      => cp_library()->setup->post_types->speaker->post_type,
			'posts_per_page' => 1,
			'post_status'    => 'any',
		);

		$speaker_posts = get_posts( array_merge( $args, array( 'name' => $term->slug ) ) );
		$post          = current( $speaker_posts );

		// fallback to searching by title
		if ( ! $post ) {
			$speaker_posts = get_posts( array_merge( $args, array( 'title' => $term->name ) ) );
			$post          = current( $speaker_posts );
		}

		if ( ! $post ) {
			$args = array(
				'post_title'   => $term->name,
				'post_name'    => $term->slug,
				'post_type'    => cp_library()->setup->post_types->speaker->post_type,
				'post_content' => $term->description,
				'post_status'  => 'publish',
			);

			/**
			 * Creates a speaker from a term
			 *
			 * @param array     $args The arguments for creating the speaker.
			 * @param \stdClass $term The term to create the series from.
			 * @return array
			 * @since 1.4.1
			 */
			$args = apply_filters( 'cpl_migration_speaker_from_term_args', $args, $term );

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

			/**
			 * Fires when a speaker has been successfully migrated from a term
			 *
			 * @param Speaker   $speker The item type that was created.
			 * @param \stdClass $term   The term that was used to create the speaker.
			 * @param Item      $item   The item that was created.
			 * @since 1.4.1
			 */
			do_action( 'cpl_migration_speaker_created', $speaker, $term, $item );
		} catch ( Exception $e ) {
			return;
		}
	}

	/**
	 * Pauses the migration
	 *
	 * @return void
	 */
	public function pause_migration() {
		$this->pause();
		wp_send_json_success();
	}

	/**
	 * Resume the migration
	 *
	 * @return void
	 */
	public function resume_migration() {
		$this->resume();
		wp_send_json_success();
	}

	/**
	 * Sends the migration progress to the client
	 *
	 * @return void
	 */
	public function send_progress() {
		$status = get_transient( "cpl_migration_status_{$this->type}" );

		if ( ! $status ) {
			wp_send_json_error(
				array(
					'progress'   => 0,
					'item_count' => 0,
					'status'     => 'not_started',
					'failed'     => 0,
				)
			);
		}

		$migration_count = max( $status['migration_count'], 1 ); // Prevent division by zero.
		$percentage      = 'complete' === $status['status'] ? 100 : ( $status['progress'] / $migration_count ) * 100;

		wp_send_json_success(
			array(
				'progress'   => $percentage,
				'item_count' => $migration_count,
				'status'     => $status['status'],
				'failed'     => $status['failed'],
			)
		);
	}
}
