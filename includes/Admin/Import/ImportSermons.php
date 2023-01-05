<?php
/**
 * Sermons import class
 *
 * This class handles importing downloads with the batch processing API
 *
 * @package     CP_Library
 * @subpackage  Admin/Import
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.4
 */

namespace CP_Library\Admin\Import;

use ChurchPlugins\Admin\Import\BatchImport;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * ImportSermons Class
 *
 * @since 1.0.4
 */
class ImportSermons extends BatchImport {

	/**
	 * Set up our import config.
	 *
	 * @since 1.0.4
	 * @return void
	 */
	public function init() {

		// Set up default field map values
		$this->field_mapping = array(
			'post_title'     => '',
			'post_name'      => '',
			'post_status'    => 'draft',
			'post_author'    => '',
			'post_date'      => '',
			'post_content'   => '',
			'post_excerpt'   => '',
			'price'          => '',
			'files'          => '',
			'categories'     => '',
			'tags'           => '',
			'sku'            => '',
			'earnings'       => '',
			'sales'          => '',
			'featured_image' => '',
			'download_limit' => '',
			'notes'          => ''
		);
	}

	/**
	 * Process a step
	 *
	 * @since 1.0.4
	 * @return bool
	 */
	public function process_step() {

		$more = false;

		if ( 1 && ! $this->can_import() ) {
			wp_die( __( 'You do not have permission to import data.', 'cp-library' ), __( 'Error', 'cp-library' ), array( 'response' => 403 ) );
		}

		$i      = 1;
		$offset = $this->step > 1 ? ( $this->per_step * ( $this->step - 1 ) ) : 0;

		if( $offset > $this->total ) {
			$this->done = true;

			// Delete the uploaded CSV file.
			unlink( $this->file );
		}

		if( ! $this->done && $this->csv ) {

			$more = true;

			foreach( $this->csv as $index => $row ) {

				// Skip all rows until we pass our offset
				if( $index + 1 <= $offset ) {
					continue;
				}

				// Done with this batch
				if( $i > $this->per_step ) {
					break;
				}

				// Import Download
				$args = array(
					'post_type'    => 'download',
					'post_title'   => '',
					'post_name'    => '',
					'post_status'  => '',
					'post_author'  => '',
					'post_date'    => '',
					'post_content' => '',
					'post_excerpt' => ''
				);

				foreach ( $args as $key => $field ) {
					if ( ! empty( $this->field_mapping[ $key ] ) && ! empty( $row[ $this->field_mapping[ $key ] ] ) ) {
						$args[ $key ] = $row[ $this->field_mapping[ $key ] ];
					}
				}

				if ( empty( $args['post_author'] ) ) {
	 				$user = wp_get_current_user();
	 				$args['post_author'] = $user->ID;
	 			} else {

	 				// Check all forms of possible user inputs, email, ID, login.
	 				if ( is_email( $args['post_author'] ) ) {
	 					$user = get_user_by( 'email', $args['post_author'] );
	 				} elseif ( is_numeric( $args['post_author'] ) ) {
	 					$user = get_user_by( 'ID', $args['post_author'] );
	 				} else {
	 					$user = get_user_by( 'login', $args['post_author'] );
	 				}

	 				// If we don't find one, resort to the logged in user.
	 				if ( false === $user ) {
	 					$user = wp_get_current_user();
	 				}

	 				$args['post_author'] = $user->ID;
	 			}

				// Format the date properly
				if ( ! empty( $args['post_date'] ) ) {

					$timestamp = strtotime( $args['post_date'], current_time( 'timestamp' ) );
					$date      = date( 'Y-m-d H:i:s', $timestamp );

					// If the date provided results in a date string, use it, or just default to today so it imports
					if ( ! empty( $date ) ) {
						$args['post_date'] = $date;
					} else {
						$date = '';
					}

				}


				// Detect any status that could map to `publish`
				if ( ! empty( $args['post_status'] ) ) {

					$published_statuses = array(
						'live',
						'published',
					);

					$current_status = strtolower( $args['post_status'] );

					if ( in_array( $current_status, $published_statuses ) ) {
						$args['post_status'] = 'publish';
					}

				}

				$download_id = wp_insert_post( $args );


				// Custom fields


				$i++;
			}

		}

		return $more;
	}

