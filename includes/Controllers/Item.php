<?php

namespace CP_Library\Controllers;

use CP_Library\Exception;
use CP_Library\Models\Item as ItemModel;

class Item {

	/**
	 * @var bool|ItemModel
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
	 *
	 * @throws Exception
	 */
	public function __construct( $id ) {
		$this->model = ItemModel::get_instance_from_origin( $id );
		$this->post  = get_post( $id );
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

	public function get_thumbnail() {
		return $this->filter( get_the_post_thumbnail_url( $this->post->ID ), __FUNCTION__ );
	}

	public function get_publish_date() {
		return $this->filter( get_post_datetime( $this->post ), __FUNCTION__ );
	}

	public function get_categories() {
		$return = [];
		$terms = get_the_terms( $this->post->ID, 'talk_categories' );

		if ( $terms ) {
			foreach( $terms as $term ) {
				$return[ $term->slug ] = $term->name;
			}
		}


		return $this->filter( $return, __FUNCTION__ );
	}

	public function get_video() {
		$return = [
			'type'  => 'url',
			'value' => false,
		];

		if ( $url = $this->model->get_meta_value( 'video_url' ) ) {
			$return['value'] = esc_url( $url );
		}

		if ( ! $url ) {
			if ( $id = $this->model->get_meta_value( 'video_id_vimeo' ) ) {
				$return['value'] = '<iframe src="https://player.vimeo.com/video/' . $id . '" width="500" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
			} else if ( $id = $this->model->get_meta_value( 'video_id_facebook' ) )  {
				$return['value'] =
					'<div id="fb-root"></div>
        			<script async defer src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.2"></script>

			        <div class="fb-video"
			             data-href="https://www.facebook.com' . $id . '"
			             data-width="500"
			             data-show-text="true"
			             data-lazy="true">
			        </div>';
			}
		}

		return $this->filter( $return, __FUNCTION__ );
	}

	public function get_audio() {
		return $this->filter( esc_url ( $this->model->get_meta_value( 'audio_url' ) ), __FUNCTION__ );
	}
}
