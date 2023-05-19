<?php
/**
 * Sermons import class
 *
 * This class handles importing sermons with the batch processing API
 *
 * @package     CP_Library
 * @subpackage  Admin/Import
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.4
 */

namespace CP_Library\Admin\Import;

use ChurchPlugins\Admin\Import\BatchImport;
use ChurchPlugins\Exception;
use CP_Library\Models\Item;
use CP_Library\Models\ItemType;
use CP_Library\Models\ServiceType;
use CP_Library\Models\Speaker;
use CP_Library\Setup\Taxonomies\Scripture;
use CP_Library\Setup\Taxonomies\Season;
use CP_Library\Setup\Taxonomies\Topic;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * ImportSermons Class
 *
 * @since 1.0.4
 */
class ImportSermons extends BatchImport {

	/**
	 * Set up our import config.
	 *
	 * @since 1.0.4
	 * @return void
	 */
	public function init() {

		// Set up default field map values
		$this->field_mapping = array(
			'title'        => '',
			'description'  => '',
			'series'       => '',
			'date'         => '',
			'passage'      => '',
			'location'     => '',
			'service_type' => '',
			'speaker'      => '',
			'topics'       => '',
			'season'       => '',
			'scripture'    => '',
			'thumbnail'    => '',
			'video'        => '',
			'audio'        => '',
		);
	}

