<?php

namespace CP_Library\Controllers;

use ChurchPlugins\Controllers\Controller;
use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Models\ItemType as Model;
use CP_Library\Util\Convenience;

class ItemType extends Controller{

	public function get_content() {
		return $this->filter( get_the_content( null, false, $this->post ), __FUNCTION__ );
	}

	public function get_title() {
		return $this->filter( apply_filters( 'the_title', $this->post->post_title, $this->post->ID ), __FUNCTION__ );
	}

	public function get_permalink() {
		return $this->filter( get_permalink( $this->post->ID ), __FUNCTION__ );
	}

	public function get_thumbnail() {
		if ( $thumb = get_the_post_thumbnail_url( $this->post->ID, 'large' ) ) {
			return $this->filter( $thumb, __FUNCTION__ );
		}

		return $this->filter( $this->get_default_thumb(), __FUNCTION__ );
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

	public function get_publish_date() {
		$date = get_post_datetime( $this->post );
		return $this->filter( $date->getTimestamp(), __FUNCTION__ );
	}

	/**
	 * Get the first date in this series
	 *
	 * @param $format
	 *
	 * @return false|string
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_date_first( $format = false ) {
		if ( ! $date = get_post_meta( $this->post->ID, 'first_item_date', true ) ) {
			$this->model->update_dates();
			if ( ! $date = get_post_meta( $this->post->ID, 'first_item_date', true ) ) {
				return '';
			}
		}

		$_format = ! empty( $format ) ? $format : get_option( 'date_format' );
		return date( $_format, $date );
	}

	/**
	 * Get the last date in this series
	 *
	 * @param $format
	 *
	 * @return false|string
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_date_last( $format = false ) {
		if ( ! $date = get_post_meta( $this->post->ID, 'last_item_date', true ) ) {
			$this->model->update_dates();
			if ( ! $date = get_post_meta( $this->post->ID, 'last_item_date', true ) ) {
				return '';
			}
		}

		$_format = ! empty( $format ) ? $format : get_option( 'date_format' );
		return date( $_format, $date );
	}

	public function get_scripture() {
		$return = [];
		$terms  = get_the_terms( $this->post->ID, cp_library()->setup->taxonomies->scripture->taxonomy );

		if ( $terms ) {
			foreach ( $terms as $term ) {
				$return[ $term->slug ] = [
					'name' => $term->name,
					'slug' => $term->slug,
					'url'  => get_term_link( $term )
				];
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
			'date'      => [ 'desc' => Convenience::relative_time( $this->get_publish_date() ), 'timestamp' => $this->get_publish_date(), 'first' => $this->get_date_first(), 'last' => $this->get_date_last() ],
			'items'     => [],
			'season'    => $this->get_seasons(),
			'scripture' => $this->get_scripture(),
		];

		foreach( $this->model->get_items() as $i ) {
			try {
				$item = new Item( $i->id, false );
				$item_data = $item->get_api_data();

				if ( 'publish' == $item_data['status'] ) {
					$data['items'][] = $item->get_api_data();
				}
			} catch( Exception $e ) {
				error_log( $e );
			}
		}

		return $this->filter( $data, __FUNCTION__ );
	}
}