	/**
	 * Import messages from csv
	 *
	 * ## OPTIONS
	 *
	 * [--skip-thumbs]
	 * : use this parameter to skip sideloading the thumbnail
	 *
	 * [--skip-tags]
	 * : use this parameter to skip importing tags
	 *
	 * [--update]
	 * : use this parameter to update existing items
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function import_messages( $args, $assoc_args ) {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$args = wp_parse_args( [
			0 => 'messages.csv',
		], $args );

		$filename = ABSPATH . $args[0];

		if ( ! $file = fopen( $filename, 'r' ) ) {
			WP_CLI::error('Could not locate the file');
		}

		$all_speakers = [];
		$all_locations = [ 'global' => 'global' ];

		foreach( Speaker::get_all_speakers() as $speaker ) {
			$all_speakers[ $speaker->id ] = strtolower( $speaker->title );
		}

		if ( class_exists( 'CP_Locations\Models\Location' ) ) {
			$has_locations = true;
			foreach ( Location::get_all_locations() as $location ) {
				// set the term id as the key
				$all_locations[ 'location_' . $location->origin_id ] = strtolower( $location->title );
			}
		} else {
			$has_locations = false;
		}

		$results = ItemType::search( 'title', 'No Series' );
		$import_id = time();

		WP_CLI::log( 'Batch ID: ' . $import_id );

		if ( empty( $results ) ) {
			$series_id = wp_insert_post( [
				'post_type'   => ItemType::get_prop( 'post_type' ),
				'post_title'  => 'No Series',
				'post_status' => 'publish',
			], true );

			if ( is_wp_error( $series_id ) ) {
				WP_CLI::error( $series_id->get_error_message() );
			}

			try {
				$series         = ItemType::get_instance_from_origin( $series_id );
				$default_series = $series->id;
			} catch( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		} else {
			$default_series = $results[0]->id;
		}

		$row = 0;

		while ( $data = fgetcsv( $file ) ) {
			try {
				count( $data );

				if ( ++ $row == 1 ) {
					$headers = array_flip( $data ); // Get the column names from the header.
					continue;
				}

				$data = array_map( 'trim', $data );

				$title    = $data[ $headers['Title'] ];
				$desc     = trim( $data[ $headers['Description'] ] );
				$series   = explode( ':', $data[ $headers['Series'] ] )[0];
				$date     = strtotime( $data[ $headers['Date'] ] );
				$location = trim( strtolower( $data[ $headers['Location'] ] ) );
				$speakers = array_map( 'trim', explode( ',', $data[ $headers['Speaker'] ] ) );
				$terms    = $data[ $headers['Tags'] ];
				$thumb    = $data[ $headers['Thumbnail'] ];
				$video    = trim( $data[ $headers['Video'] ] );
				$audio    = trim( $data[ $headers['Audio'] ] );

				if ( $has_locations && false === $location_id = array_search( $location, $all_locations ) ) {
					WP_CLI::error( 'Could not find location.' );
				}

				if ( empty( $title ) ) {
					$title = $series;
				}

				$cp_hash = md5( $title . $date );
				$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from $wpdb->postmeta WHERE `meta_key` = '_cp_import_id' AND `meta_value` = %s", $cp_hash ) );

				if ( $post_id && empty( $assoc_args['update'] ) ) {
					WP_CLI::warning( $title . ' already exists. Skipping this item.' );
					continue;
				}

				WP_CLI::log( 'Importing ' . $title );

				$args = [
					'post_type'    => Item::get_prop( 'post_type' ),
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_date'    => date( 'Y-m-d 9:00:00', $date ),
					'post_content' => wp_kses_post( $desc ),
				];

				if ( $post_id ) {
					$args['ID'] = $post_id;
				}

				$message_id = wp_insert_post( $args, true );

				update_post_meta( $message_id, '_cp_import_id', $cp_hash );
				update_post_meta( $message_id, '_cp_import_batch_id', $import_id );
				update_post_meta( $message_id, '_cp_import_data', $data );
				update_post_meta( $message_id, '_cp_import_headers', $headers );

				if ( is_wp_error( $message_id ) ) {
					WP_CLI::warning( $message_id->get_error_message() );
					continue;
				}

				// add taxonomy
				if ( $has_locations ) {
					wp_set_post_terms( $message_id, $location_id, 'cp_location' );
				}

				// save to our tables
				$item = Item::get_instance_from_origin( $message_id );

				WP_CLI::log( '--- message created' );

				if ( $thumb && empty( $assoc_args['skip-thumbs'] ) && $thumb !== get_post_meta( $message_id, '_cp_import_thumb', true ) ) {
					// Handle thumbnail
					$thumb_id = media_sideload_image( $thumb, $message_id, $title . ' Thumbnail', 'id' );

					if ( is_wp_error( $thumb_id ) ) {
						WP_CLI::warning( $thumb_id->get_error_message() );
					} else {
						WP_CLI::log( '--- imported thumbnail' );
					}

					set_post_thumbnail( $message_id, $thumb_id );
					update_post_meta( $message_id, '_cp_import_thumb', $thumb );
				}

				// Speakers
				$speaker_ids = [];
				foreach( $speakers as $speaker ) {
					if ( false === $speaker_id = array_search( strtolower( $speaker ), $all_speakers ) ) {
						$speaker_id = wp_insert_post( [
							'post_type'   => Speaker::get_prop( 'post_type' ),
							'post_title'  => $speaker,
							'post_status' => 'publish',
						], true );

						if ( is_wp_error( $speaker_id ) ) {
							WP_CLI::error( $speaker_id->get_error_message() );
							continue;
						}

						// get the id and save it to our array
						$speaker_id                  = Speaker::get_instance_from_origin( $speaker_id )->id;
						$all_speakers[ $speaker_id ] = strtolower( $speaker );

						WP_CLI::log( '--- speaker created, ' . $speaker );
					}

					if ( $has_locations && $speaker_id ) {
						// add the current location to the speaker's location
						wp_set_post_terms( Speaker::get_instance( $speaker_id )->origin_id, $location_id, 'cp_location', true );
					}

					$speaker_ids[] = $speaker_id;
				}

				$item->update_speakers( $speaker_ids );

				// Series / ItemType
				$series_id = $default_series;

				if ( ! empty( $series ) ) {
					$results = ItemType::search( 'title', $series );

					if ( empty( $results ) ) {
						$results = ItemType::search( 'title', $series, true );
					}

					if ( count( $results ) ) {
						// default to first result
						$series_id = $results[0]->id;

						// see if there is a direct match
						foreach( $results as $result ) {
							if ( $result->title == $series ) {
								$series_id = $result->id;
							}
						}
					} elseif ( ! empty( $series ) ) { // create the series if it doesn't exist
						$series_id = wp_insert_post( [
							'post_type'   => ItemType::get_prop( 'post_type' ),
							'post_title'  => $series,
							'post_status' => 'publish',
						], true );

						if ( is_wp_error( $series_id ) ) {
							WP_CLI::error( $series_id->get_error_message() );
						}

						// get the id
						$series_id = ItemType::get_instance_from_origin( $series_id )->id;

						WP_CLI::log( '--- Series created, ' . $series );
					}
				}

				$item->update_types( [ $series_id ] );

				if ( $video ) {
					update_post_meta( $message_id, 'video_url', $video );
					$item->update_meta_value( 'video_url', $video );
					WP_CLI::log( '--- added video' );
				}

				if ( strstr( $audio, 'w.soundcloud.com/player' ) ) {
					$audio = $this->get_soundcloud_url( $audio );
				}

				if ( $audio ) {
					update_post_meta( $message_id, 'audio_url', $audio );
					$item->update_meta_value( 'audio_url', $audio );
				}

				if ( ! empty( $terms ) && empty( $assoc_args['skip-tags'] ) ) {
					self::update_terms( $message_id, $terms );
				}

			} catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		}

	}


	/**
	 * Return the calculated completion percentage
	 *
	 * @since 1.0.4
	 * @return int
	 */
	public function get_percentage_complete() {

		if( $this->total > 0 ) {
			$percentage = ( $this->step * $this->per_step / $this->total ) * 100;
		}

		if( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	/**
	 * Set up and store the price for the download
	 *
	 * @since 1.0.4
	 * @return void
	 */
	private function set_price( $download_id = 0, $price = '' ) {

		if( is_numeric( $price ) ) {

			update_post_meta( $download_id, 'edd_price', edd_sanitize_amount( $price ) );

		} else {

			$prices = $this->str_to_array( $price );

			if( ! empty( $prices ) ) {

				$variable_prices = array();
				$price_id        = 1;
				foreach( $prices as $price ) {

					// See if this matches the EDD Download export for variable prices
					if( false !== strpos( $price, ':' ) ) {

						$price = array_map( 'trim', explode( ':', $price ) );

						$variable_prices[ $price_id ] = array( 'name' => $price[ 0 ], 'amount' => $price[ 1 ] );
						$price_id++;

					}

				}

				update_post_meta( $download_id, '_variable_pricing', 1 );
				update_post_meta( $download_id, 'edd_variable_prices', $variable_prices );

			}

		}

	}

	/**
	 * Set up and store the file downloads
	 *
	 * @since 1.0.4
	 * @return void
	 */
	private function set_files( $download_id = 0, $files = array() ) {

		if( ! empty( $files ) ) {

			$download_files = array();
			$file_id        = 1;
			foreach( $files as $file ) {

				$condition = '';

				if ( false !== strpos( $file, ';' ) ) {

					$split_on  = strpos( $file, ';' );
					$file_url  = substr( $file, 0, $split_on );
					$condition = substr( $file, $split_on + 1 );

				} else {

					$file_url = $file;

				}

				$download_file_args = array(
					'index'     => $file_id,
					'file'      => $file_url,
					'name'      => basename( $file_url ),
					'condition' => empty( $condition ) ? 'all' : $condition
				);

				$download_files[ $file_id ] = $download_file_args;
				$file_id++;

			}

			update_post_meta( $download_id, 'edd_download_files', $download_files );

		}

	}

	/**
	 * Set up and store the Featured Image
	 *
	 * @since 1.0.4
	 * @return void
	 */
	private function set_image( $download_id = 0, $image = '', $post_author = 0 ) {

		$is_url   = false !== filter_var( $image, FILTER_VALIDATE_URL );
		$is_local = $is_url && false !== strpos( site_url(), $image );
		$ext      = edd_get_file_extension( $image );

		if( $is_url && $is_local ) {

			// Image given by URL, see if we have an attachment already
			$attachment_id = attachment_url_to_postid( $image );

		} elseif( $is_url ) {

			if( ! function_exists( 'media_sideload_image' ) ) {

				require_once( ABSPATH . 'wp-admin/includes/file.php' );

			}

			// Image given by external URL
			$url = media_sideload_image( $image, $download_id, '', 'src' );

			if( ! is_wp_error( $url ) ) {

				$attachment_id = attachment_url_to_postid( $url );

			}


		} elseif( false === strpos( $image, '/' ) && edd_get_file_extension( $image ) ) {

			// Image given by name only

			$upload_dir = wp_upload_dir();

			if( file_exists( trailingslashit( $upload_dir['path'] ) . $image ) ) {

				// Look in current upload directory first
				$file = trailingslashit( $upload_dir['path'] ) . $image;

			} else {

				// Now look through year/month sub folders of upload directory for files with our image's same extension
				$files = glob( $upload_dir['basedir'] . '/*/*/*' . $ext );
				foreach( $files as $file ) {

					if( basename( $file ) == $image ) {

						// Found our file
						break;

					}

					// Make sure $file is unset so our empty check below does not return a false positive
					unset( $file );

				}

			}

			if( ! empty( $file ) ) {

				// We found the file, let's see if it already exists in the media library

				$guid          = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file );
				$attachment_id = attachment_url_to_postid( $guid );


				if( empty( $attachment_id ) ) {

					// Doesn't exist in the media library, let's add it

					$filetype = wp_check_filetype( basename( $file ), null );

					// Prepare an array of post data for the attachment.
					$attachment = array(
						'guid'           => $guid,
						'post_mime_type' => $filetype['type'],
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $image ),
						'post_content'   => '',
						'post_status'    => 'inherit',
						'post_author'    => $post_author
					);

					// Insert the attachment.
					$attachment_id = wp_insert_attachment( $attachment, $file, $download_id );

					// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					// Generate the metadata for the attachment, and update the database record.
					$attach_data = wp_generate_attachment_metadata( $attachment_id, $file );
					wp_update_attachment_metadata( $attachment_id, $attach_data );

				}

			}

		}

		if( ! empty( $attachment_id ) ) {

			return set_post_thumbnail( $download_id, $attachment_id );

		}

		return false;

	}

