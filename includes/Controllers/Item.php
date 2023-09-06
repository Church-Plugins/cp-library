<?php

namespace CP_Library\Controllers;

use ChurchPlugins\Controllers\Controller;
use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ServiceType;
use CP_Library\Models\Speaker;
use CP_Library\Util\Convenience;

class Item extends Controller{

	/**
	 * @param $model ItemModel
	 */

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

	public function get_locations() {
		if ( ! function_exists( 'cp_locations' ) ) {
			return $this->filter( [], __FUNCTION__ );
		}

		$tax = cp_locations()->setup->taxonomies->location->taxonomy;
		$locations = wp_get_post_terms( $this->post->ID, $tax );

		if ( is_wp_error( $locations ) || empty( $locations ) ) {
			return $this->filter( [], __FUNCTION__ );
		}

		$item_locations = [];
		foreach ( $locations as $location ) {
			$location_id = \CP_Locations\Setup\Taxonomies\Location::get_id_from_term( $location->slug );

			if ( 'global' === $location_id ) {
				continue;
			}

			$location    = new \CP_Locations\Controllers\Location( $location_id );
			$item_locations[ $location_id ] = [
				'title' => $location->get_title(),
				'url'   => $location->get_permalink(),
			];
		}

		return $this->filter( $item_locations, __FUNCTION__ );
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

		if ( ! $thumb && ! empty( $this->get_types() ) ) {
			try {
				$type = new ItemType( $this->get_types()[0]['id'], false );
				$thumb = $type->get_thumbnail();
			} catch( Exception $e ) {
				error_log( $e );
			}
		}

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

		$data = @file_get_contents( "http://vimeo.com/api/v2/video/$id.json" );

		if ( ! $data ) {
			return false;
		}

		$data = json_decode( $data );

		return $data[0]->thumbnail_large;
	}

	public function get_publish_date() {
		if ( $date = get_post_datetime( $this->post, 'date', 'gmt' ) ) {
			$date = $date->format( 'U' );
		}

		return $this->filter( $date, __FUNCTION__ );
	}

