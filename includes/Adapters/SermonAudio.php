<?php

namespace CP_Library\Adapters;

use CP_Library\Admin\Settings;

class SermonAudio extends Adapter {
	public $base_url;

	public function __construct() {
		$this->base_url = 'https://api.sermonaudio.com/v2/node/sermons';

		$this->type = 'sermon_audio';
		$this->display_name = __( 'Sermon Audio', 'cp-library' );
		
		parent::__construct();
	}

	public function pull( int $amount, int $page ) {
		$url = add_query_arg( array(
			'pageSize' => $amount,
			'page' => $page,
			'sortBy' => 'updated',
			'broadcasterID' => $this->get_setting( 'api_key', '' )
		), $this->base_url );

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


		$items = array();
		$speakers = array();
		$item_types = array();

		foreach( $data->results as $sermon ) {
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
		$this->process();

		// whether or not there are more pages
		return (bool) $data->next;
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