	/**
	 * Set up and taxonomy terms
	 *
	 * @since 1.0.4
	 * @return void
	 */
	private function set_taxonomy_terms( $download_id = 0, $terms = array(), $taxonomy = 'download_category' ) {

		$terms = $this->maybe_create_terms( $terms, $taxonomy );

		if( ! empty( $terms ) ) {

			wp_set_object_terms( $download_id, $terms, $taxonomy );

		}

	}

	/**
	 * Locate term IDs or create terms if none are found
	 *
	 * @since 1.0.4
	 * @return array
	 */
	private function maybe_create_terms( $terms = array(), $taxonomy = 'download_category' ) {

		// Return of term IDs
		$term_ids = array();

		foreach( $terms as $term ) {

			if( is_numeric( $term ) && 0 === (int) $term ) {

				$t = get_term( $term, $taxonomy );

			} else {

				$t = get_term_by( 'name', $term, $taxonomy );

				if( ! $t ) {

					$t = get_term_by( 'slug', $term, $taxonomy );

				}

			}

			if( ! empty( $t ) ) {

				$term_ids[] = $t->term_id;

			} else {

				$term_data = wp_insert_term( $term, $taxonomy, array( 'slug' => sanitize_title( $term ) ) );

				if( ! is_wp_error( $term_data ) ) {

					$term_ids[] = $term_data['term_id'];

				}

			}

		}

		return array_map( 'absint', $term_ids );
	}

	/**
	 * Retrieve URL to Downloads list table
	 *
	 * @since 1.0.4
	 * @return string
	 */
	public function get_list_table_url() {
		return edd_get_admin_base_url();
	}

	/**
	 * Retrieve Download label
	 *
	 * @since 1.0.4
	 * @return void
	 */
	public function get_import_type_label() {
		return edd_get_label_plural( true );
	}

}
