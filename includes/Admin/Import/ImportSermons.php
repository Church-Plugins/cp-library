<?php
/**
 * Sermons import class
 *
 * This class handles importing sermons with the batch processing API
 *
 * @since       1.0.4
 * @subpackage  Admin/Import
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @package     CP_Library
 */

namespace CP_Library\Admin\Import;

use ChurchPlugins\Exception;
use CP_Library\Models\Item;
use CP_Library\Models\ItemType;
use CP_Library\Models\ServiceType;
use CP_Library\Models\Speaker;
use CP_Library\Setup\Taxonomies\Season;
use CP_Library\Setup\Taxonomies\Topic;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * ImportSermons Class
 *
 * @since 1.4.10
 */
class ImportSermons extends BackgroundProcessImport {
	/**
	 * The action to hook into to start the import
	 *
	 * @var string
	 */
	protected $action = 'sermons';

	/**
	 * The unique key for this import class
	 *
	 * @var string
	 */
	public static $key = 'sermons';

	/**
	 * Cache speaker query
	 *
	 * @var bool|array
	 */
	protected static $_speakers = false;

	/**
	 * Cache service type query
	 *
	 * @var bool|array
	 */
	protected static $_service_types = false;

	/**
	 * Field mapping
	 *
	 * @var array
	 */
	protected $field_mapping = array(
		'title'        => '',
		'description'  => '',
		'series'       => '',
		'date'         => '',
		'location'     => '',
		'service_type' => '',
		'speaker'      => '',
		'topics'       => '',
		'season'       => '',
		'scripture'    => '',
		'thumbnail'    => '',
		'video'        => '',
		'audio'        => '',
		'variation'    => '',
		'downloads'    => '',
	);

	/**
	 * Default options
	 *
	 * @var array
	 */
	protected $options = array(
		'sideload_audio'     => false,
		'stop_on_error'      => true,
		'sideload_downloads' => false,
	);

	/**
	 * Set up our import config.
	 *
	 * @since 1.0.4
	 * @return void
	 */
	public function init() {}

	/**
	 * Import a single item
	 * 
	 * @since 1.4.10
	 *
	 * @param array $item Item to process.
	 * @param array $options Import options.
	 * @throws Exception If there is an error importing the item.
	 */
	protected function import_item( $item, $options ): void {
		global $wpdb;

		$all_locations     = $this->maybe_get_locations();
		$default_series    = $this->maybe_get_default_series();
		$variation_options = [];
		$import_id         = time();

		$has_locations = ! empty( $all_locations );

		if ( cp_library()->setup->variations->is_enabled() ) {
			$variation_options = cp_library()->setup->variations->get_source_items();
		}

		$post_id      = false;
		$location_id  = false;
		$date         = $item['date'];
		$title        = trim( $item['title'] );
		$desc         = trim( $item['description'] );
		$series       = explode( ';', $item['series'] )[0];
		$date         = is_numeric( $date ) ? $date : strtotime( $item['date'] );
		$location     = trim( strtolower( $item['location'] ) );
		$service_type = array_filter( array_map( 'trim', explode( ',', $item['service_type'] ) ) );
		$speakers     = array_filter( array_map( 'trim', explode( ',', $item['speaker'] ) ) );
		$topics       = array_filter( array_map( 'trim', explode( ',', $item['topics'] ) ) );
		$season       = array_filter( array_map( 'trim', explode( ',', $item['season'] ) ) );
		$scripture    = array_filter( array_map( 'trim', explode( ',', $item['scripture'] ) ) );
		$thumb        = trim( $item['thumbnail'] );
		$video        = trim( $item['video'] );
		$audio        = trim( $item['audio'] );
		$variation    = trim( $item['variation'] );
		$downloads    = trim( $item['downloads'] );

		if ( empty( $variation_options ) ) {
			$variation = false;
		}

		if ( $has_locations && $location && false === $location_id = array_search( $location, $all_locations ) ) {
			throw new Exception( "Could not find location: '$location'." );
		}

		if ( $variation ) {
			$parent_search = Item::search( 'title', $title );

			foreach ( $parent_search as $parent ) {
				if ( get_post( $parent->origin_id )->post_parent === 0 ) {
					$post_id = $parent->origin_id;
					break;
				}
			}
		}

		if ( empty( $title ) ) {
			$title = $series;
		}

		$cp_hash = md5( $title . $date . $location_id );

		// update existing post if it exists
		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from $wpdb->postmeta WHERE `meta_key` = '_cp_import_id' AND `meta_value` = %s", $cp_hash ) );

		if ( empty( $post_id ) ) {
			$post_type = cp_library()->setup->post_types->item->post_type;
			$post_id   = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type=%s", $title, $post_type ) );
		}

		$args = [
			'post_type'    => Item::get_prop( 'post_type' ),
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_date'    => gmdate( 'Y-m-d 9:00:00', $date ),
			'post_content' => wp_kses_post( str_replace( '\n', "\n", $desc ) ),
		];

		if ( ! $post_id ) {
			// update existing post if it exists
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from $wpdb->postmeta WHERE `meta_key` = '_cp_import_id' AND `meta_value` = %s", $cp_hash ) );
		}

		if ( $post_id ) {
			$args['ID'] = $post_id;
		}

		$message_id = wp_insert_post( $args, true );

		if ( is_wp_error( $message_id ) ) {
			throw new Exception( esc_html( $message_id->get_error_message() ) );
		}

		update_post_meta( $message_id, '_cp_import_id', $cp_hash );
		update_post_meta( $message_id, '_cp_import_batch_id', $import_id );
		update_post_meta( $message_id, '_cp_import_data', $item );

		// add taxonomy
		if ( $location_id ) {
			wp_set_post_terms( $message_id, $location_id, 'cp_location' );
		}

		// save to our tables
		$item = Item::get_instance_from_origin( $message_id );

		if ( $thumb && get_post_meta( $message_id, '_cp_import_thumb', true ) !== $thumb ) {
			$this->set_image( $message_id, $thumb );
			update_post_meta( $message_id, '_cp_import_thumb', $thumb );
		}

		if ( ! empty( $topics ) ) {
			$this->set_taxonomy_terms( $message_id, $topics, Topic::get_instance()->taxonomy );
		}

		if ( ! empty( $scripture ) ) {
			$item->update_scripture( $scripture );
		}

		if ( ! empty( $season ) ) {
			$this->set_taxonomy_terms( $message_id, $season, Season::get_instance()->taxonomy );
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
					$series_id = wp_insert_post(
						[
							'post_type'   => ItemType::get_prop( 'post_type' ),
							'post_title'  => $series,
							'post_status' => 'publish',
						],
						true
					);

					if ( is_wp_error( $series_id ) ) {
						throw new Exception( esc_html( $series_id->get_error_message() ) );
					}

					// get the id
					$series_id = ItemType::get_instance_from_origin( $series_id )->id;
				}
			}

