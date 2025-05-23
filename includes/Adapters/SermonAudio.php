<?php
/**
 * SermonAudio adapter functionality.
 *
 * @package CP_Library
 */

namespace CP_Library\Adapters;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
use CP_Library\Controllers\Item;

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
		$this->base_url = 'https://api.sermonaudio.com/v2';

		$this->type         = 'sermon_audio';
		$this->display_name = __( 'Sermon Audio', 'cp-library' );

		parent::__construct();

		add_filter( 'cpl_item_get_video', [ $this, 'video_redirect' ], 10, 2 );
	}

	/**
	 * Check if SermonAudio url needs redirect
	 *
	 * @param $value
	 * @param $item Item
	 *
	 * @return mixed
	 * @since  1.5.0
	 *
	 * @author Tanner Moushey, 10/15/24
	 */
	public function video_redirect( $value, $item ) {

		if ( empty( $value ) || empty( $value['value'] ) ) {
			return $value;
		}

		if ( ! str_contains( $value['value'], 'sermonaudio.com' ) ) {
			return $value;
		}

		$redirect  = get_post_meta( $item->post->ID, '_video_url_redirect', true );
		$timestamp = get_post_meta( $item->post->ID, '_video_url_redirect_time', true );

		if ( $redirect === 'found' ) {
			return $value;
		}

		if ( $redirect && $timestamp && $timestamp + DAY_IN_SECONDS > time() ) {
			$value['value'] = $redirect;
			return $value;
		}

		// Perform a head request to get only headers
		$response = wp_remote_head( $value['value'], [ 'redirection' => 0 ] ); // No redirection follow

		// Check for WP error
		if ( is_wp_error( $response ) ) {
			return $value;
		}

		// Get the response code
		$response_code = wp_remote_retrieve_response_code($response);

		// Check if it's a redirect (3xx response code)
		if ($response_code >= 300 && $response_code < 400) {
			// Get the 'Location' header
			$new_url = wp_remote_retrieve_header($response, 'location');

			update_post_meta( $item->post->ID, '_video_url_redirect', $new_url );
			update_post_meta( $item->post->ID, '_video_url_redirect_time', time() );

			$value['value'] = $new_url;
		} else {
			update_post_meta( $item->post->ID, '_video_url_redirect', 'found' );
		}

		return $value;
	}

	/**
	 * Formats and enqueues items to be processed
	 *
	 * @param \stdClass[] $items The items to format and enqueue.
	 * @return void
	 */
	public function format_and_process( $items ) {
		$sermons    = array();
		$speakers   = array();
		$item_types = array();

		foreach ( $items as $sermon ) {
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

			$sermons[ $sermon->sermonID ] = apply_filters( 'cp_library_sermon_audio_item', $item );
		}

		// enqueues items to be processed
		$this->enqueue( $sermons );

		// enqueues attachments to be processed with the items
		$this->add_attachments( $speakers, 'cpl_speaker' );
		$this->add_attachments( $item_types, 'cpl_item_type' );
		$this->process_batch();
	}

	/**
	 * Fetch most recently updated items from API
	 *
	 * @param int $amount The amount of items to fetch.
	 * @return array The most recent items.
	 */
	public function get_recent_items( $amount ) {
		$query = array(
			'pageSize'               => $amount,
			'broadcasterID'          => $this->get_setting( 'broadcaster_id', '' ),
			'sortBy'                 => 'newest',
			'preachedAfterTimestamp' => strtotime( $this->get_setting( 'ignore_before', 0 ) ),
			'cache'                  => true
		);

		$data = $this->fetch( '/node/sermons', $query );

		if ( ! $data->results ) {
			return [];
		}

		$sermon_ids = wp_list_pluck( $data->results, 'sermonID' );

		$query = array(
			'pageSize'        => 100,
			'broadcasterID'   => $this->get_setting( 'broadcaster_id', '' ),
			'liteBroadcaster' => true,
			'sermonIDs'       => implode( ',', $sermon_ids ),
		);

		$data = $this->fetch( '/node/sermons', $query );

		return $data->results ?? [];
	}

	/**
	 * Pull a batch of items from the source
	 *
	 * @param int $batch The current batch number.
	 * @return array|false The next batch of items to process, or false if there are no more items to process.
	 */
	public function get_next_batch( $batch ) {
		$query = array(
			'pageSize'               => 100,
			'broadcasterID'          => $this->get_setting( 'broadcaster_id', '' ),
			'sortBy'                 => 'oldest',
			'page'                   => $batch,
			'preachedAfterTimestamp' => strtotime( $this->get_setting( 'ignore_before', 0 ) ),
			'cache'                  => true
		);

		$data = $this->fetch( '/node/sermons', $query );

		if ( empty( $data->results ) ) {
			return false;
		}

		$sermon_ids = wp_list_pluck( $data->results, 'sermonID' );

		$query = array(
			'pageSize'        => 100,
			'broadcasterID'   => $this->get_setting( 'broadcaster_id', '' ),
			'liteBroadcaster' => true,
			'sermonIDs'       => implode( ',', $sermon_ids ),
		);

		$data = $this->fetch( '/node/sermons', $query );

		return $data->results ? $data->results : false;
	}

	/**
	 * Makes request to Sermon Audio API
	 *
	 * @param string $path The path to request.
	 * @param array  $query Query arguments.
	 * @return \stdClass The results from Sermon Audio.
	 * @throws \ChurchPlugins\Exception If there is an error with the request.
	 * @since 1.5.0
	 */
	protected function fetch( $path, $query = [] ) {
		$url = add_query_arg( $query, $this->base_url . $path );

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
	 * Formats a Sermon
	 *
	 * @param \stdClass $item The sermon to format.
	 * @return array The formatted sermon.
	 */
	public function format_item( $item ) {

		if ( ! empty( $item->preachDate ) && $date = strtotime( $item->preachDate ) ) {
			$post_date = gmdate( 'Y-m-d H:i:s', $date + 12 * HOUR_IN_SECONDS ); // set publish time to noon
		} else {
			$post_date = gmdate( 'Y-m-d H:i:s', $item->publishTimestamp );
		}

		$args = array(
			'external_id'  => $item->sermonID,
			'post_title'   => $item->displayTitle,
			'post_date'    => $post_date,
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
			if ( isset( $cpl_data['service_type'] ) && cp_library()->setup->post_types->service_type_enabled() ) {

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
