<?php
/**
 * Stubs for rendering the speaker title templates without WordPress.
 *
 * @package CP_Library
 */

namespace CP_Library\Admin {

	if ( ! class_exists( Settings::class, false ) ) {
		class Settings {
			/** @var bool */
			public static $permalinks = false;

			public static function get_item( $key, $default = array() ) {
				return array( 'speakers' );
			}

			public static function get_speaker( $key, $default = false ) {
				return self::$permalinks;
			}
		}
	}
}

namespace ChurchPlugins {

	if ( ! class_exists( Helpers::class, false ) ) {
		class Helpers {
			public static function get_icon( $name ) {
				return '';
			}
		}
	}
}

namespace CP_Library\Controllers {

	if ( ! class_exists( Item::class, false ) ) {
		class Item {
			public function __construct( $id ) {
			}

			public function get_podcast_speakers() {
				return "Faith & Works, O'Brien";
			}

			public function get_podcast_subtitle() {
				return '';
			}

			public function get_podcast_description_html() {
				return '';
			}

			public function get_podcast_description() {
				return '';
			}

			public function get_podcast_content() {
				return '';
			}

			public function get_duration() {
				return '';
			}
		}
	}
}

namespace {

	if ( ! function_exists( 'esc_html' ) ) {
		/**
		 * Matches WordPress esc_html(): encode once, leave an existing entity in place.
		 *
		 * @param string $text
		 * @return string
		 */
		function esc_html( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url ) {
			return (string) $url;
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
		}
	}

	if ( ! function_exists( 'wp_list_pluck' ) ) {
		function wp_list_pluck( $list, $field ) {
			$values = array();
			foreach ( $list as $item ) {
				$values[] = is_object( $item ) ? $item->$field : $item[ $field ];
			}
			return $values;
		}
	}

	if ( ! function_exists( 'get_permalink' ) ) {
		function get_permalink( $id ) {
			return 'https://example.test/speaker/' . $id;
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $id ) {
			return (object) array( 'ID' => $id );
		}
	}

	if ( ! function_exists( 'setup_postdata' ) ) {
		function setup_postdata( $post ) {
		}
	}

	if ( ! function_exists( 'wp_reset_postdata' ) ) {
		function wp_reset_postdata() {
		}
	}

	if ( ! function_exists( 'the_permalink' ) ) {
		function the_permalink() {
			echo 'https://example.test/sermon/';
		}
	}

	if ( ! function_exists( 'the_title' ) ) {
		function the_title() {
			echo 'Sermon';
		}
	}

	if ( ! function_exists( 'the_title_rss' ) ) {
		function the_title_rss() {
		}
	}

	if ( ! function_exists( 'the_guid' ) ) {
		function the_guid() {
		}
	}

	if ( ! function_exists( 'the_permalink_rss' ) ) {
		function the_permalink_rss() {
		}
	}

	if ( ! function_exists( 'get_the_ID' ) ) {
		function get_the_ID() {
			return 1;
		}
	}

	if ( ! function_exists( 'get_post_time' ) ) {
		function get_post_time( ...$args ) {
			return '2020-01-01 00:00:00';
		}
	}

	if ( ! function_exists( 'mysql2date' ) ) {
		function mysql2date( $format, $date, $translate = true ) {
			return 'Wed, 01 Jan 2020 00:00:00 +0000';
		}
	}

	if ( ! function_exists( 'rss_enclosure' ) ) {
		function rss_enclosure() {
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $hook, ...$args ) {
		}
	}

	if ( ! function_exists( 'cp_library' ) ) {
		function cp_library() {
			return new class() {
				public $templates;

				public function __construct() {
					$this->templates = new class() {
						public function get_template_part( ...$args ) {
						}
					};
				}
			};
		}
	}
}
