<?php
/**
 * Boots the SermonAudio match-existing add-on.
 *
 * @package CP_SermonAudio_Match
 */

namespace CP_SermonAudio_Match;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the filters that run before a SermonAudio sermon is saved.
 */
class Plugin {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Attach on plugins_loaded, or immediately when that has already run.
	 *
	 * The integration harness loads this file after WordPress has booted, so
	 * a late plugins_loaded callback would never run.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( function_exists( 'did_action' ) && did_action( 'plugins_loaded' ) ) {
			self::register();
			return;
		}

		add_action( 'plugins_loaded', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Hook the two SermonAudio item filters and the post-data filter.
	 *
	 * Priority 100 runs after a filter that adjusts the formatted sermon,
	 * and before load_item() reads the result.
	 *
	 * @return void
	 */
	public static function register() {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_filter( 'cp_library_sermon_audio_item', array( Matcher::class, 'filter_item' ), 100 );
		add_filter( 'cpl_sermon_audio_adapter_item', array( Matcher::class, 'filter_item' ), 100, 2 );
		add_filter( 'wp_insert_post_data', array( Matcher::class, 'keep_linked_content' ), 100, 2 );
		add_action( 'save_post', array( Matcher::class, 'finish_sync_save' ), 999 );
		add_action( 'shutdown', array( Matcher::class, 'clear_sync_flags' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice_missing_core' ) );
	}

	/**
	 * Ask for CP Sermon Library when this add-on is active without it.
	 *
	 * @return void
	 */
	public static function notice_missing_core() {
		if ( function_exists( 'cp_library' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'CP Sermons - SermonAudio Match Existing needs CP Sermon Library to be active.', 'cp-sermonaudio-match' );
		echo '</p></div>';
	}
}