	/**
	 * Process a step
   * 
   * @param $options
	 *
	 * @since 1.0.4
	 * @return bool
	 */
	public function process_step( $step = 0, $options = array() ) {
		global $wpdb;

    $default_options = array(
      'sideload_audio' => true
    );

    $options = array_merge( $default_options, $options );

		$more = false;

		if ( ! $this->can_import() ) {
			wp_die( __( 'You do not have permission to import data.', 'cp-library' ), __( 'Error', 'cp-library' ), array( 'response' => 403 ) );
		}

		$i      = 1;
		$offset = $this->step > 1 ? ( $this->per_step * ( $this->step - 1 ) ) : 0;

		if( $offset > $this->total ) {
			$this->done = true;

			// Delete the uploaded CSV file.
			unlink( $this->file );
		}

		if ( $this->done || empty( $this->csv ) ) {
			return $more;
		}

		$all_speakers      = $this->maybe_get_speakers();
		$all_service_types = $this->maybe_get_service_types();
		$all_locations     = $this->maybe_get_locations();
		$has_locations     = ! empty( $all_locations );
		$default_series    = $this->maybe_get_default_series();

		$import_id = time();

		$more = true;

		foreach( $this->csv as $index => $row ) {

			// Skip all rows until we pass our offset
			if( $index + 1 <= $offset ) {
				continue;
			}

			// Done with this batch
			if( $i > $this->per_step ) {
				break;
			}

			try {
				$this->row = $row;

				$location_id  = false;
				$title        = trim( $this->get_field_value( 'title' ) );
				$desc         = trim( $this->get_field_value( 'description' ) );
				$series       = explode( ':', $this->get_field_value( 'series' ) )[0];
				$date         = strtotime( $this->get_field_value( 'date' ) );
				$passage      = trim( $this->get_field_value( 'passage' ) );
				$location     = trim( strtolower( $this->get_field_value( 'location' ) ) );
				$service_type = array_map( 'trim', explode( ',', $this->get_field_value( 'service_type' ) ) );
				$speakers     = array_map( 'trim', explode( ',', $this->get_field_value( 'speaker' ) ) );
				$topics       = array_map( 'trim', explode( ',', $this->get_field_value( 'topics' ) ) );
				$season       = array_map( 'trim', explode( ',', $this->get_field_value( 'season' ) ) );
				$scripture    = array_map( 'trim', explode( ',', $this->get_field_value( 'scripture' ) ) );
				$thumb        = trim( $this->get_field_value( 'thumbnail' ) );
				$video        = trim( $this->get_field_value( 'video' ) );
				$audio        = trim( $this->get_field_value( 'audio' ) );

				if ( $has_locations && $location
				     && false === $location_id = array_search( $location, $all_locations ) ) {
					wp_die( "Could not find location: '$location'." );
				}

				if ( empty( $title ) ) {
					$title = $series;
				}

				$cp_hash = md5( $title . $date . $location_id );

				// update existing post if it exists
				$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from $wpdb->postmeta WHERE `meta_key` = '_cp_import_id' AND `meta_value` = %s", $cp_hash ) );

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

				if ( is_wp_error( $message_id ) ) {
					wp_die( $message_id->get_error_message() );
					continue;
				}

				update_post_meta( $message_id, '_cp_import_id', $cp_hash );
				update_post_meta( $message_id, '_cp_import_batch_id', $import_id );
				update_post_meta( $message_id, '_cp_import_data', $row );
				update_post_meta( $message_id, 'passage', $passage );

				// add taxonomy
				if ( $location_id ) {
					wp_set_post_terms( $message_id, $location_id, 'cp_location' );
				}

				// save to our tables
				$item = Item::get_instance_from_origin( $message_id );

				if ( $thumb && $thumb !== get_post_meta( $message_id, '_cp_import_thumb', true ) ) {
					$this->set_image( $message_id, $thumb );
					update_post_meta( $message_id, '_cp_import_thumb', $thumb );
				}


				if ( $video ) {
					update_post_meta( $message_id, 'video_url', $video );
					$item->update_meta_value( 'video_url', $video );
				}

				if ( strstr( $audio, 'w.soundcloud.com/player' ) ) {
					$audio = $this->get_soundcloud_url( $audio );
				}

				if ( $audio ) {
          $audio_url = $audio;
          if( $options['sideload_audio'] ) {
            $sideloaded_media_url = $this->sideload_media_and_get_url( $message_id, $audio );
						if( $sideloaded_media_url ) {
							$audio_url = $sideloaded_media_url;
						}
          }
					update_post_meta( $message_id, 'audio_url', $audio_url );
					$item->update_meta_value( 'audio_url', $audio_url );
				}

				if ( ! empty( $topics ) ) {
					$this->set_taxonomy_terms( $message_id, $topics, Topic::get_instance()->taxonomy );
				}

				if ( ! empty( $scripture ) ) {
					$this->set_taxonomy_terms( $message_id, $scripture, Scripture::get_instance()->taxonomy );
				}

				if ( ! empty( $season ) ) {
					$this->set_taxonomy_terms( $message_id, $season, Season::get_instance()->taxonomy );
				}

				// Handle message speakers
				if ( false !== $all_speakers ) {
					// Speakers
					$speaker_ids = [];
					foreach ( $speakers as $speaker ) {
						if ( false === $speaker_id = array_search( strtolower( $speaker ), $all_speakers ) ) {
							$speaker_id = wp_insert_post( [
								'post_type'   => Speaker::get_prop( 'post_type' ),
								'post_title'  => $speaker,
								'post_status' => 'publish',
							], true );

							if ( is_wp_error( $speaker_id ) ) {
								wp_die( $speaker_id->get_error_message() );
							}

							// get the id and save it to our array
							$speaker_id                  = Speaker::get_instance_from_origin( $speaker_id )->id;
							$all_speakers[ $speaker_id ] = strtolower( $speaker );
						}

						if ( $location_id && $speaker_id ) {
							// add the current location to the speaker's location
							wp_set_post_terms( Speaker::get_instance( $speaker_id )->origin_id, $location_id, 'cp_location', true );
						}

						$speaker_ids[] = $speaker_id;
					}

					$item->update_speakers( $speaker_ids );
				}

				// Handle message series
				if ( cp_library()->setup->post_types->item_type_enabled() ) {
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
							foreach ( $results as $result ) {
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
								wp_die( $series_id->get_error_message() );
							}

							// get the id
							$series_id = ItemType::get_instance_from_origin( $series_id )->id;
						}
					}

					if ( $series_id ) {
						$item->update_types( [ $series_id ] );
					}
				}

				// Handle Service Types
				if ( false !== $all_service_types ) {
					$types = [];
					foreach ( $service_type as $type ) {
						if ( false === $type_id = array_search( strtolower( $type ), $all_service_types ) ) {
							$post_id = wp_insert_post( [
								'post_type'   => ServiceType::get_prop( 'post_type' ),
								'post_title'  => $type,
								'post_status' => 'publish',
							], true );

							if ( is_wp_error( $post_id ) ) {
								wp_die( $post_id->get_error_message() );
							}

							// get the id and save it to our array
							$type_id                       = ServiceType::get_instance_from_origin( $post_id )->id;
							$all_service_types[ $type_id ] = strtolower( $type );
						}

						$types[] = $type_id;
					}

					$item->update_service_types( $types );
				}

			} catch ( Exception $e ) {
				wp_die( $e->getMessage() );
			}

