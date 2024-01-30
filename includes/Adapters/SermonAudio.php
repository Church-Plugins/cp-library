<?php

namespace CP_Library\Adapters;

use CP_Library\Admin\Settings;

class SermonAudio extends Adapter {
	public $base_url;

	/**
	 * Gets all sermons since sermon audio doesn't provide a good way to only get sermons since a certain date
	 */
	protected $pulled_sermons = [];

	public function __construct() {
		$this->base_url = 'https://api.sermonaudio.com/v2/node/sermons';

		$this->type = 'sermon_audio';
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

		$sermons = $this->fetch_all_since_date();
		$this->format_and_process( $sermons, true );
	}

	/**
	 * Formats and enqueues items to be processed
	 * 
	 * @return void
	 */
	public function format_and_process( $sermons, $hard_pull = false ) {
		$items = array();
		$speakers = array();
		$item_types = array();

		foreach( $sermons as $sermon ) {
			$item = $this->format_item( $sermon );

			$item['attachments'] = array();

			if( (boolean) $sermon->speaker && cp_library()->setup->post_types->speaker_enabled() ) {
				$speaker = $this->format_speaker( $sermon->speaker );
				$speakers[$sermon->speaker->speakerID] = $speaker;
				$item['attachments']['cpl_speaker'] = [ $sermon->speaker->speakerID ];
			}

			if( (boolean) $sermon->series && cp_library()->setup->post_types->item_type_enabled() ) {
				$item_type = $this->format_item_type( $sermon->series );
				$item_types[$sermon->series->seriesID] = $item_type;
				$item['attachments']['cpl_item_type'] = [ $sermon->series->seriesID ];
			}

			$items[$sermon->sermonID] = $item;
		}

		// enqueues items to be processed
		$this->enqueue( $items );

		// enqueues attachments to be processed with the items
		$this->add_attachments( $speakers,   'cpl_speaker' );
		$this->add_attachments( $item_types, 'cpl_item_type' );
		$this->process( $hard_pull );
	}

