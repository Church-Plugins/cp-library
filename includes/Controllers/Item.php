<?php

namespace CP_Library\Controllers;

use CP_Library\Admin\Settings;
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
	 * @param bool $use_origin whether or not to use the origin id
	 *
	 * @throws Exception
	 */
	public function __construct( $id, $use_origin = true ) {
		$this->model = $use_origin ? ItemModel::get_instance_from_origin( $id ) : ItemModel::get_instance( $id );
		$this->post  = get_post( $this->model->origin_id );
	}

	protected function filter( $value, $function ) {
		return apply_filters( 'cpl_item_' . $function, $value, $this );
	}

	public function get_content( $raw = false ) {
		$content = get_the_content( null, false, $this->post );
		if ( ! $raw ) {
			$content = apply_filters( 'the_content', $content );
		}

		return $this->filter( $content, __FUNCTION__ );
	}

	public function get_title() {
		return $this->filter( get_the_title( $this->post->ID ), __FUNCTION__ );
	}

	public function get_permalink() {
		return $this->filter( get_permalink( $this->post->ID ), __FUNCTION__ );
	}

	/**
	 * Get default thumbnail for items
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_default_thumb() {
		return $this->filter( Settings::get( 'default_thumbnail', CP_LIBRARY_PLUGIN_URL . 'assets/images/cpl-logo.jpg' ), __FUNCTION__ );
	}

	/**
	 * Get thumbnail
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_thumbnail() {
		if ( $thumb = get_the_post_thumbnail_url( $this->post->ID ) ) {
			return $this->filter( $thumb, __FUNCTION__ );
		}

		$thumb = $this->maybeGetVimeoThumb();

		if ( ! $thumb ) {
			$thumb = $this->get_default_thumb();
		}

		return $this->filter( $thumb, __FUNCTION__ );
	}

	/**
	 * Get thumbnail from Vimeo
	 *
	 * @return false
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
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

	public function get_topics() {
		$return = [];
		$terms  = get_the_terms( $this->post->ID, cp_library()->setup->taxonomies->topic->taxonomy );

		if ( $terms ) {
			foreach ( $terms as $term ) {
				$return[ $term->slug ] = $term->name;
			}
		}

		return $this->filter( $return, __FUNCTION__ );
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
				$return['type']  = 'vimeo';
				$return['id']    = $id;
				$return['value'] = 'https://vimeo.com/' . $id;
			} else if ( $id = $this->model->get_meta_value( 'video_id_facebook' ) )  {
				$return['type']  = 'facebook';
				$return['id']    = $id;
				$return['value'] = 'https://www.facebook.com' . $id;
			}
		}

		return $this->filter( $return, __FUNCTION__ );
	}

	public function get_audio() {
		return $this->filter( esc_url ( $this->model->get_meta_value( 'audio_url' ) ), __FUNCTION__ );
	}

	/**
	 * Get the item_types associated with this item
	 *
	 * @return array|mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_types() {
		$types = [];

		if ( ! cp_library()->setup->post_types->item_type_enabled() ) {
			return $types;
		}

		try {
			$item_types = $this->model->get_types();

			if ( ! empty( $item_types ) ) {
				foreach ( $item_types as $type_id ) {
					$type = new ItemType( $type_id, false );
					$types[] = [
						'title'     => $type->get_title(),
						'permalink' => $type->get_permalink(),
					];
				}
			}
		} catch( Exception $e ) {
			error_log( $e );
		}

		return $this->filter( $types, __FUNCTION__ );

	}

	public function get_api_data() {
		$data = [
			'id'        => $this->model->id,
			'originID'  => $this->post->ID,
			'permalink' => $this->get_permalink(),
			'slug'      => $this->post->post_name,
			'thumb'     => $this->get_thumbnail(),
			'title'     => htmlspecialchars_decode( $this->get_title(), ENT_QUOTES | ENT_HTML401 ),
			'desc'      => $this->get_content(),
			'date'      => $this->get_publish_date(),
			'category'  => $this->get_categories(),
			'video'     => $this->get_video(),
			'audio'     => $this->get_audio(),
			'types'     => $this->get_types(),
			'topics'    => $this->get_topics(),
		];

		return $this->filter( $data, __FUNCTION__ );
	}
}
