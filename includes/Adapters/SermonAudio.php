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

			// adds references to attachments. These must be named the same values used in add_attachments()
			if( $sermon->speaker ) {
				$item['attachments']['cpl_speaker'] = [ $sermon->speaker->speakerID ];
			}

			if( $sermon->series ) {
				$item['attachments']['cpl_item_type'] = [ $sermon->series->seriesID ];
			}

			$items[$sermon->sermonID] = $item;

			if( (boolean) $sermon->speaker && cp_library()->setup->post_types->speaker_enabled() ) {
				$speaker = $this->format_speaker( $sermon->speaker );
				$speakers[$sermon->speaker->speakerID] = $speaker;
			}

			if( (boolean) $sermon->series && cp_library()->setup->post_types->item_type_enabled() ) {
				$item_type = $this->format_item_type( $sermon->series );
				$item_types[$sermon->series->seriesID] = $item_type;
			}
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
			'meta_input' => array()
		);

		if( $item->hasAudio ) {
			$args['meta_input']['audio_url'] = $item->media->audio[0]->downloadURL;
		}

		if( $item->hasVideo ) {
			$args['meta_input']['video_url'] = $item->media->video[0]->streamURL;
		}

		return $args;
	}

	/**
	 * Formats a Series
	 */
	public function format_item_type( $item_type ) {
		return array(
			'external_id'  => $item_type->seriesID,
			'post_title'   => $item_type->title,
			'post_status'  => 'publish',
			'post_type'    => cp_library()->setup->post_types->item_type->post_type,
			'post_content' => wp_kses_post( $item_type->description ),
		);
	}

	/**
	 * Formats a Speaker
	 */
	public function format_speaker( $speaker ) {
		return array(
			'external_id'  => $speaker->speakerID,
			'post_title'   => $speaker->displayName,
			'post_status'  => 'publish',
			'post_type'    => cp_library()->setup->post_types->speaker->post_type,
			'post_content' => wp_kses_post( $speaker->bio ),
		);
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
} 




