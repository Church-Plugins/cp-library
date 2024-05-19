<?php
/**
 * SermonAudio adapter functionality.
 *
 * @package CP_Library
 */

namespace CP_Library\Adapters;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * SermonAudio adapter subclass
 *
 * @since 1.3.0
 */
class SermonAudio extends Adapter {

	/**
	 * The base url for the Sermon Audio API
	 *
	 * @var string
	 */
	public $base_url;

	/**
	 * Gets all sermons since sermon audio doesn't provide a good way to only get sermons since a certain date
	 *
	 * @var \stdClass[]
	 */
	protected $pulled_sermons = [];

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->base_url = 'https://api.sermonaudio.com/v2/node/sermons';

		$this->type         = 'sermon_audio';
		$this->display_name = __( 'Sermon Audio', 'cp-library' );

		parent::__construct();
	}

	/**
	 * Performs a hard pull
	 *
	 * @return void
	 */
	public function hard_pull() {
		if ( empty( $this->get_setting( 'broadcaster_id', false ) ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid broadcaster ID', 'cp-library' ) ) );
		}

		try {
			$sermons = $this->fetch_all_since_date();
		} catch ( \ChurchPlugins\Exception $e ) {
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}

		$this->format_and_process( $sermons, true );
	}

	/**
	 * Formats and enqueues items to be processed
	 *
	 * @param \stdClass[] $sermons The sermons to format and enqueue.
	 * @param bool        $hard_pull Whether or not this is a hard pull.
	 * @return void
	 */
	public function format_and_process( $sermons, $hard_pull = false ) {
		$items      = array();
		$speakers   = array();
		$item_types = array();

		foreach ( $sermons as $sermon ) {
			$item = $this->format_item( $sermon );

			$item['attachments'] = array();

			if ( (bool) $sermon->speaker && cp_library()->setup->post_types->speaker_enabled() ) {
				$speaker = $this->format_speaker( $sermon->speaker );
				$speakers[ $sermon->speaker->speakerID ] = $speaker;
				$item['attachments']['cpl_speaker'] = [ $sermon->speaker->speakerID ];
			}

			if ( (bool) $sermon->series && cp_library()->setup->post_types->item_type_enabled() ) {
				$item_type = $this->format_item_type( $sermon->series );
				$item_types[ $sermon->series->seriesID ] = $item_type;
				$item['attachments']['cpl_item_type'] = [ $sermon->series->seriesID ];
			}

			$items[ $sermon->sermonID ] = $item;
		}

		// enqueues items to be processed
		$this->enqueue( $items );

		// enqueues attachments to be processed with the items
		$this->add_attachments( $speakers, 'cpl_speaker' );
		$this->add_attachments( $item_types, 'cpl_item_type' );
		$this->process( $hard_pull );
	}

	/**
	 * Implements the pulling functionality used by the parent
	 *
	 * @param int $amount The amount of items to pull.
	 * @param int $page The page to pull from.
	 * @return bool Whether or not there are more pages.
	 */
	public function pull( int $amount, int $page ) {
		$query = array(
			'pageSize'      => $amount,
			'page'          => $page,
			'sortBy'        => 'updated',
			'broadcasterID' => $this->get_setting( 'broadcaster_id', '' ),
		);

		$data    = $this->get_results( $query );
		$results = array_filter( $data->results, [ $this, 'is_valid_result' ] );

		$this->format_and_process( $results, false );

		// whether or not there are more pages
		return (bool) $data->next;
	}

	/**
	 * Gets results from sermon audio based on a query
	 *
	 * @param array $query The url query array.
	 * @return \stdClass The results from Sermon Audio.
	 * @throws \ChurchPlugins\Exception If there is an error with the request.
	 * @updated 1.4.1 Sermon Audio API now requires an API key as part of the request.
	 */
	protected function get_results( $query ) {
		$url = add_query_arg( $query, $this->base_url );

		$api_key = $this->get_setting( 'api_key', '' );

		if ( empty( $api_key ) ) {
			throw new \ChurchPlugins\Exception( esc_html__( 'Invalid API key', 'cp-library' ) );
		}

		$headers = array(
			'X-Api-Key' => $api_key,
		);

		$response = wp_remote_get( $url, [ 'timeout' => 100, 'headers' => $headers ] );

		if ( is_wp_error( $response ) ) {
			throw new \ChurchPlugins\Exception( esc_html( $response->get_error_message() ) );
		}

		$data = json_decode( $response['body'] );

		if ( isset( $data->errors ) ) {
			$errors = (array) $data->errors;
			$key    = array_keys( $errors )[0];
			$value  = $errors[ $key ];
			throw new \ChurchPlugins\Exception( esc_html( $value ) );
		}

		return $data;
	}

	/**
	 * Loads all results from sermon audio, looping through pages and accumulating data
	 *
	 * @param array $query The url query array
	 * @return \stdClass[] The results from Sermon Audio
	 */
	protected function load_results( $query ) {
		unset( $query['page'] );

		$results = [];
		$page = 1;

		// loop through all pages
		do {
			$query['page'] = $page;
			$data          = $this->get_results( $query );
			$results       = array_merge( $results, $data->results );
			$page++;
		} while ( $data->next );

		return $results;
	}

	/**
	 * Loads all sermons since the user specified date. Sermon audio doesn't provide a simple way to do this, so some custom logic is required
	 */
	protected function fetch_all_since_date() {
		$sermons = [];

		$date = new \DateTime( $this->get_setting( 'ignore_before', '@0' ) );

		$current_year = (int) gmdate( 'Y' );
		$min_year     = (int) $date->format( 'Y' );

		// loop through years up to the current date
		for ( $year = $min_year; $year <= $current_year; $year++ ) {
			$query = array(
				'pageSize'      => 100,
				'broadcasterID' => $this->get_setting( 'broadcaster_id', '' ),
				'year'          => $year,
			);

			$results = $this->load_results( $query );
			$results = array_filter( $results, [ $this, 'is_valid_result' ] );
			$sermons = array_merge( $sermons, $results );
		}

		return $sermons;
	}

	/**
	 * Checks if a sermon is valid, meaning it is after the specified cutoff date
	 *
	 * @param \stdClass $sermon The sermon to check
	 * @return bool Whether or not the sermon is valid
	 */
	protected function is_valid_result( $sermon ) {
		$min_date    = strtotime( $this->get_setting( 'ignore_before', '0' ) );
		$sermon_date = strtotime( $sermon->preachDate );
		return $sermon_date >= $min_date;
	}

	/**
	 * Formats a Sermon
	 *
	 * @param \stdClass $item The sermon to format.
	 * @return array The formatted sermon.
	 */
	public function format_item( $item ) {
		$args = array(
			'external_id'  => $item->sermonID,
			'post_title'   => $item->displayTitle,
			'post_date'    => gmdate( 'Y-m-d H:i:s', $item->publishTimestamp ),
			'post_status'  => 'publish',
			'post_type'    => cp_library()->setup->post_types->item->post_type,
			'post_content' => wp_kses_post( $item->moreInfoText ),
			'meta_input'   => array(),
			'cpl_data'     => array(),
			'taxonomies'   => array(),
		);

		if ( $item->hasAudio ) {
			$args['meta_input']['audio_url'] = $item->media->audio[0]->downloadURL;
		}

		if ( $item->hasVideo ) {
			$args['meta_input']['video_url'] = $item->media->video[0]->streamURL;
		}

		if ( $item->bibleText ) {
			$args['cpl_data']['scripture'] = $item->bibleText;
		}

		if ( $item->eventType ) {
			$args['cpl_data']['service_type'] = $item->eventType;
		}

		return $args;
	}

	/**
	 * Formats a Series
	 *
	 * @param \stdClass $item_type The series to format.
	 * @return array The formatted series.
	 */
	public function format_item_type( $item_type ) {
		$args = array(
			'external_id'  => $item_type->seriesID,
			'post_title'   => $item_type->title,
			'post_status'  => 'publish',
			'post_type'    => cp_library()->setup->post_types->item_type->post_type,
			'post_content' => wp_kses_post( $item_type->description ),
			'cpl_data'     => array(),
		);

		if ( $item_type->image ) {
			$args['cpl_data']['featured_image'] = $item_type->image;
		} elseif ( $item_type->broadcaster->imageURL ) {
			$args['cpl_data']['featured_image'] = $item_type->broadcaster->imageURL;
		}

		return $args;
	}

	/**
	 * Formats a Speaker
	 *
	 * @param \stdClass $speaker The speaker to format.
	 * @return array The formatted speaker.
	 */
	public function format_speaker( $speaker ) {
		$args = array(
			'external_id'  => $speaker->speakerID,
			'post_title'   => $speaker->displayName,
			'post_status'  => 'publish',
			'post_type'    => cp_library()->setup->post_types->speaker->post_type,
			'post_content' => wp_kses_post( $speaker->bio ),
			'cpl_data'     => array(),
		);

		if ( $speaker->portraitURL ) {
			$args['cpl_data']['featured_image'] = $speaker->portraitURL;
		}

		return $args;
	}

	/**
	 * Gets a model class based on a key
	 *
	 * @param string $key The key to get the model for.
	 * @return string|\ChurchPlugins\Models\Table|false
	 */
	public function get_model_from_key( $key ) {
		switch ( $key ) {
			case 'cpl_speaker':
				return \CP_Library\Models\Speaker::class;
			case 'cpl_item_type':
				return \CP_Library\Models\ItemType::class;
			case 'cpl_item':
				return \CP_Library\Models\Item::class;
			default:
				return false;
		}
	}

	/**
	 * Adds an attachment to a sermon
	 *
	 * @param \CP_Library\Models\Item $item The item to add the attachment to.
	 * @param mixed                   $attachment The attachment to add.
	 * @param string                  $attachment_key The key of the attachment.
	 */
	public function add_attachment( $item, $attachment, $attachment_key ) {
		if ( 'cpl_speaker' === $attachment_key ) {
			$item->update_speakers( [ $attachment->id ] );
		} elseif ( 'cpl_item_type' === $attachment_key ) {
			$item->add_type( $attachment->id );
		}
	}

	/**
	 * Handle processing custom data
	 *
	 * @param \CP_Library\Models\Item $item The item to process.
	 * @param array                   $cpl_data The custom data to process.
	 * @param string                  $post_type The item's post type.
	 */
	public function process_cpl_data( $item, $cpl_data, $post_type ) {
		if ( 'cpl_item' === $post_type ) {
			if ( isset( $cpl_data['scripture'] ) ) {
				$item->update_scripture( explode( ';', $cpl_data['scripture'] ) );
			}

			// Service type
			if ( isset( $cpl_data['service_type'] ) ) {

				// create service type if it doesn't exist
				$service_type_slug = sanitize_title( $cpl_data['service_type'] );
				$service_type_post = get_posts(
					array(
						'name'           => $service_type_slug,
						'post_type'      => cp_library()->setup->post_types->service_type->post_type,
						'posts_per_page' => 1,
					)
				);
				$service_type_post = current( $service_type_post );

				if ( ! $service_type_post ) {
					$service_type_post = wp_insert_post(
						array(
							'post_title'  => $cpl_data['service_type'],
							'post_name'   => $service_type_slug,
							'post_status' => 'publish',
							'post_type'   => cp_library()->setup->post_types->service_type->post_type,
						)
					);
				}

				if ( is_wp_error( $service_type_post ) ) {
					error_log( $service_type_post->get_error_message() );
					return;
				}

				$service_type_post = get_post( $service_type_post ); // make sure we have the post object

				$service_type_model = \CP_Library\Models\ServiceType::get_instance_from_origin( $service_type_post->ID );

				$item->update_service_types( [ $service_type_model->id ] );
			}
		}
	}
}