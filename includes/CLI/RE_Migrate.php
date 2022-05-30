<?php

use CP_Library\Models\Item;
use CP_Library\Models\ItemType;

// Make the `cp` command available to WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'cp', 'RE_Migrate' );
}

/**
 * Provides the wp-cli 'cp' command
 *
 * For more info, use: cp [command] --help
 *
 * Example:
 * wp cp migrate
 *
 * @author Tanner Moushey
 */
class RE_Migrate {

	/**
	 * Class constructor. Initialize members.
	 *
	 * @author Landon Otis
	 */
	public function __construct() {
	}

	public function import_series( $args, $assoc_args ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$args = wp_parse_args( [
			0 => 'series.csv',
		], $args );

		$filename = ABSPATH . $args[0];

		if ( ! $file = fopen( $filename, 'r' ) ) {
			WP_CLI::error('Could not locate the file');
		}

		$row = 0;
		while( $data = fgetcsv( $file ) ) {
			count( $data );

			if ( ++ $row == 1 ) {
				$headers = array_flip( $data ); // Get the column names from the header.
				continue;
			}

			$data = array_map( 'trim', $data );

			$slug = array_pop( explode( '/', $data[ $headers['URL'] ] ) );
			$title = $data[ $headers['Title'] ];
			$desc = $data[ $headers['Description'] ];
			$thumb = $data[ $headers['Thumbnail'] ];
			$study_guide = str_replace( 'https://christpres.mediahttps:', 'https:', $data[ $headers['Study Guide'] ] );

			WP_CLI::log( 'Importing ' . $title );
			if ( get_page_by_path( $slug, OBJECT, ItemType::get_prop( 'post_type' ) ) ) {
				WP_CLI::warning( 'This content already exists' );
				continue;
			}

			$series_id = wp_insert_post( [
				'post_type'    => ItemType::get_prop( 'post_type' ),
				'post_title'   => $title,
				'post_content' => $desc,
				'post_status'  => 'publish',
				'post_name'     => $slug,
			], true );

			WP_CLI::log( '--- series created' );

			// save to our tables
			ItemType::get_instance_from_origin( $series_id );

			if ( is_wp_error( $series_id ) ) {
				WP_CLI::warning( $series_id->get_error_message() );
			}

			// Handle thumbnail
			$thumb_id = media_sideload_image( $thumb, $series_id, $title . ' Thumbnail', 'id' );

			if ( is_wp_error( $thumb_id ) ) {
				WP_CLI::warning( $thumb_id->get_error_message() );
			} else {
				WP_CLI::log( '--- imported thumbnail' );
			}

			set_post_thumbnail( $series_id, $thumb_id );

			// Handle study guide
			$study_guide_url = media_sideload_image( $study_guide, $series_id, $title . ' Study Guide', 'src' );

			if ( is_wp_error( $study_guide_url ) ) {
				WP_CLI::warning( $study_guide_url->get_error_message() );
			} else {
				WP_CLI::log( '--- imported study guide' );
			}

			update_post_meta( $series_id, 'cp_study_guide', $study_guide_url );

		}

	}

	/**
	 * Script for migrating old sermons and talks to new CP Items
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @throws \WP_CLI\ExitException
	 * @author Tanner Moushey
	 */
	public function migrate( $args, $assoc_args ) {
		$talks = get_posts( [
			'post_type' => 'talk',
			'posts_per_page' => -1
		] );

		$sermons = get_posts( [
			'post_type' => 'sermon',
			'posts_per_page' => -1
		] );

		$total = count( $talks ) + count( $sermons );

		$progress = WP_CLI\Utils\make_progress_bar( "Migrating " . $total . " items", $total );

		foreach( $talks as $talk ) {
			$progress->tick();
			$talk->post_type = 'cpl_item';
			$audio = get_post_meta( $talk->ID, 'talk_file', true );

			wp_update_post( $talk );

			if ( ! $audio ) {
				WP_CLI::warning( 'no audio' );
			}

			try {
				$item = Item::get_instance_from_origin( $talk->ID );

				if ( ! $audio ) {
					WP_CLI::warning( 'no audio' );
				} else {
					$item->update_meta_value( 'audio_url', $audio['url'] );
					wp_set_object_terms( $talk->ID, 'audio', 're_media_type' );
				}
			} catch ( Exception $e ) {
				WP_CLI::warning( $e->getMessage() );
			}
		}

		foreach( $sermons as $sermon ) {
			$progress->tick();
			$sermon->post_type = 'cpl_item';
			$vimeo = get_post_meta( $sermon->ID, 'vimeo_video_id', true );
			$facebook = get_post_meta( $sermon->ID, 'facebook_video_id', true );

			wp_update_post( $sermon );

			try {
				$item = Item::get_instance_from_origin( $sermon->ID );

				if ( $facebook ) {
					$item->update_meta_value( 'video_id_facebook', $facebook );
				}

				if ( $vimeo ) {
					$item->update_meta_value( 'video_id_vimeo', $vimeo );
				}

				wp_set_object_terms( $sermon->ID, 'video', 're_media_type' );

			} catch ( Exception $e ) {
				WP_CLI::warning( $e->getMessage() );
			}
		}

		$progress->finish();

		WP_CLI::log( 'Finished' );

	}
}
