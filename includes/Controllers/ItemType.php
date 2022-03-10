<?php

namespace CP_Library\Controllers;

use CP_Library\Exception;
use CP_Library\Models\ItemType as Model;

class ItemType {

	/**
	 * @var bool|Model
	 */
	public $model;

	/**
	 * @var array|\WP_Post|null
	 */
	public $post;

	/**
	 * Item constructor.
	 *
	 * @param $id
	 * @param bool $use_origin whether or not to use the origin id
	 *
	 * @throws Exception
	 */
	public function __construct( $id, $use_origin = true ) {
		$this->model = $use_origin ? Model::get_instance_from_origin( $id ) : Model::get_instance( $id );
		$this->post  = get_post( $this->model->origin_id );
	}

	protected function filter( $value, $function ) {
		return apply_filters( 'cpl_item_' . $function, $value, $this );
	}

	public function get_content() {
		return $this->filter( get_the_content( null, false, $this->post ), __FUNCTION__ );
	}

	public function get_title() {
		return $this->filter( get_the_title( $this->post->ID ), __FUNCTION__ );
	}

	public function get_permalink() {
		return $this->filter( get_permalink( $this->post->ID ), __FUNCTION__ );
	}

	public function get_thumbnail() {
		if ( $thumb = get_the_post_thumbnail_url( $this->post->ID ) ) {
			return $this->filter( $thumb, __FUNCTION__ );
		}

		$thumb = $this->maybeGetVimeoThumb();

		return $this->filter( $thumb, __FUNCTION__ );
	}

	protected function maybeGetVimeoThumb() {
		if ( ! $id = $this->model->get_meta_value( 'video_id_vimeo' ) ) {
			return false;
		}

		$data = file_get_contents( "http://vimeo.com/api/v2/video/$id.json" );
		$data = json_decode( $data );

		return $data[0]->thumbnail_large;
	}

	public function get_publish_date() {
		return $this->filter( get_post_datetime( $this->post ), __FUNCTION__ );
	}

	public function get_scripture() {
		$return = [];
		$terms  = get_the_terms( $this->post->ID, cp_library()->setup->taxonomies->scripture->taxonomy );

		if ( $terms ) {
			foreach ( $terms as $term ) {
				$return[ $term->slug ] = $term->name;
			}
		}

		return $this->filter( $return, __FUNCTION__ );
	}

	public function get_seasons() {
		$return = [];
		$terms  = get_the_terms( $this->post->ID, cp_library()->setup->taxonomies->season->taxonomy );

		if ( $terms ) {
			foreach ( $terms as $term ) {
				$return[ $term->slug ] = $term->name;
			}
		}

		return $this->filter( $return, __FUNCTION__ );
	}

	public function get_api_data() {
		$data = [
			'id'        => $this->model->id,
			'originID'  => $this->post->ID,
			'permalink' => $this->get_permalink(),
			'thumb'     => $this->get_thumbnail(),
			'title'     => htmlspecialchars_decode( $this->get_title(), ENT_QUOTES | ENT_HTML401 ),
			'desc'      => $this->get_content(),
			'date'      => $this->get_publish_date(),
			'items'     => [],
			'season'    => $this->get_seasons(),
			'scripture' => $this->get_scripture(),
		];

		foreach( $this->model->get_items() as $i ) {
			try {
				$item = new Item( $i->origin_id );
				$data['items'][] = $item->get_api_data();
			} catch( Exception $e ) {
				error_log( $e );
			}
		}

		return $this->filter( $data, __FUNCTION__ );
	}
}
