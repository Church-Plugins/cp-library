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
   * <file>
   * : specify the path to the import file
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
   * <file>
   * : specify the path to the import file
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

}
