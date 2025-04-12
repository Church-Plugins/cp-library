<?php

namespace CP_Library\Controllers;

use ChurchPlugins\Controllers\Controller;
use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Models\ServiceType as ServiceTypeModel;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Util\Convenience;

class ServiceType extends Controller {

	/**
	 * @param $model ServiceTypeModel
	 */

	public function get_content() {
		return $this->filter( get_the_content( null, false, $this->post ), __FUNCTION__ );
	}

	public function get_title() {
		return $this->filter( apply_filters( 'the_title', $this->post->post_title, $this->post->ID ), __FUNCTION__ );
	}

	public function get_permalink() {
		return $this->filter( get_permalink( $this->post->ID ), __FUNCTION__ );
	}

	/**
	 * Get thumbnail with fallback to default
	 * 
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_thumbnail() {
		if ( $thumb = get_the_post_thumbnail_url( $this->post->ID, 'full' ) ) {
			return $this->filter( $thumb, __FUNCTION__ );
		}

		return $this->filter( $this->get_default_thumb(), __FUNCTION__ );
	}

	/**
	 * Get default thumbnail for service types
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_default_thumb() {
		return $this->filter( Settings::get( 'default_thumbnail', CP_LIBRARY_PLUGIN_URL . 'assets/images/cpl-logo.jpg' ), __FUNCTION__ );
	}

	/**
	 * Get the publish date timestamp
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_publish_date() {
		$date = get_post_datetime( $this->post );
		return $this->filter( $date->getTimestamp(), __FUNCTION__ );
	}

	/**
	 * Get all items associated with this service type
	 * 
	 * @param string $field The field to return from each item
	 * @return array List of items
	 * @since 1.0.0
	 */
	public function get_items($field = 'origin_id') {
		return $this->filter( $this->model->get_all_items($field), __FUNCTION__ );
	}

	/**
	 * Get the number of items associated with this service type
	 * 
	 * @return int Count of items
	 * @since 1.0.0
	 */
	public function get_items_count() {
		$items = $this->get_items();
		return $this->filter( count($items), __FUNCTION__ );
	}

	/**
	 * Check if this is the default service type
	 * 
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_default() {
		return $this->filter( Settings::get_service_type( 'default_service_type' ) == $this->model->id, __FUNCTION__ );
	}

	/**
	 * Get data formatted for API
	 * 
	 * @param bool $include_items Whether to include items in the response
	 * @return array API data
	 * @since 1.0.0
	 */
	public function get_api_data($include_items = true) {
		$data = [
			'id'        => $this->model->id,
			'originID'  => $this->post->ID,
			'permalink' => $this->get_permalink(),
			'thumb'     => $this->get_thumbnail(),
			'title'     => htmlspecialchars_decode( $this->get_title(), ENT_QUOTES | ENT_HTML401 ),
			'desc'      => $this->get_content(),
			'date'      => [ 
				'desc' => Convenience::relative_time( $this->get_publish_date() ), 
				'timestamp' => $this->get_publish_date() 
			],
			'items'     => [],
			'isDefault' => $this->is_default(),
		];

		if ( $include_items ) {
			$item_ids = $this->get_items('id');
			
			foreach ( $item_ids as $item_id ) {
				try {
					$item = new Item( $item_id, false );
					$item_data = $item->get_api_data();

					if ( 'publish' == $item_data['status'] ) {
						$data['items'][] = $item_data;
					}
				} catch ( Exception $e ) {
					error_log( $e );
				}
			}
		}

		return $this->filter( $data, __FUNCTION__ );
	}

	/**
	 * Get data formatted for player
	 * 
	 * @return array Player data
	 * @since 1.0.0
	 */
	public function get_player_data() {
		$data = [
			'id'        => $this->model->id,
			'originID'  => $this->post->ID,
			'permalink' => $this->get_permalink(),
			'thumb'     => $this->get_thumbnail(),
			'title'     => htmlspecialchars_decode( $this->get_title(), ENT_QUOTES | ENT_HTML401 ),
			'date'      => [ 
				'desc' => Convenience::relative_time( $this->get_publish_date() ), 
				'timestamp' => $this->get_publish_date() 
			],
		];

		return $this->filter( $data, __FUNCTION__ );
	}
}