	/**
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_the_topics() {
		$terms = $this->get_topics();
		$return = [];

		foreach( $terms as $term ) {
			$return[] = sprintf( '<a href="%s">%s</a>', $term['url'], $term['name'] );
		}

		return $this->filter( $return, __FUNCTION__ );
	}

	public function get_topics() {
		$return = [];
		$terms  = get_the_terms( $this->post->ID, cp_library()->setup->taxonomies->topic->taxonomy );

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

	public function get_scripture() {

		// scripture is top level, get parent scripture if applicable
		if ( $this->post->post_parent ) {
			$parent = new self( $this->post->post_parent );
			return $parent->get_scripture();
		}

		$return = [];

		$passages = cp_library()->setup->taxonomies->scripture->get_object_passages( $this->post->ID );
		$terms    = cp_library()->setup->taxonomies->scripture->get_object_scripture( $this->post->ID );

		if ( empty( $passages[0] ) ) {
			return false;
		}

		$book = cp_library()->setup->taxonomies->scripture->get_book( $passages[0] );
		if ( ! $term = get_term_by( 'name', $book, cp_library()->setup->taxonomies->scripture->taxonomy ) ) {
			return false;
		}


		$terms  = [ $term ];

		if ( $terms ) {
			foreach ( $terms as $term ) {
				$return[ $term->slug ] = [
					'name' => $passages[0],
					'slug' => $term->slug,
					'url'  => get_term_link( $term )
				];
			}
		}

		return $this->filter( $return, __FUNCTION__ );
	}

	public function get_categories() {
		$return = [];
		$terms = get_the_terms( $this->post->ID, 'talk_categories' );

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		if ( $terms ) {
			foreach( $terms as $term ) {
				$return[ $term->slug ] = $term->name;
			}
		}


		return $this->filter( $return, __FUNCTION__ );
	}

	public function get_video() {

		$timestamp = get_post_meta( $this->model->origin_id, 'message_timestamp', true );
		$timestamp = ItemModel::duration_to_seconds( $timestamp );
		$return = [
			'type'  	=> 'url',
			'value' 	=> false,
			'marker'	=> $timestamp
		];

		if ( $url = $this->model->get_meta_value( 'video_url' ) ) {
			$return['value'] = $url;
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
		return $this->filter( $this->model->get_meta_value( 'audio_url' ), __FUNCTION__ );
	}

	public function get_embed() {
		$embed = $this->model->get_meta_value( 'item_embed' );

		if ( ! $embed ) {
			return false;
		}

		return $this->filter( $embed, __FUNCTION__ );
	}

	/**
	 * Get the duration for this item. Derived from the audio file if it exists.
	 *
	 * @since  1.0.4
	 *
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/13/23
	 */
	public function get_duration() {
		$duration = false;

		if ( $id = $this->audio_url_id ) {

			$meta = wp_get_attachment_metadata( $id );

			// Have duration.
			if ( ! empty( $meta['length_formatted'] ) ) {
				$duration = $meta['length_formatted'];
			}

		}

		return $this->filter( $duration, __FUNCTION__ );;
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
						'id'        => $type->model->id,
						'origin_id' => $type->model->origin_id,
						'title'     => $type->get_title(),
						'permalink' => $type->get_permalink(),
					];
				}
			}
		} catch( \ChurchPlugins\Exception $e ) {
			error_log( $e );
		}

		return $this->filter( $types, __FUNCTION__ );

	}

	/**
	 * Get speakers for this Item
	 *
	 * @return mixed|void
	 * @throws \ChurchPlugins\Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_speakers() {
		// no speakers for variations
		if ( $this->has_variations() ) {
			return [];
		}

		$speaker_ids = $this->model->get_speakers();
		$speakers = [];

		foreach( $speaker_ids as $id ) {
			$speaker  = Speaker::get_instance( $id );
			$speakers[] = [
				'id'    => $speaker->id,
				'title' => $speaker->title,
				'origin_id' => $speaker->origin_id,
			];
		}

		return $this->filter( $speakers, __FUNCTION__ );
	}

	/**
	 * Get service type for this Item
	 *
	 * @return mixed|void
	 * @throws \ChurchPlugins\Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_service_types() {
		if ( ! cp_library()->setup->post_types->service_type_enabled() ) {
			return [];
		}

		$service_type_ids = $this->model->get_service_types();
		$service_types = [];

		foreach( $service_type_ids as $id ) {
			$service_type  = ServiceType::get_instance( $id );
			$service_types[] = [
				'id'    => $service_type->id,
				'title' => $service_type->title,
				'origin_id' => $service_type->origin_id,
			];
		}

		return $this->filter( $service_types, __FUNCTION__ );
	}

	/*************** Variation Functions ****************/

	/**
	 * Whether this item has variations
	 *
	 * @since  1.1.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/6/23
	 */
	public function has_variations() {
		$return = true;

		if ( ! cp_library()->setup->variations->is_enabled() ) {
			$return = false;
		}

		if ( $this->post->post_parent ) {
			$return = false;
		}

		if ( ! $this->_cpl_has_variations ) {
			$return = false;
		}

		return $this->filter( $return, __FUNCTION__ );
	}

	/**
	 * Get variants for this item
	 *
	 * @since  1.1.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/6/23
	 */
	public function get_variations() {
		$variations = [];

		if ( $this->has_variations() ) {
			$variations = $this->model->get_variations();
		}

		return $this->filter( $variations, __FUNCTION__ );
	}

	/**
	 * Get variant for the provided source
	 *
	 * @since  1.1.0
	 *
	 * @param $source
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/6/23
	 */
	public function get_variation( $source ) {
		$variations = [];

		if ( $this->has_variations() ) {
			$variations = $this->model->get_variations();
		}

		return $this->filter( $variations, __FUNCTION__ );
	}

	/**
	 * Whether this item is a variant
	 *
	 * @since  1.1.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/6/23
	 */
	public function is_variant() {
		return $this->filter( $this->post->post_parent, __FUNCTION__ );
	}

	/**
	 * Get the variation source for this variant
	 *
	 * @since  1.1.0
	 *
	 * @return false|array $source should provide an array with 'type', 'id' and 'label' defined
	 * @author Tanner Moushey, 5/6/23
	 */
	public function get_variation_source() {
		if ( ! $this->is_variant() ) {
			return false;
		}

		$source = apply_filters( 'cpl_get_item_source', false, $this );

		// make sure that we have the expected data
		if ( ! isset( $source['id'], $source['label'], $source['type'] ) ) {
			$source = false;
		}

		return $this->filter( $source, __FUNCTION__ );
	}

	/**
	 * Return the ID for this item's variation source
	 *
	 * @since  1.1.0
	 *
	 * @return false|mixed|void
	 * @author Tanner Moushey, 5/6/23
	 */
	public function get_variation_source_id() {
		if ( ! $source = $this->get_variation_source() ) {
			return false;
		}

		if ( ! isset( $source['id'] ) ) {
			return false;
		}

		return $this->filter( $source['id'], __FUNCTION__ );
	}

	/**
	 * Return the Label for this item's variation source
	 *
	 * @since  1.1.0
	 *
	 * @return false|mixed|void
	 * @author Tanner Moushey, 5/6/23
	 */
	public function get_variation_source_label() {
		if ( ! $source = $this->get_variation_source() ) {
			return '';
		}

		if ( ! isset( $source['label'] ) ) {
			return '';
		}

		return $this->filter( $source['label'], __FUNCTION__ );
	}

	/**
	 * Return the type for this item's variation source
	 *
	 * @since  1.1.0
	 *
	 * @return false|mixed|void
	 * @author Tanner Moushey, 5/6/23
	 */
	public function get_variation_source_type() {
		if ( ! $source = $this->get_variation_source() ) {
			return '';
		}

		if ( ! isset( $source['type'] ) ) {
			return '';
		}

		return $this->filter( $source['type'], __FUNCTION__ );
	}

	/**
	 * Return the variations for this item, if they exist
	 *
	 * @since 1.1.0
	 *
	 * @return array|false
	 * @author Jonathan Roley
	 */
	public function get_variation_data() {

		if( ! $this->has_variations() ) {
			return false;
		}

		$variations = $this->get_variations();

		$variations = array_map( function( $id ) {
			$item = new Item( $id );

			if( ! $item->get_variation_source_id() ) {
				return false;
			}

			if( ! $item->is_variant() ) {
				return false;
			}

			return array(
				'title'     => sprintf( '%s: %s', $this->get_title(), $item->get_variation_source_label() ),
				'variation' => $item->get_variation_source_label(),
				'id'        => $item->get_variation_source_id(),
				'audio'     => $item->get_audio(),
				'video'     => $item->get_video(),
				'embed'     => $item->get_embed(),
				'speakers'  => $item->get_speakers(),
				'permalink' => $this->get_permalink()
			);
		}, $variations );

		$variations = array_filter( $variations, 'is_array' );

		return $variations;
	}

	/*************** Podcast Functions ****************/

	/**
	 * Get the content formatted for podcast
	 *
	 * @since  1.0.4
	 *
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_podcast_content() {
		$content = $this->get_content();

		// Allow some HTML per iTunes spec:
		// "You can use rich text formatting and some HTML (<p>, <ol>, <ul>, <a>) in the <content:encoded> tag."
		$content = strip_tags( $content, '<b><strong><i><em><p><ol><ul><a>' );

		$content = strip_shortcodes( $content );

		$content = trim( $content );

		return $this->filter( $content, __FUNCTION__ );
	}

	/**
	 * Get the summary formatted for podcast
	 *
	 * @since  1.0.4
	 *
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_podcast_summary() {
		$content = $this->get_podcast_content();

		// iTunes limits to 4000 characers
		return $this->filter( Helpers::str_truncate( $content, 4000 ), __FUNCTION__ );
	}

	/**
	 * Generate the excerpt for the podcast
	 *
	 * @since  1.0.4
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_podcast_excerpt( $max_chars = false ) {
		// Get excerpt output.
		$excerpt = apply_filters( 'the_excerpt_rss', get_the_excerpt() );

		// Strip tags and shortcodes.
		$excerpt = strip_tags( $excerpt );
		$excerpt = strip_shortcodes( $excerpt );

		// Remove other undesirable contents to clean up subtitle from automatic excerpt.
		// This is based on code from Seriously Simple Podcasting (GPL license).
		$excerpt = str_replace(
			array( '>', '<', '\'', '"', '`', '[andhellip;]', '[&hellip;]', '[&#8230;]' ),
			array( '', '', '', '', '', '', '', '' ),
			$excerpt
		);

		if ( $max_chars ) {
			$excerpt = Helpers::str_truncate( $this->get_podcast_excerpt(), $max_chars );
		}

		return $this->filter( trim( $excerpt ), __FUNCTION__ );
	}

	/**
	 * Generate the podcast description for this item
	 *
	 * @since  1.0.4
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_podcast_description() {
		// max characters for iTunes
		return $this->filter( $this->get_podcast_excerpt( 4000 ), __FUNCTION__ );
	}

	/**
	 * Generate the Podcast subtitle for this item
	 *
	 * @since  1.0.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_podcast_subtitle() {
		// max characters for iTunes
		return $this->filter( $this->get_podcast_excerpt( 225 ), __FUNCTION__ );
	}

	/**
	 * Get the speakers list for podcast
	 *
	 * @since  1.0.4
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_podcast_speakers() {

		try {
			$speakers = wp_list_pluck( $this->get_speakers(), 'title' );
		} catch( \ChurchPlugins\Exception $e ) {
			error_log( $e );
			$speakers = [];
		}

		$speakers = implode( ', ', $speakers );
		$speakers = htmlspecialchars( trim( $speakers ) );

		// iTunes limits to 4000 characers
		return $this->filter( $speakers, __FUNCTION__ );
	}


	/*************** API Functions ****************/

	/**
	 * Build and return API data
	 *
	 * @since  1.0.0
	 *
	 *
	 * @return mixed|void
	 * @author Tanner Moushey
	 */
	public function get_api_data( $include_variations = false ) {
		$date = [];

		try {
			$data = [
				'id'         => $this->model->id,
				'originID'   => $this->post->ID,
				'permalink'  => $this->get_permalink(),
				'status'     => get_post_status( $this->post ),
				'slug'       => $this->post->post_name,
				'thumb'      => $this->get_thumbnail(),
				'title'      => htmlspecialchars_decode( $this->get_title(), ENT_QUOTES | ENT_HTML401 ),
				'desc'       => $this->get_content(),
				'date'       => [
					'desc'      => Convenience::relative_time( $this->get_publish_date() ),
					'timestamp' => $this->get_publish_date()
				],
				'category'   => $this->get_categories(),
				'speakers'   => $this->get_speakers(),
				'locations'  => $this->get_locations(),
				'video'      => $this->get_video(),
				'audio'      => $this->get_audio(),
				'embed'      => $this->get_embed(),
				'types'      => $this->get_types(),
				'service_types' => $this->get_service_types(),
				'topics'     => $this->get_topics(),
				'scripture'  => $this->get_scripture(),
				'variations' => null,
			];

			if ( $include_variations ) {
				$data['variations'] = $this->get_variation_data();
			}
		} catch ( \ChurchPlugins\Exception $e ) {
			error_log( $e );
		}

		return $this->filter( $data, __FUNCTION__ );
	}

	/**
	 * Return analytics for this Item
	 *
	 * @param $action
	 *
	 * @return array|object|\stdClass[]|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_analytics( $action = '' ) {
		$args = [ 'object_type' => 'item', 'object_id' => $this->model->id ];

		if ( $action ) {
			$args[ 'action' ] = $action;
		}

		return \ChurchPlugins\Models\Log::query( $args );
	}

	public function get_analytics_count( $action = '' ) {
		$args = [ 'object_type' => 'item', 'object_id' => $this->model->id ];

		if ( $action ) {
			$args[ 'action' ] = $action;
		}

		return \ChurchPlugins\Models\Log::count_by_action( $args );
	}

	/*************** Controller Processing Functions ****************/

	/**
	 * Handle enclosure functionality for this item
	 *
	 * @since  1.0.4
	 *
	 *
	 * @author Tanner Moushey, 4/13/23
	 */
	public function do_enclosure() {
		$audio = $this->get_audio();

		// Make Dropbox URLs use ?raw=1.
		// Note that this will not work on iTunes.
		if ( preg_match( '/dropbox/', $audio ) ) {
			$audio = remove_query_arg( 'dl', $audio );
			$audio = add_query_arg( 'raw', '1', $audio );
		}

		do_enclose( $audio, $this->post->ID );
	}

}
