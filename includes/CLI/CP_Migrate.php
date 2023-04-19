<?php

use CP_Library\Setup\Taxonomies\Scripture;
use CP_Library\Setup\Taxonomies\Topic;
use CP_Library\Setup\Taxonomies\Season;
use CP_Library\Models\Item;
use CP_Library\Models\ItemType;
use CP_Library\Models\Speaker;
use CP_Locations\Models\Location;

// Make the `cp` command available to WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'cp', 'CP_Migrate' );
}

/**
 * Provides the wp-cli 'cp' command
 *
 * For more info, use: cp [command] --help
 *
 * Example:
 * wp cp migrate
 *
 * @author Tanner Moushey
 */
class CP_Migrate {

	/**
	 * Class constructor. Initialize members.
	 *
	 * @author Landon Otis
	 */
	public function __construct() {
	}

	/**
	 * Iterate all Sermon Series' and update their first and last sermon times for housekeeping
	 *
	 * @return void
	 * @author costmo
	 */
	public function update_series_times() {
		$types = ItemType::get_all_types();
		foreach( $types as $type_data ) {
			$type = new ItemType( $type_data );
			$type->update_dates();
		}
	}

	/**
	 * Delete all messages
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete_messages() {
		$messages = get_posts( [ 'post_type' => Item::get_prop( 'post_type' ), 'posts_per_page' => -1 ] );
		foreach ( $messages as $message ) {
			wp_delete_post( $message->ID, true );
		}

		WP_CLI::success( 'Deleted ' . count( $messages ) . ' messages' );
	}

	/**
	 * Loop through taxonomy map file and make sure that all expected terms exist.
	 *
	 * @param $args
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function map_taxonomies( $args ) {
		$args = wp_parse_args( [
			0 => 'category_map.csv',
		], $args );

		$filename = ABSPATH . $args[0];

		if ( ! $file = fopen( $filename, 'r' ) ) {
			WP_CLI::error( 'Could not locate the file' );
		}

		$row = 0;

		WP_CLI::log( 'Mapping taxonomy data' );

		$taxonomies = [
			'topic'     => [ 'updated' => false, 'terms' => array_map( 'strtolower', Topic::get_instance()->get_terms() ) ],
			'season'    => [ 'updated' => false, 'terms' => array_map( 'strtolower', Season::get_instance()->get_terms() ) ],
			'scripture' => [ 'updated' => false, 'terms' => array_map( 'strtolower', Scripture::get_instance()->get_terms() ) ],
		];

		while ( $data = fgetcsv( $file ) ) {
			count( $data );

			if ( ++ $row == 1 ) {
				$headers = array_flip( $data ); // Get the column names from the header.
				continue;
			}

			$data = array_map( 'trim', $data );

			$action = strtolower( $data[ $headers['ACTION'] ] );
			$old    = $data[ $headers['OLD'] ];
			$_new   = array_map( 'trim', explode( ',', $data[ $headers['NEW'] ] ) );
			$type   = strtolower( $data[ $headers['TYPE'] ] );

			foreach ( $_new as $new ) {
				if ( 'delete' == $action ) {
					continue;
				}

				if ( empty( $taxonomies[ $type ] ) ) {
					WP_CLI::warning( 'Could not find ' . $type . ' for ' . $new );
					continue;
				}

				if ( array_search( strtolower( $new ), $taxonomies[ $type ]['terms'] ) ) {
					continue;
				}

				// throw an error if no action was provided and we couldn't find the value
				if ( 'none' == $type ) {
					WP_CLI::warning( 'Could not find ' . $new . ' in ' . $type );
					continue;
				}

				WP_CLI::log( 'Add ' . $new . ' to ' . $type . '. Replaces: ' . $old );
			}

		}

		WP_CLI::success( 'Completed taxonomy map check' );
	}

	/**
	 * Receives an array of tags that belong to one of our taxonomies.
	 * @param $post_id
	 * @param $terms
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	protected static function update_terms( $post_id, $terms ) {

		$map = false;
		$filename = ABSPATH . 'category_map.csv';

		// handle term mapping
		if ( $file = fopen( $filename, 'r' ) ) {
			$row = 0;
			while ( $data = fgetcsv( $file ) ) {
				count( $data );

				if ( ++ $row == 1 ) {
					$headers = array_flip( $data ); // Get the column names from the header.
					continue;
				}

				$data = array_map( 'trim', $data );

				$action = strtolower( $data[ $headers['ACTION'] ] );
				$old    = strtolower( $data[ $headers['OLD'] ] );
				$_new   = array_map( 'trim', explode( ',', $data[ $headers['NEW'] ] ) );

				$map[ $old ] = [];

				if ( 'delete' === $action ) {
					$map[ $old ] = 'delete';
					continue;
				}

				foreach( $_new as $new ) {
					$map[ $old ][] = $new;
				}
			}
		}

		$taxonomies = [
			'topic'     => [
				'tax'   => Topic::get_instance()->taxonomy,
				'terms' => Topic::get_instance()->get_terms(),
				'terms_lwr' => array_map( 'strtolower', Topic::get_instance()->get_terms() )
			],
			'season'    => [
				'tax'   => Season::get_instance()->taxonomy,
				'terms' => Season::get_instance()->get_terms(),
				'terms_lwr' => array_map( 'strtolower', Season::get_instance()->get_terms() )
			],
			'scripture' => [
				'tax'   => Scripture::get_instance()->taxonomy,
				'terms' => Scripture::get_instance()->get_terms(),
				'terms_lwr' => array_map( 'strtolower', Scripture::get_instance()->get_terms() )
			],
		];

		$terms = array_map( 'trim', explode( ',', $terms ) );

		// setup update array
		$update = [];
		foreach( $taxonomies as $taxonomy ) {
			$update[ $taxonomy['tax'] ] = [];
		}

		foreach( $terms as $term ) {
			$tax = false;

			$_terms = [ $term ];

			// find the mapped term if it exists... allow for multiple terms mapped to a single term
			if ( isset( $map[ strtolower( $term ) ] ) ) {
				$_terms = $map[ strtolower( $term ) ];

				if ( 'delete' === $_terms ) {
					continue;
				}
			}

			foreach ( $_terms as $_term ) {
				foreach ( $taxonomies as $taxonomy ) {
					// search against lowercase terms but use normal case in update array
					if ( $key = array_search( strtolower( $_term ), $taxonomy['terms_lwr'] ) ) {
						$tax              = $taxonomy['tax'];
						$update[ $tax ][] = $taxonomy['terms'][ $key ];
						break;
					}
				}
			}

			if ( ! $tax ) {
				WP_CLI::warning( 'Could not find ' . $term );
			}

		}

		foreach( $update as $tax => $terms ) {
			wp_set_post_terms( $post_id, $terms, $tax );
		}
	}

	/**
	 * Import provided series
	 *
	 * ## OPTIONS
	 *
	 * [--skip-thumbs]
	 * : use this parameter to skip sideloading the thumbnail
	 *
	 * [--update]
	 * : use this parameter to update existing items
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @throws \ChurchPlugins\Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function import_series( $args, $assoc_args ) {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$args = wp_parse_args( [
			0 => 'series.csv',
		], $args );

		$filename = ABSPATH . $args[0];

		if ( ! $file = fopen( $filename, 'r' ) ) {
			WP_CLI::error('Could not locate the file');
		}

		$import_id = time();

		WP_CLI::log( 'Batch ID: ' . $import_id );

		$row = 0;
		while( $data = fgetcsv( $file ) ) {
			count( $data );

			if ( ++ $row == 1 ) {
				$headers = array_flip( $data ); // Get the column names from the header.
				continue;
			}

			$data = array_map( 'trim', $data );

			// @todo add better handling for url
			$url_data = explode( '/', untrailingslashit( $data[ $headers['URL'] ] ) );
			$slug = array_pop( $url_data );

			$title       = $data[ $headers['Title'] ];
			$desc        = $data[ $headers['Description'] ];
			$thumb       = $data[ $headers['Thumbnail'] ];
			$study_guide = $data[ $headers['Study Guide'] ];
			$cp_hash     = md5( $title );
			$post_id     = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from $wpdb->postmeta WHERE `meta_key` = '_cp_import_id' AND `meta_value` = %s", $cp_hash ) );

			if ( $post_id && empty( $assoc_args['update'] ) ) {
				WP_CLI::warning( $title . ' already exists. Skipping this item.' );
				continue;
			}

			WP_CLI::log( 'Importing ' . $title );
			if ( $slug && get_page_by_path( $slug, OBJECT, ItemType::get_prop( 'post_type' ) ) ) {
				WP_CLI::warning( 'This content already exists' );
				continue;
			}

			$args = [
				'post_type'    => ItemType::get_prop( 'post_type' ),
				'post_title'   => $title,
				'post_content' => $desc ?? '',
				'post_status'  => 'publish',
			];

			if ( ! empty( $post_id ) ) {
				$args['ID'] = $post_id;
			}

			if ( ! empty( $slug ) ) {
				$args['post_name'] = $slug;
			}

			$series_id = wp_insert_post( $args, true );

			if ( is_wp_error( $series_id ) ) {
				WP_CLI::warning( $series_id->get_error_message() );
				continue;
			}

			update_post_meta( $series_id, '_cp_import_id', $cp_hash );
			update_post_meta( $series_id, '_cp_import_batch_id', $import_id );
			update_post_meta( $series_id, '_cp_import_data', $data );
			update_post_meta( $series_id, '_cp_import_headers', $headers );

			// save to our tables
			ItemType::get_instance_from_origin( $series_id );

			WP_CLI::log( '--- series created' );

			if ( $thumb && empty( $assoc_args['skip-thumbs'] ) ) {
				// Handle thumbnail
				$thumb_id = media_sideload_image( $thumb, $series_id, $title . ' Thumbnail', 'id' );

				if ( is_wp_error( $thumb_id ) ) {
					WP_CLI::warning( $thumb_id->get_error_message() );
				} else {
					WP_CLI::log( '--- imported thumbnail' );
				}

				set_post_thumbnail( $series_id, $thumb_id );
			}

			// Handle study guide
			if ( $study_guide ) {
				$tmp = download_url( $study_guide );

				if ( is_wp_error( $tmp ) ) {
					WP_CLI::warning( $tmp->get_error_message() );
					continue;
				}

				$file_array = [
					'name'     => basename( $study_guide ),
					'tmp_name' => $tmp,
				];

				$id = media_handle_sideload( $file_array, $series_id );

				if ( is_wp_error( $id ) ) {
					WP_CLI::warning( $id->get_error_message() );
					continue;
				}

				$study_guide_url = wp_get_attachment_url( $id );
				update_post_meta( $series_id, 'cp_study_guide', $study_guide_url );

				WP_CLI::log( '--- imported study guide' );
			}

		}

	}

	/**
	 * Assemble a list of all the terms provided with the messages
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_message_terms( $args, $assoc_args ) {
		$args = wp_parse_args( [
			0 => 'messages.csv',
		], $args );

		$filename = ABSPATH . $args[0];

		if ( ! $file = fopen( $filename, 'r' ) ) {
			WP_CLI::error( 'Could not locate the file' );
		}

		$all_terms = [];

		$row = 0;
		while ( $data = fgetcsv( $file ) ) {
			count( $data );

			if ( ++ $row == 1 ) {
				$headers = array_flip( $data ); // Get the column names from the header.
				continue;
			}

			$data  = array_map( 'trim', $data );
			$terms = $data[ $headers['Tags'] ];

			$terms = array_map( 'trim', explode( ',', $terms ) );

			foreach( $terms as $term ) {
				if ( empty( $all_terms[ $term ] ) ) {
					$all_terms[ $term ] = 0;
				}

				$all_terms[ $term ] ++;
			}
		}

		WP_CLI::log( 'Term,Usage' );

		foreach( $all_terms as $term => $count ) {
			WP_CLI::log( $term . ',' . $count );
		}
	}

	/**
	 * Import messages from csv
	 *
	 * ## OPTIONS
	 *
	 * [--skip-thumbs]
	 * : use this parameter to skip sideloading the thumbnail
	 *
	 * [--skip-tags]
	 * : use this parameter to skip importing tags
	 *
	 * [--update]
	 * : use this parameter to update existing items
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function import_messages( $args, $assoc_args ) {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$args = wp_parse_args( [
			0 => 'messages.csv',
		], $args );

		$filename = ABSPATH . $args[0];

		if ( ! $file = fopen( $filename, 'r' ) ) {
			WP_CLI::error('Could not locate the file');
		}

		$all_speakers = [];
		$all_locations = [ 'global' => 'global' ];

		foreach( Speaker::get_all_speakers() as $speaker ) {
			$all_speakers[ $speaker->id ] = strtolower( $speaker->title );
		}

		if ( class_exists( 'CP_Locations\Models\Location' ) ) {
			$has_locations = true;
			foreach ( Location::get_all_locations() as $location ) {
				// set the term id as the key
				$all_locations[ 'location_' . $location->origin_id ] = strtolower( $location->title );
			}
		} else {
			$has_locations = false;
		}

		$results = ItemType::search( 'title', 'No Series' );
		$import_id = time();

		WP_CLI::log( 'Batch ID: ' . $import_id );

		if ( empty( $results ) ) {
			$series_id = wp_insert_post( [
				'post_type'   => ItemType::get_prop( 'post_type' ),
				'post_title'  => 'No Series',
				'post_status' => 'publish',
			], true );

			if ( is_wp_error( $series_id ) ) {
				WP_CLI::error( $series_id->get_error_message() );
			}

			try {
				$series         = ItemType::get_instance_from_origin( $series_id );
				$default_series = $series->id;
			} catch( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		} else {
			$default_series = $results[0]->id;
		}

		$row = 0;

		while ( $data = fgetcsv( $file ) ) {
			try {
				count( $data );

				if ( ++ $row == 1 ) {
					$headers = array_flip( $data ); // Get the column names from the header.
					continue;
				}

				$data = array_map( 'trim', $data );

				$title    = $data[ $headers['Title'] ];
				$desc     = trim( $data[ $headers['Description'] ] );
				$series   = explode( ':', $data[ $headers['Series'] ] )[0];
				$date     = strtotime( $data[ $headers['Date'] ] );
				$location = trim( strtolower( $data[ $headers['Location'] ] ) );
				$speakers = array_map( 'trim', explode( ',', $data[ $headers['Speaker'] ] ) );
				$terms    = $data[ $headers['Tags'] ];
				$thumb    = $data[ $headers['Thumbnail'] ];
				$video    = trim( $data[ $headers['Video'] ] );
				$audio    = trim( $data[ $headers['Audio'] ] );

				if ( $has_locations && false === $location_id = array_search( $location, $all_locations ) ) {
					WP_CLI::error( 'Could not find location.' );
				}

				if ( empty( $title ) ) {
					$title = $series;
				}

				$cp_hash = md5( $title . $date );
				$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from $wpdb->postmeta WHERE `meta_key` = '_cp_import_id' AND `meta_value` = %s", $cp_hash ) );

				if ( $post_id && empty( $assoc_args['update'] ) ) {
					WP_CLI::warning( $title . ' already exists. Skipping this item.' );
					continue;
				}

				WP_CLI::log( 'Importing ' . $title );

				$args = [
					'post_type'    => Item::get_prop( 'post_type' ),
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_date'    => date( 'Y-m-d 9:00:00', $date ),
					'post_content' => wp_kses_post( $desc ),
				];

				if ( $post_id ) {
					$args['ID'] = $post_id;
				}

				$message_id = wp_insert_post( $args, true );

				update_post_meta( $message_id, '_cp_import_id', $cp_hash );
				update_post_meta( $message_id, '_cp_import_batch_id', $import_id );
				update_post_meta( $message_id, '_cp_import_data', $data );
				update_post_meta( $message_id, '_cp_import_headers', $headers );

				if ( is_wp_error( $message_id ) ) {
					WP_CLI::warning( $message_id->get_error_message() );
					continue;
				}

				// add taxonomy
				if ( $has_locations ) {
					wp_set_post_terms( $message_id, $location_id, 'cp_location' );
				}

				// save to our tables
				$item = Item::get_instance_from_origin( $message_id );

				WP_CLI::log( '--- message created' );

				if ( $thumb && empty( $assoc_args['skip-thumbs'] ) && $thumb !== get_post_meta( $message_id, '_cp_import_thumb', true ) ) {
					// Handle thumbnail
					$thumb_id = media_sideload_image( $thumb, $message_id, $title . ' Thumbnail', 'id' );

					if ( is_wp_error( $thumb_id ) ) {
						WP_CLI::warning( $thumb_id->get_error_message() );
					} else {
						WP_CLI::log( '--- imported thumbnail' );
					}

					set_post_thumbnail( $message_id, $thumb_id );
					update_post_meta( $message_id, '_cp_import_thumb', $thumb );
				}

				// Speakers
				$speaker_ids = [];
				foreach( $speakers as $speaker ) {
					if ( false === $speaker_id = array_search( strtolower( $speaker ), $all_speakers ) ) {
						$speaker_id = wp_insert_post( [
							'post_type'   => Speaker::get_prop( 'post_type' ),
							'post_title'  => $speaker,
							'post_status' => 'publish',
						], true );

						if ( is_wp_error( $speaker_id ) ) {
							WP_CLI::error( $speaker_id->get_error_message() );
							continue;
						}

						// get the id and save it to our array
						$speaker_id                  = Speaker::get_instance_from_origin( $speaker_id )->id;
						$all_speakers[ $speaker_id ] = strtolower( $speaker );

						WP_CLI::log( '--- speaker created, ' . $speaker );
					}

					if ( $has_locations && $speaker_id ) {
						// add the current location to the speaker's location
						wp_set_post_terms( Speaker::get_instance( $speaker_id )->origin_id, $location_id, 'cp_location', true );
					}

					$speaker_ids[] = $speaker_id;
				}

				$item->update_speakers( $speaker_ids );

				// Series / ItemType
				$series_id = $default_series;

				if ( ! empty( $series ) ) {
					$results = ItemType::search( 'title', $series );

					if ( empty( $results ) ) {
						$results = ItemType::search( 'title', $series, true );
					}

					if ( count( $results ) ) {
						// default to first result
						$series_id = $results[0]->id;

						// see if there is a direct match
						foreach( $results as $result ) {
							if ( $result->title == $series ) {
								$series_id = $result->id;
							}
						}
					} elseif ( ! empty( $series ) ) { // create the series if it doesn't exist
						$series_id = wp_insert_post( [
							'post_type'   => ItemType::get_prop( 'post_type' ),
							'post_title'  => $series,
							'post_status' => 'publish',
						], true );

						if ( is_wp_error( $series_id ) ) {
							WP_CLI::error( $series_id->get_error_message() );
						}

						// get the id
						$series_id = ItemType::get_instance_from_origin( $series_id )->id;

						WP_CLI::log( '--- Series created, ' . $series );
					}
				}

				$item->update_types( [ $series_id ] );

				if ( $video ) {
					update_post_meta( $message_id, 'video_url', $video );
					$item->update_meta_value( 'video_url', $video );
					WP_CLI::log( '--- added video' );
				}

				if ( strstr( $audio, 'w.soundcloud.com/player' ) ) {
					$audio = $this->get_soundcloud_url( $audio );
				}

				if ( $audio ) {
					update_post_meta( $message_id, 'audio_url', $audio );
					$item->update_meta_value( 'audio_url', $audio );
					WP_CLI::log( '--- added audio' );
				}

				if ( ! empty( $terms ) && empty( $assoc_args['skip-tags'] ) ) {
					self::update_terms( $message_id, $terms );
				}

			} catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		}

	}

	/**
	 * Get SoundCloud canonical url from embed code
	 *
	 * @param $embed
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_soundcloud_url( $embed ) {
		$response = wp_remote_get( $embed );

		if ( is_wp_error( $response ) ) {
			WP_CLI::warnign( 'Could not get connect to ' . $embed );
		}

		if ( ! preg_match( '/<link rel="canonical" href="([^"]*)/', $response['body'], $url ) ) {
			WP_CLI::warnign( 'Could not get url from ' . $embed );
		}

		return $url[1];
	}

	/**
	 * Remove duplicate sermons
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function duplicate_message_series( $args, $assoc_args ) {
		$messages = get_posts( [ 'post_type' => Item::get_prop( 'post_type' ), 'posts_per_page' => -1 ] );
		$deleted = $maybe_delete = [];

		foreach ( $messages as $message ) {
			$location = wp_get_post_terms( $message->ID, 'cp_location' );
			$title = $message->post_title;

			// already deleted this one
			if ( isset( $deleted[ $message->ID ] ) ) {
				continue;
			}

			if ( empty( $location ) ) {
				continue;
			}

			$location = $location[0];

			foreach( $messages as $duplicate ) {
				// already deleted this one
				if ( isset( $deleted[ $duplicate->ID ] ) ) {
					continue;
				}

				if ( $duplicate->ID === $message->ID ) {
					continue;
				}

				if ( $duplicate->post_title !== $title ) {
					continue;
				}

				if ( $duplicate->post_date !== $message->post_date ) {
					continue;
				}

				if ( ! has_term( $location->term_id, 'cp_location', $duplicate->ID ) ) {
					continue;
				}

				$meta      = [ 'video_url', 'audio_url' ];
				$different = false;

				foreach( $meta as $key ) {
					if ( get_post_meta( $message->ID, $key, true ) !== get_post_meta( $duplicate->ID, $key, true ) ) {
						$different = true;
					}
				}

				if ( $different ) {
//					$maybe_delete[ $duplicate->ID ] = $duplicate->post_title;
//					continue;
				}

				wp_delete_post( $duplicate->ID, true );
				$deleted[ $duplicate->ID ] = $duplicate->post_title;
				WP_CLI::log( 'Duplicate: ' . $duplicate->post_title );
			}
		}

//		WP_CLI::warning( 'Maybe remove ' . count( $maybe_delete ) . ' messages' );
//		WP_CLI::log( implode( ', ', $maybe_delete ) );
		WP_CLI::success( 'Removed ' . count( $deleted ) . ' messages' );
	}

	/**
	 * Generate a JSON file with bible stats from a static input array
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @since  1.0.0
	 *
	 * @author costmo
	 */
	public function generate_bible_json( $args, $assoc_args ) {
		$output_file = dirname( __FILE__ ) . "/scripture_detailed_" . date( "Ymd_His" ) . ".json";

		$json_data = json_encode( self::bible_stat_data() );
		file_put_contents( $output_file, $json_data );

		WP_CLI::success( "JSON data written to " . $output_file );

	}