			if ( $series_id ) {
				$item->update_types( [ $series_id ] );
			}
		}

		// Handle Service Types if they are not set as the variation source
		if ( cp_library()->setup->post_types->service_type_enabled() && cp_library()->setup->variations->get_source() !== cp_library()->setup->post_types->service_type->post_type ) {
			$types = $this->get_service_type_ids( $service_type );
			$item->update_service_types( $types );
		}

		if ( $variation ) {
			$variation_id = array_search( $variation, $variation_options );

			if ( false === $variation_id ) {
				throw new Exception( 'The provided variation could not be found in ' . cp_library()->setup->variations->get_source_label() );
			}

			$variant = $item->update_variant(
				[
					'video_url'      => $video,
					'audio_url'      => $audio,
					'speakers'       => $this->get_speaker_ids( $speakers, $location_id ),
					'variation_id'   => $variation_id,
					'variation_type' => cp_library()->setup->variations->get_source(),
				]
			);

			do_action( 'cp_library_import_process_step_variant', $variant, $item, $row, $options, $this, $index, $i );
		} else {

			// only process the below fields if this is not a variation item. Otherwise, these fields are stored
			// in the variant

			if ( $video ) {
				update_post_meta( $item->origin_id, 'video_url', $video );
				$item->update_meta_value( 'video_url', $video );
			}

			if ( strstr( $audio, 'w.soundcloud.com/player' ) ) {
				$audio = $this->get_soundcloud_url( $audio );
			}

			if ( $audio ) {
				$audio_url = $audio;
				if ( $options['sideload_audio'] ) {
					$sideloaded_media_url = $this->sideload_media_and_get_url( $message_id, $audio );
					if ( $sideloaded_media_url ) {
						$audio_url = $sideloaded_media_url;
					}
				}
				update_post_meta( $message_id, 'audio_url', $audio_url );
				$item->update_meta_value( 'audio_url', $audio_url );
				$controller = new \CP_Library\Controllers\Item( $item->origin_id );
				$controller->do_enclosure();
			}

			if ( ! empty( $downloads ) ) {
				$download_urls = explode( ',', $downloads );
				$downloads     = [];

				foreach ( $download_urls as $download_url ) {
					$download_url  = explode( '|', $download_url );
					$download_name = '';

					if ( count( $download_url ) > 1 ) {
						$download_name = $download_url[0];
						$download_url  = $download_url[1];
					} else {
						$download_url = $download_url[0];
					}

					if ( $options['sideload_downloads'] ) {
						$sideloaded_media_url = $this->sideload_media_and_get_url( $message_id, $download_url );

						if ( $sideloaded_media_url ) {
							$download = array(
								'file' => $sideloaded_media_url,
								'name' => $download_name,
							);

							if ( $attachment_id = attachment_url_to_postid( $sideloaded_media_url ) ) {
								$download['file_id'] = $attachment_id;
							}

							$downloads[] = $download;
						}
					} else {
						$downloads[] = array(
							'file' => $download_url,
							'name' => $download_name,
						);
					}
				}

				update_post_meta( $message_id, 'downloads', $downloads );
			}

			// Handle message speakers
			if ( cp_library()->setup->post_types->speaker_enabled() ) {
				$speaker_ids = $this->get_speaker_ids( $speakers, $location_id );
				$item->update_speakers( $speaker_ids );
			}
		} // endif ( $variant )
	}

	/**
	 * Get the ids for the provided speakers. Create them if they do not already exist.
	 *
	 * @since  1.1.0
	 *
	 * @param array       $speakers List of speaker names from the import file.
	 * @param bool|number $location_id Location ID to assign to the speaker.
	 * @return string[] Array of speaker ID => speaker name mapping.
	 * @throws Exception If there is an error inserting or fetching a speaker.
	 * @author Tanner Moushey, 5/25/23
	 */
	protected function get_speaker_ids( $speakers, $location_id = false ) {
		$all_speakers = $this->maybe_get_speakers();

		$speaker_ids = [];

		if ( empty( $speakers ) || false === $all_speakers ) {
			return $speaker_ids;
		}

		foreach ( $speakers as $speaker ) {
			$speaker_id = array_search( strtolower( $speaker ), $all_speakers, true );

			if ( false === $speaker_id ) {
				$speaker_id = wp_insert_post(
					[
						'post_type'   => Speaker::get_prop( 'post_type' ),
						'post_title'  => $speaker,
						'post_status' => 'publish',
					],
					true
				);

				if ( is_wp_error( $speaker_id ) ) {
					throw new Exception( esc_html( $speaker_id->get_error_message() ) );
				}

				// get the id and save it to our array
				$speaker_id                     = Speaker::get_instance_from_origin( $speaker_id )->id;
				self::$_speakers[ $speaker_id ] = strtolower( $speaker );
			}

			if ( $location_id && $speaker_id ) {
				// add the current location to the speaker's location
				wp_set_post_terms( Speaker::get_instance( $speaker_id )->origin_id, $location_id, 'cp_location', true );
			}

			$speaker_ids[] = $speaker_id;
		}

		return $speaker_ids;
	}

	/**
	 * Get the ids for the provided service types. Create those that don't exist.
	 *
	 * @since  1.1.0
	 *
	 * @param array $service_types List of service type names from the import file.
	 *
	 * @return number[] Array of service type IDs.
	 * @throws Exception If there is an error inserting a service type.
	 * @author Tanner Moushey, 5/25/23
	 */
	protected function get_service_type_ids( $service_types ) {
		$types              = [];
		$service_type_names = $this->maybe_get_service_types();
		foreach ( $service_types as $type ) {
			$type_id = array_search( strtolower( $type ), $service_type_names );

			if ( false === $type_id ) {
				$post_id = wp_insert_post(
					[
						'post_type'   => ServiceType::get_prop( 'post_type' ),
						'post_title'  => $type,
						'post_status' => 'publish',
					],
					true
				);

				if ( is_wp_error( $post_id ) ) {
					throw new Exception( esc_html( $post_id->get_error_message() ) );
				}

				// get the id and save it to our array
				$type_id                          = ServiceType::get_instance_from_origin( $post_id )->id;
				self::$_service_types[ $type_id ] = strtolower( $type );
			}

			$types[] = $type_id;
		}

		return $types;
	}

	/**
	 * Get all registered locations (CP Locations integration)
	 *
	 * @since  1.0.4
	 * @return array|false Array of locations or false if CP Locations is not enabled.
	 * @author Tanner Moushey
	 */
	public function maybe_get_locations() {
		if ( ! class_exists( 'CP_Locations\Models\Location' ) ) {
			return false;
		}

		$all_locations = [ 'global' => 'global' ];

		foreach ( \CP_Locations\Models\Location::get_all_locations() as $location ) {
			$all_locations[ 'location_' . $location->origin_id ] = strtolower( $location->title ); // set the term id as the key
		}

		return $all_locations;
	}

	/**
	 * Get all speakers or false if disabled
	 *
	 * @since  1.0.4
	 * @return array|false Array of speaker ID => speaker name mapping, or false if speakers are disabled.
	 * @author Tanner Moushey
	 */
	public function maybe_get_speakers() {
		if ( ! cp_library()->setup->post_types->speaker_enabled() ) {
			return false;
		}

		if ( false === self::$_speakers ) {
			$all_speakers = [];

			foreach ( Speaker::get_all_speakers() as $speaker ) {
				$all_speakers[ $speaker->id ] = strtolower( $speaker->title );
			}

			self::$_speakers = $all_speakers;
		}

		return self::$_speakers;
	}

	/**
	 * Get all service types or false if disabled
	 *
	 * @since  1.0.4
	 *
	 * @return array|false Array of service type ID => service type name mapping, or false if service types are disabled.
	 * @author Tanner Moushey
	 */
	public function maybe_get_service_types() {
		if ( ! cp_library()->setup->post_types->service_type_enabled() ) {
			return false;
		}

		if ( false === self::$_service_types ) {
			$service_types = [];

			foreach ( ServiceType::get_all_service_types() as $type ) {
				$service_types[ $type->id ] = strtolower( $type->title );
			}

			self::$_service_types = $service_types;
		}

		return self::$_service_types;
	}

	/**
	 * Get the default series, or create one if it doesn't exist.
	 *
	 * @since  1.0.4
	 * @return number|false The series ID or false if the series is disabled.
	 * @throws Exception If there is an error fetching or inserting the series.
	 * @author Tanner Moushey
	 */
	public function maybe_get_default_series() {
		if ( ! cp_library()->setup->post_types->item_type_enabled() ) {
			return false;
		}

		if ( ! apply_filters( 'cp_library_import_sermons_use_default_series', true ) ) {
			return false;
		}

		$no_series_title = apply_filters( 'cp_library_import_sermons_default_series_title', __( 'No Series', 'cp-library' ) );

		$results = ItemType::search( 'title', $no_series_title );

		if ( $results ) {
			return $results[0]->id;
		}

		$series_id = wp_insert_post(
			[
				'post_type'   => ItemType::get_prop( 'post_type' ),
				'post_title'  => $no_series_title,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $series_id ) ) {
			throw new Exception( $series_id->get_error_message() );
		}

		return ItemType::get_instance_from_origin( $series_id )->id;
	}

	/**
	 * Get SoundCloud canonical url from embed code
	 *
	 * @param string $embed SoundCloud embed code.
	 * @return string SoundCloud canonical url.
	 * @throws Exception If there is an error fetching the SoundCloud url.
	 * @since 1.0.0
	 * @author Tanner Moushey
	 */
	public function get_soundcloud_url( $embed ) {
		$response = wp_remote_get( $embed );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Could not get connect to ' . $embed );
		}

		if ( ! preg_match( '/<link rel="canonical" href="([^"]*)/', $response['body'], $url ) ) {
			throw new Exception( 'Could not get url from ' . $embed );
		}

		return $url[1];
	}

	/**
	 * Set up and taxonomy terms
	 *
	 * @since 1.0.4
	 * @return void
	 */
	private function set_taxonomy_terms( $post_id = 0, $terms = array(), $taxonomy = 'category' ) {
		$terms = $this->maybe_create_terms( $terms, $taxonomy );

		if ( ! empty( $terms ) ) {
			wp_set_object_terms( $post_id, $terms, $taxonomy );
		}
	}

	/**
	 * Locate term IDs or create terms if none are found.
	 *
	 * @param array  $terms Array of term names.
	 * @param string $taxonomy Taxonomy to search.
	 * @return int[] Array of term IDs.
	 * @since 1.0.4
	 */
	private function maybe_create_terms( $terms = array(), $taxonomy = 'category' ) {
		// Return of term IDs
		$term_ids = array();

		foreach ( $terms as $term ) {

			if ( is_numeric( $term ) && 0 === (int) $term ) {

				$t = get_term( $term, $taxonomy );

			} else {

				$t = get_term_by( 'name', $term, $taxonomy );

				if ( ! $t ) {

					$t = get_term_by( 'slug', $term, $taxonomy );

				}
			}

			if ( ! empty( $t ) ) {

				$term_ids[] = $t->term_id;

			} else {

				$term_data = wp_insert_term( $term, $taxonomy, array( 'slug' => sanitize_title( $term ) ) );

				if ( ! is_wp_error( $term_data ) ) {

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
			'post_type' => $post_type,
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
