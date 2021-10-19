<?php

use CP_Library\Models\Item;

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
			$talk->post_type = 'cpl_items';
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
			$sermon->post_type = 'cpl_items';
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