	/**
	 * A static array of Bible statistics for conversion to JSON
	 *
	 * @return void
	 * @author costmo
	 */
	private static function bible_stat_data() {
		return [
			"Genesis" => [
				"verse_counts" => [
					31, 25, 24, 26, 32, 22, 24, 22, 29, 32,
					32, 20, 18, 24, 21, 16, 27, 33, 38, 18,
					34, 24, 20, 67, 34, 35, 46, 22, 35, 43,
					55, 32, 20, 31, 29, 43, 36, 30, 23, 23,
					57, 38, 34, 34, 28, 34, 31, 22, 33, 26
				]
			],
			"Exodus" => [
				"verse_counts" => [
					22,
					25,
					22,
					31,
					23,
					30,
					25,
					32,
					35,
					29,
					10,
					51,
					22,
					31,
					27,
					36,
					16,
					27,
					25,
					26,
					36,
					31,
					33,
					18,
					40,
					37,
					21,
					43,
					46,
					38,
					18,
					35,
					23,
					35,
					35,
					38,
					29,
					31,
					43,
					38,
				]
			],
			"Leviticus" => [
				"verse_counts" => [
					17,
					16,
					17,
					35,
					19,
					30,
					38,
					36,
					24,
					20,
					47,
					8,
					59,
					57,
					33,
					34,
					16,
					30,
					37,
					27,
					24,
					33,
					44,
					23,
					55,
					46,
					34,
				]
			],
			"Numbers" => [
				"verse_counts" => [
					54,
					34,
					51,
					49,
					31,
					27,
					89,
					26,
					23,
					36,
					35,
					16,
					33,
					45,
					41,
					50,
					13,
					32,
					22,
					29,
					35,
					41,
					30,
					25,
					18,
					65,
					23,
					31,
					40,
					16,
					54,
					42,
					56,
					29,
					34,
					13,
				]
			],
			"Deuteronomy" => [
				"verse_counts" => [
					46,
					37,
					29,
					49,
					33,
					25,
					26,
					20,
					29,
					22,
					32,
					32,
					18,
					29,
					23,
					22,
					20,
					22,
					21,
					20,
					23,
					30,
					25,
					22,
					19,
					19,
					26,
					68,
					29,
					20,
					30,
					52,
					29,
					12
				]
			],
			"Joshua" => [
				"verse_counts" => [
					18,
					24,
					17,
					24,
					15,
					27,
					26,
					35,
					27,
					43,
					23,
					24,
					33,
					15,
					63,
					10,
					18,
					28,
					51,
					9,
					45,
					34,
					16,
					33
				]
			],
			"Judges" => [
				"verse_counts" => [
					36,
					23,
					31,
					24,
					31,
					40,
					25,
					35,
					57,
					18,
					40,
					15,
					25,
					20,
					20,
					31,
					13,
					31,
					30,
					48,
					25
				]
			],
			"Ruth" => [
				"verse_counts" => [
					22,
					23,
					18,
					22
				]
			],
			"1 Samuel" => [
				"verse_counts" => [
					28,
					36,
					21,
					22,
					12,
					21,
					17,
					22,
					27,
					27,
					15,
					25,
					23,
					52,
					35,
					23,
					58,
					30,
					24,
					42,
					15,
					23,
					29,
					22,
					44,
					25,
					12,
					25,
					11,
					31,
					13
				]
			],
			"2 Samuel" => [
				"verse_counts" => [
					27,
					32,
					39,
					12,
					25,
					23,
					29,
					18,
					13,
					19,
					27,
					31,
					39,
					33,
					37,
					23,
					29,
					33,
					43,
					26,
					22,
					51,
					39,
					25
				]
			],
			"1 Kings" => [
				"verse_counts" => [
					53,
					46,
					28,
					34,
					18,
					38,
					51,
					66,
					28,
					29,
					43,
					33,
					34,
					31,
					34,
					34,
					24,
					46,
					21,
					43,
					29,
					53
				]
			],
			"2 Kings" => [
				"verse_counts" => [
					18,
					25,
					27,
					44,
					27,
					33,
					20,
					29,
					37,
					36,
					21,
					21,
					25,
					29,
					38,
					20,
					41,
					37,
					37,
					21,
					26,
					20,
					37,
					20,
					30
				]
			],
			"1 Chronicles" => [
				"verse_counts" => [
					54,
					55,
					24,
					43,
					26,
					81,
					40,
					40,
					44,
					14,
					47,
					40,
					14,
					17,
					29,
					43,
					27,
					17,
					19,
					8,
					30,
					19,
					32,
					31,
					31,
					32,
					34,
					21,
					30
				]
			],
			"2 Chronicles" => [
				"verse_counts" => [
					17,
					18,
					17,
					22,
					14,
					42,
					22,
					18,
					31,
					19,
					23,
					16,
					22,
					15,
					19,
					14,
					19,
					34,
					11,
					37,
					20,
					12,
					21,
					27,
					28,
					23,
					9,
					27,
					36,
					27,
					21,
					33,
					25,
					33,
					27,
					23
				]
			],
			"Ezra" => [
				"verse_counts" => [
					11,
					70,
					13,
					24,
					17,
					22,
					28,
					36,
					15,
					44
				]
			],
			"Nehemiah" => [
				"verse_counts" => [
					11,
					20,
					32,
					23,
					19,
					19,
					73,
					18,
					38,
					39,
					36,
					47,
					31
				]
			],
			"Esther" => [
				"verse_counts" => [
					22,
					23,
					15,
					17,
					14,
					14,
					10,
					17,
					32,
					3
				]
			],
			"Job" => [
				"verse_counts" => [
					22,
					13,
					26,
					21,
					27,
					30,
					21,
					22,
					35,
					22,
					20,
					25,
					28,
					22,
					35,
					22,
					16,
					21,
					29,
					29,
					34,
					30,
					17,
					25,
					6,
					14,
					23,
					28,
					25,
					31,
					40,
					22,
					33,
					37,
					16,
					33,
					24,
					41,
					30,
					24,
					34,
					17
				]
			],
			"Psalms" => [
				"verse_counts" => [
					6,
					12,
					8,
					8,
					12,
					10,
					17,
					9,
					20,
					18,
					7,
					8,
					6,
					7,
					5,
					11,
					15,
					50,
					14,
					9,
					13,
					31,
					6,
					10,
					22,
					12,
					14,
					9,
					11,
					12,
					24,
					11,
					22,
					22,
					28,
					12,
					40,
					22,
					13,
					17,
					13,
					11,
					5,
					26,
					17,
					11,
					9,
					14,
					20,
					23,
					19,
					9,
					6,
					7,
					23,
					13,
					11,
					11,
					17,
					12,
					8,
					12,
					11,
					10,
					13,
					20,
					7,
					35,
					36,
					5,
					24,
					20,
					28,
					23,
					10,
					12,
					20,
					72,
					13,
					19,
					16,
					8,
					18,
					12,
					13,
					17,
					7,
					18,
					52,
					17,
					16,
					15,
					5,
					23,
					11,
					13,
					12,
					9,
					9,
					5,
					8,
					28,
					22,
					35,
					45,
					48,
					43,
					13,
					31,
					7,
					10,
					10,
					9,
					8,
					18,
					19,
					2,
					29,
					176,
					7,
					8,
					9,
					4,
					8,
					5,
					6,
					5,
					6,
					8,
					8,
					3,
					18,
					3,
					3,
					21,
					26,
					9,
					8,
					24,
					13,
					10,
					7,
					12,
					15,
					21,
					10,
					20,
					14,
					9,
					6
				]
			],
			"Proverbs" => [
				"verse_counts" => [
					33,
					22,
					35,
					27,
					23,
					35,
					27,
					36,
					18,
					32,
					31,
					28,
					25,
					35,
					33,
					33,
					28,
					24,
					29,
					30,
					31,
					29,
					35,
					34,
					28,
					28,
					27,
					28,
					27,
					33,
					31
				]
			],
			"Ecclesiastes" => [
				"verse_counts" => [
					18,
					26,
					22,
					16,
					20,
					12,
					29,
					17,
					18,
					20,
					10,
					14
				]
			],
			"Song of Solomon" => [
				"verse_counts" => [
					17,
					17,
					11,
					16,
					16,
					13,
					13,
					14
				]
			],
			"Isaiah" => [
				"verse_counts" => [
					31,
					22,
					26,
					6,
					30,
					13,
					25,
					22,
					21,
					34,
					16,
					6,
					22,
					32,
					9,
					14,
					14,
					7,
					25,
					6,
					17,
					25,
					18,
					23,
					12,
					21,
					13,
					29,
					24,
					33,
					9,
					20,
					24,
					17,
					10,
					22,
					38,
					22,
					8,
					31,
					29,
					25,
					28,
					28,
					25,
					13,
					15,
					22,
					26,
					11,
					23,
					15,
					12,
					17,
					13,
					12,
					21,
					14,
					21,
					22,
					11,
					12,
					19,
					12,
					25,
					24
				]
			],
			"Jeremiah" => [
				"verse_counts" => [
					19,
					37,
					25,
					31,
					31,
					30,
					34,
					22,
					26,
					25,
					23,
					17,
					27,
					22,
					21,
					21,
					27,
					23,
					15,
					18,
					14,
					30,
					40,
					10,
					38,
					24,
					22,
					17,
					32,
					24,
					40,
					44,
					26,
					22,
					19,
					32,
					21,
					28,
					18,
					16,
					18,
					22,
					13,
					30,
					5,
					28,
					7,
					47,
					39,
					46,
					64,
					34
				]
			],
			"Lamentations" => [
				"verse_counts" => [
					22,
					22,
					66,
					22,
					22
				]
			],
			"Ezekiel" => [
				"verse_counts" => [
					28,
					10,
					27,
					17,
					17,
					14,
					27,
					18,
					11,
					22,
					25,
					28,
					23,
					23,
					8,
					63,
					24,
					32,
					14,
					49,
					32,
					31,
					49,
					27,
					17,
					21,
					36,
					26,
					21,
					26,
					18,
					32,
					33,
					31,
					15,
					38,
					28,
					23,
					29,
					49,
					26,
					20,
					27,
					31,
					25,
					24,
					23,
					35
				]
			],
			"Daniel" => [
				"verse_counts" => [
					21,
					49,
					30,
					37,
					31,
					28,
					28,
					27,
					27,
					21,
					45,
					13
				]
			],
			"Hosea" => [
				"verse_counts" => [
					11,
					23,
					5,
					19,
					15,
					11,
					16,
					14,
					17,
					15,
					12,
					14,
					16,
					9
				]
			],
			"Joel" => [
				"verse_counts" => [
					20,
					32,
					21
				]
			],
			"Amos" => [
				"verse_counts" => [
					15,
					16,
					15,
					13,
					27,
					14,
					17,
					14,
					15
				]
			],
			"Obadiah" => [
				"verse_counts" => [
					21
				]
			],
			"Jonah" => [
				"verse_counts" => [
					17,
					10,
					10,
					11
				]
			],
			"Micah" => [
				"verse_counts" => [
					16,
					13,
					12,
					13,
					15,
					16,
					20
				]
			],
			"Nahum" => [
				"verse_counts" => [
					15,
					13,
					19
				]
			],
			"Habakkuk" => [
				"verse_counts" => [
					17,
					20,
					19
				]
			],
			"Zephaniah" => [
				"verse_counts" => [
					18,
					15,
					20
				]
			],
			"Haggai" => [
				"verse_counts" => [
					15,
					23
				]
			],
			"Zechariah" => [
				"verse_counts" => [
					21,
					13,
					10,
					14,
					11,
					15,
					14,
					23,
					17,
					12,
					17,
					14,
					9,
					21
				]
			],
			"Malachi" => [
				"verse_counts" => [
					14,
					17,
					18,
					6
				]
			],
			"Matthew" => [
				"verse_counts" => [
					25,
					23,
					17,
					25,
					48,
					34,
					29,
					34,
					38,
					42,
					30,
					50,
					58,
					36,
					39,
					28,
					27,
					35,
					30,
					34,
					46,
					46,
					39,
					51,
					46,
					75,
					66,
					20
				]
			],
			"Mark" => [
				"verse_counts" => [
					45,
					28,
					35,
					41,
					43,
					56,
					37,
					38,
					50,
					52,
					33,
					44,
					37,
					72,
					47,
					20
				]
			],
			"Luke" => [
				"verse_counts" => [
					80,
					52,
					38,
					44,
					39,
					49,
					50,
					56,
					62,
					42,
					54,
					59,
					35,
					35,
					32,
					31,
					37,
					43,
					48,
					47,
					38,
					71,
					56,
					53
				]
			],
			"John" => [
				"verse_counts" => [
					51,
					25,
					36,
					54,
					47,
					71,
					53,
					59,
					41,
					42,
					57,
					50,
					38,
					31,
					27,
					33,
					26,
					40,
					42,
					31,
					25
				]
			],
			"Acts" => [
				"verse_counts" => [
					26,
					47,
					26,
					37,
					42,
					15,
					60,
					40,
					43,
					48,
					30,
					25,
					52,
					28,
					41,
					40,
					34,
					28,
					41,
					38,
					40,
					30,
					35,
					27,
					27,
					32,
					44,
					31
				]
			],
			"Romans" => [
				"verse_counts" => [
					32,
					29,
					31,
					25,
					21,
					23,
					25,
					39,
					33,
					21,
					36,
					21,
					14,
					23,
					33,
					27
				]
			],
			"1 Corinthians" => [
				"verse_counts" => [
					31,
					16,
					23,
					21,
					13,
					20,
					40,
					13,
					27,
					33,
					34,
					31,
					13,
					40,
					58,
					24
				]
			],
			"2 Corinthians" => [
				"verse_counts" => [
					24,
					17,
					18,
					18,
					21,
					18,
					16,
					24,
					15,
					18,
					33,
					21,
					14
				]
			],
			"Galatians" => [
				"verse_counts" => [
					24,
					21,
					29,
					31,
					26,
					18
				]
			],
			"Ephesians" => [
				"verse_counts" => [
					23,
					22,
					21,
					32,
					33,
					24
				]
			],
			"Philippians" => [
				"verse_counts" => [
					30,
					30,
					21,
					23
				]
			],
			"Colossians" => [
				"verse_counts" => [
					29,
					23,
					25,
					18
				]
			],
			"1 Thessalonians" => [
				"verse_counts" => [
					10,
					20,
					13,
					18,
					28
				]
			],
			"2 Thessalonians" => [
				"verse_counts" => [
					12,
					17,
					18
				]
			],
			"1 Timothy" => [
				"verse_counts" => [
					20,
					15,
					16,
					16,
					25,
					21
				]
			],
			"2 Timothy" => [
				"verse_counts" => [
					18,
					26,
					17,
					22
				]
			],
			"Titus" => [
				"verse_counts" => [
					16,
					15,
					15
				]
			],
			"Philemon" => [
				"verse_counts" => [
					25
				]
			],
			"Hebrews" => [
				"verse_counts" => [
					14,
					18,
					19,
					16,
					14,
					20,
					28,
					13,
					28,
					39,
					40,
					29,
					25
				]
			],
			"James" => [
				"verse_counts" => [
					27,
					26,
					18,
					17,
					20
				]
			],
			"1 Peter" => [
				"verse_counts" => [
					25,
					25,
					22,
					19,
					14
				]
			],
			"2 Peter" => [
				"verse_counts" => [
					21,
					22,
					18
				]
			],
			"1 John" => [
				"verse_counts" => [
					10,
					29,
					24,
					21,
					21
				]
			],
			"2 John" => [
				"verse_counts" => [
					13
				]
			],
			"3 John" => [
				"verse_counts" => [
					14
				]
			],
			"Jude" => [
				"verse_counts" => [
					25
				]
			],
			"Revelation" => [
				"verse_counts" => [
					20,
					29,
					22,
					11,
					14,
					17,
					17,
					13,
					21,
					11,
					19,
					17,
					18,
					20,
					8,
					21,
					18,
					24,
					21,
					15,
					27,
					21
				]
			]
		];
	}

}