			$i++;
		}

		return $more;
	}

	/**
	 *
	 * @since  1.0.4
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_get_locations() {
		if ( ! class_exists( 'CP_Locations\Models\Location' ) ) {
			return false;
		}

		$all_locations = [ 'global' => 'global' ];
		foreach ( \CP_Locations\Models\Location::get_all_locations() as $location ) {
			// set the term id as the key
			$all_locations[ 'location_' . $location->origin_id ] = strtolower( $location->title );
		}

		return $all_locations;
	}

	/**
	 * Get all speakers or false if disabled
	 *
	 * @return array|false
	 * @since  1.0.4
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_get_speakers() {
		if ( ! cp_library()->setup->post_types->speaker_enabled() ) {
			return false;
		}

		$all_speakers = [];

		foreach( Speaker::get_all_speakers() as $speaker ) {
			$all_speakers[ $speaker->id ] = strtolower( $speaker->title );
		}

		return $all_speakers;
	}

	/**
	 * Get all service types or false if disabled
	 *
	 * @return array|false
	 * @since  1.0.4
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_get_service_types() {
		if ( ! cp_library()->setup->post_types->service_type_enabled() ) {
			return false;
		}

		$service_types = [];

		foreach( ServiceType::get_all_service_types() as $type ) {
			$service_types[ $type->id ] = strtolower( $type->title );
		}

		return $service_types;
	}

	/**
	 * Get the default series, or create one if it doesn't exist.
	 *
	 * @return false|void|null
	 * @throws \ChurchPlugins\Exception
	 * @since  1.0.4
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_get_default_series() {
		if ( ! cp_library()->setup->post_types->item_type_enabled() ) {
			return false;
		}

		if ( ! apply_filters( 'cp_library_import_sermons_use_default_series', true ) ) {
			return false;
		}

		$results = ItemType::search( 'title', apply_filters( 'cp_library_import_sermons_default_series_title', __( 'No Series', 'cp-library' ) ) );

		if ( $results ) {
			return $results[0]->id;
		}

		$series_id = wp_insert_post( [
			'post_type'   => ItemType::get_prop( 'post_type' ),
			'post_title'  => 'No Series',
			'post_status' => 'publish',
		], true );

		if ( is_wp_error( $series_id ) ) {
			wp_die( $series_id->get_error_message() );
		}

		try {
			$series         = ItemType::get_instance_from_origin( $series_id );
			return $series->id;
		} catch ( Exception $e ) {
			wp_die( $e->getMessage() );
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
			wp_die( 'Could not get connect to ' . $embed );
		}

		if ( ! preg_match( '/<link rel="canonical" href="([^"]*)/', $response['body'], $url ) ) {
			wp_die( 'Could not get url from ' . $embed );
		}

		return $url[1];
	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @since 1.0.4
	 * @return int
	 */
	public function get_percentage_complete() {

		if( $this->total > 0 ) {
			$percentage = ( $this->step * $this->per_step / $this->total ) * 100;
		}

		if( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	/**
	 * Set up and taxonomy terms
	 *
	 * @since 1.0.4
	 * @return void
	 */
	private function set_taxonomy_terms( $post_id = 0, $terms = array(), $taxonomy = 'category' ) {

		$terms = $this->maybe_create_terms( $terms, $taxonomy );

		if( ! empty( $terms ) ) {
			wp_set_object_terms( $post_id, $terms, $taxonomy );
		}

	}

	/**
	 * Locate term IDs or create terms if none are found
	 *
	 * @since 1.0.4
	 * @return array
	 */
	private function maybe_create_terms( $terms = array(), $taxonomy = 'category' ) {

		// Return of term IDs
		$term_ids = array();

		foreach( $terms as $term ) {

			if( is_numeric( $term ) && 0 === (int) $term ) {

				$t = get_term( $term, $taxonomy );

			} else {

				$t = get_term_by( 'name', $term, $taxonomy );

				if( ! $t ) {

					$t = get_term_by( 'slug', $term, $taxonomy );

				}

			}

			if( ! empty( $t ) ) {

				$term_ids[] = $t->term_id;

			} else {

				$term_data = wp_insert_term( $term, $taxonomy, array( 'slug' => sanitize_title( $term ) ) );

				if( ! is_wp_error( $term_data ) ) {

					$term_ids[] = $term_data['term_id'];

				}

			}

		}

		return array_map( 'absint', $term_ids );
	}

	/**
	 * Retrieve URL to Sermons list table
	 *
	 * @since 1.0.4
	 * @return string
	 */
	public function get_list_table_url() {
		$post_type = cp_library()->setup->post_types->item->post_type;

		// Default args
		$args = array(
			'post_type' => $post_type
		);

		// Default URL
		$admin_url = admin_url( 'edit.php' );

		// Get the base admin URL
		$url = add_query_arg( $args, $admin_url );

		return $url;
	}

	/**
	 * Retrieve message label
	 *
	 * @since 1.0.4
	 * @return string
	 */
	public function get_import_type_label() {
		return strtolower( cp_library()->setup->post_types->item->plural_label );
	}

}
