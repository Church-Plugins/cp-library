<?php
namespace CP_Library\Setup;

use CP_Library\Controllers\Item;
use ChurchPlugins\Exception;
use CP_Library\Init as Init;
use CP_Library\Models\ServiceType;
use CP_Library\Templates;
use CP_Library\Views\Shortcode as Shortcode_View;

/**
 * Shortcode controller class
 *
 * @author costmo
 */
class Shortcode
{

	/**
	 * Singleton instance
	 *
	 * @var Shortcode
	 */
	protected static $_instance;

	protected static $_query = false;

	/**
	 * Enforce singleton instantiation
	 *
	 * @return Shortcode
	 */
	public static function get_instance() {
		if( !self::$_instance instanceof Shortcode ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 * @author costmo
	 * @return void
	 */
	protected function __construct() {
		$this->add_shortcodes();
	}

	/**
	 * Add the app's custom shortcodes to WP
	 *
	 * @param array $params
	 * @return void
	 * @author costmo
	 */
	public function add_shortcodes() {

		// An array of mappings from `shortcode` => `handler method`
		$codes = [
			'cpl_item_list'    => 'render_item_list',
			'cpl_item'         => 'render_item',
			'cp-sermon'        => 'render_item',
			'cpl_item_widget'  => 'render_item_widget',
			'cpl_video_widget' => 'render_video_widget',
			'cpl_source_list'  => 'render_source_list',
			'cpl_source'       => 'render_source',
			'cpl_player'       => 'render_player',
			'cp-sermons'       => 'render_sermons_archive',
		];

		foreach( $codes as $shortcode => $handler ) {
			add_shortcode( $shortcode, [ $this, $handler ] );
		}
	}


	/**
	 * Generate static JS to feed parameters between PHP and JS
	 *
	 * @param array $args
	 * @return String
	 * @author costmo
	 */
	protected static function staticScript( $args = array() ) {

		$output = '
		<script>
			var cplParams = cplParams || {};
		';
		// Push shortcode parameters to the frontend so that JS has access to the data
		if( !empty( $args) && is_array( $args ) ) {
			foreach( $args as $key => $value ) {
				$output .= "cplParams." . $key . " = '" . $value . "';\n";
			}
		}
		$output .= '
		</script>
		';

		return $output;
	}

	public function render_item_list( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_item_list"></div>';

		return $output;
	}

	public function render_item( $atts ) {
		$atts = shortcode_atts( [
			'id' => 'false',
			'player' => 'true',
			'details' => 'true',
			'location' => 0,
			'service-type' => '',
			'template' => '',
		], $atts, 'cpl_item' );

		if ( 'false' === $atts['id'] || empty( $atts['id'] ) ) {
			$args = [
				'post_type' => cp_library()->setup->post_types->item->post_type,
				'posts_per_page' => 1,
				'post_status' => 'publish',
			];

			if ( ! empty( $atts['location'] ) ) {
				$args['cp_location'] = 'location_' . $atts['location'];
			}

			if ( ! empty( $atts['service-type'] ) ) {
				try {
					$type = ServiceType::get_instance_from_origin( $atts['service-type'] );
					$args['post__in'] = $type->get_all_items();
				} catch( \Exception $e ) {
					error_log( $e );
				}
			}

			$items = get_posts( $args );

			if ( empty( $items ) ) {
				return 'No ' . cp_library()->setup->post_types->item->plural_label . ' found.';
			}

			$id = $items[0]->ID;
		} else {
			$id = $atts['id'];
		}


		try {
			$item = new Item( $id );
		} catch( Exception $e ) {
			return 'No ' . cp_library()->setup->post_types->item->plural_label . ' found.';
		}

		$atts['item'] = $item->get_api_data( true );
		ob_start();

		if ( 'alt' == $atts['template'] ) {
			cp_library()->templates->get_template_part( 'widgets/item-single--alt', $atts );
		} else {
			cp_library()->templates->get_template_part( 'widgets/item-single', $atts );
		}

		return ob_get_clean();

	}

	public function render_source_list( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_source_list"></div>';

		return $output;

	}

	public function render_source( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_source"></div>';

		return $output;

	}

	public function render_player( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_player"></div>';

		return $output;
	}

	/**
	 * Render persistent player HTML
	 *
	 * Use JS to make sure we are in the body container
	 *
	 * @since  1.0.0
	 * @updated 1.1.0
	 * @deprecated 1.4.2
	 *
	 * @author Tanner Moushey
	 */
	public function render_persistent_player() {
		echo '<script>jQuery(document).ready(function() {jQuery("body").append(\'<div id="cpl_persistent_player"></div>\');});</script>';
	}

	public function render_item_widget( $args ) {
		$output  = self::staticScript( $args );

		$request = new \WP_REST_Request( 'GET', '/' . cp_library()->get_api_namespace() . '/items' );
		$request->set_query_params( [ 'count' => 1, 'media-type' => 'audio' ] );
		$response = rest_do_request( $request );
		$server   = rest_get_server();
		$data     = $server->response_to_data( $response, false );

		if ( ! empty( $data['items'] ) ) {
			$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_item_widget" data-item="' . esc_attr( json_encode( $data['items'][0] ) ) . '"></div>';
		}

		return $output;
	}

	public function render_video_widget( $args ) {
		$output  = self::staticScript( $args );

		$request = new \WP_REST_Request( 'GET', '/' . cp_library()->get_api_namespace() . '/items' );
		$request->set_query_params( [ 'count' => 1, 'media-type' => 'video' ] );
		$response = rest_do_request( $request );
		$server   = rest_get_server();
		$data     = $server->response_to_data( $response, false );

		if ( ! empty( $data['items'] ) ) {
			$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_video_widget" data-item="' . esc_attr( json_encode( $data['items'][0] ) ) . '"></div>';
		}

		return $output;
	}

	public function render_sermons_archive() {
		global $wp_query;

		$taxonomies = cp_library()->setup->taxonomies->get_objects();

		$args = [
			'post_type' => 'cpl_item',
			'post_status' => 'publish',
		];

		self::$_query = $wp_query = new \WP_Query( $args );

		ob_start();

		cp_library()->templates->get_template_part( 'archive' );

		$content = ob_get_clean();
		wp_reset_query();

		add_action( 'wp_footer', [ $this, 'custom_query_vars' ], 500 );
		return $content;
	}

	public function custom_query_vars() {
		if ( ! self::$_query ) {
			return;
		} ?>

		<script>
			var cplVars = cplVars || {};
			cplVars.query_vars = <?php echo json_encode( self::$_query->query_vars ); ?>;
		</script>
		<?php
	}

}