	/**
	 * Implements the pulling functionality used by the parent
	 */
	public function pull( int $amount, int $page ) {
		$query = array(
			'pageSize' => $amount,
			'page' => $page,
			'sortBy' => 'updated',
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
	 * @param array $query The url query array 
	 */
	protected function get_results( $query ) {
		$url = add_query_arg( $query, $this->base_url );

		$response = wp_remote_get($url);

		if( is_wp_error( $response ) ) {
			throw new \ChurchPlugins\Exception( $response->get_error_message() );
		}

		$data = json_decode( $response['body'] );

		if( isset( $data->errors ) ) {
			$errors = (array) $data->errors;
			$key = array_keys( $errors )[0];
			$value = $errors[$key];
			throw new \ChurchPlugins\Exception( "$key: $value" );
		}

		return $data;
	}

	/**
	 * Loads all results from sermon audio, looping through pages and accumulating data
	 * 
	 * @param array $query The url query array
	 */
	protected function load_results( $query ) {
		unset( $query['page'] );

		$results = [];
		$page = 1;

		// loop through all pages
		do {
			$query['page'] = $page;
			try {
				$data = $this->get_results( $query );
			} catch( \ChurchPlugins\Exception $e ) {
				error_log( $e->getMessage() );
				break;
			}
			$results = array_merge( $results, $data->results );
			$page++;
		} while( $data->next );

		return $results;
	}

	/**
	 * Loads all sermons since the user specified date. Sermon audio doesn't provide a simple way to do this, so some custom logic is required
	 */
	protected function fetch_all_since_date() {
		$sermons = [];

		$date = new \DateTime( $this->get_setting( 'ignore_before', '@0' ) );

		$current_year = (int) date( 'Y' );
		$min_year     = (int) $date->format( 'Y' );

		// loop through years up to the current date
		for ( $year = $min_year; $year <= $current_year; $year++ ) {
			$query = array(
				'pageSize' => 100,
				'broadcasterID' => $this->get_setting( 'broadcaster_id', '' ),
				'year' => $year
			);
			
			$results = $this->load_results( $query );
			$results = array_filter( $results, [ $this, 'is_valid_result' ] );
			$sermons = array_merge( $sermons, $results );
		}

		return $sermons;
	}

	/**
	 * Checks if a sermon is valid, meaning it is after the specified cutoff date
	 */
	protected function is_valid_result( $sermon ) {
		$min_date = strtotime( $this->get_setting( 'ignore_before', '0' ) );
		$sermon_date = strtotime( $sermon->preachDate );
		return $sermon_date >= $min_date;
	}
	
	/**
	 * Formats a Sermon
	 */
	public function format_item( $item ) {
		$args = array(
			'external_id' => $item->sermonID,
			'post_title' => $item->displayTitle,
			'post_date' => gmdate( 'Y-m-d H:i:s', $item->publishTimestamp ),
			'post_status' => 'publish',
			'post_type' => cp_library()->setup->post_types->item->post_type,
			'post_content' => wp_kses_post( $item->moreInfoText ),
			'meta_input' => array(),
			'cpl_data' => array()
		);

		if( $item->hasAudio ) {
			$args['meta_input']['audio_url'] = $item->media->audio[0]->downloadURL;
		}

		if( $item->hasVideo ) {
			$args['meta_input']['video_url'] = $item->media->video[0]->streamURL;
		}

		if( $item->bibleText ) {
			$args['cpl_data']['scripture'] = $item->bibleText;
		}

		return $args;
	}

	/**
	 * Formats a Series
	 */
	public function format_item_type( $item_type ) {
	 	$args = array(
			'external_id'  => $item_type->seriesID,
			'post_title'   => $item_type->title,
			'post_status'  => 'publish',
			'post_type'    => cp_library()->setup->post_types->item_type->post_type,
			'post_content' => wp_kses_post( $item_type->description ),
			'cpl_data'     => array()
		);

		if( $item_type->image ) {
			$args['cpl_data']['featured_image'] = $item_type->image;
		}
		else if( $item_type->broadcaster->imageURL ) {
			$args['cpl_data']['featured_image'] = $item_type->broadcaster->imageURL;
		}

		return $args;
	}

	/**
	 * Formats a Speaker
	 */
	public function format_speaker( $speaker ) {
		$args = array(
			'external_id'  => $speaker->speakerID,
			'post_title'   => $speaker->displayName,
			'post_status'  => 'publish',
			'post_type'    => cp_library()->setup->post_types->speaker->post_type,
			'post_content' => wp_kses_post( $speaker->bio ),
			'cpl_data'     => array()
		);

		if( $speaker->portraitURL ) {
			$args['cpl_data']['featured_image'] = $speaker->portraitURL;
		}

		return $args;
	}

	/**
	 * Gets a model class based on a key
	 * 
	 * @return string|\ChurchPlugins\Models\Table|false
	 */
	public function get_model_from_key($key) {
		switch( $key ) {
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
	 * @param \CP_Library\Models\Item $item
	 * @param mixed $attachment
	 * @param string $attachment_key
	 */
	public function add_attachment( $item, $attachment, $attachment_key ) {
		if( $attachment_key === 'cpl_speaker' ) {
			$item->update_speakers( [ $attachment->id ] );
		}
		else if( $attachment_key === 'cpl_item_type' ) {
			$item->add_type( $attachment->id );
		}
	}

	/**
	 * Handle processing custom data
	 * 
	 * @param \CP_Library\Models\Item $item
	 * @param array $data
	 * @param string $post_type
	 */
	public function process_cpl_data( $item, $cpl_data, $post_type ) {
		if( $post_type === 'cpl_item' ) {
			if( isset( $cpl_data['scripture'] ) ) {
				$item->update_scripture( $cpl_data['scripture'] );
			}
		}
	}
} 




