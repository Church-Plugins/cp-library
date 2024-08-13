<?php

namespace CP_Library\Integrations;

use ChurchPlugins\Exception;
use ChurchPlugins\Helpers;
use CP_Library\Admin\Import\ImportSermons;
use CP_Library\Models\Item;
use CP_Resources\Models\Resource;

class Resources {

	/**
	 * @var Resources
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Resources
	 *
	 * @return Resources
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Resources ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * @return void
	 */
	protected function includes() {}

	protected function actions() {
		add_action( 'cp_library_tools_import_additional_options', [ $this, 'import_options' ] );
		add_filter( 'cp_do_ajax_import_options', [ $this, 'set_variation_options' ], 10, 2 );
		add_filter( 'cp_library_import_process_step_item', [ $this, 'process_item' ], 10, 4 );
		add_filter( 'cp_resources_output_resources_check_object', [ $this, 'output_resources_hotwire' ] );
	}

	/** Actions ***************************************************/

	public function import_options() {
		?>
		<p>
			<input type='checkbox' id='import-resources' name='import-resources'>
			<label for='import-resources'><?php esc_html_e( 'Import columns with the "resource_" prefix as resources.', 'cp-library' ); ?></label>
		</p>
		<p>
			<input type='checkbox' id='sideload-resources' name='sideload-resources'>
			<label for='sideload-resources'><?php esc_html_e( 'Attempt to import resource files to the Media Library', 'cp-library' ); ?></label>
		</p>
		<?php
	}

	public function set_variation_options( $options, $map ) {
		$options['import_resources'] = Helpers::get_param( $map, 'import-resources' ) == 'on';
		$options['sideload_resources'] = Helpers::get_param( $map, 'sideload-resources' ) == 'on';

		return $options;
	}

	/**
	 * Process resources for the provided item
	 *
	 * @since  1.1.0
	 *
	 * @param $item Item
	 * @param $row
	 * @param $options
	 * @param $importer ImportSermons
	 *
	 * @author Tanner Moushey, 5/26/23
	 */
	public function process_item( $item, $row, $options, $importer ) {
		if ( empty( $options['import_resources'] ) ) {
			return;
		}

		$count = 0;
		foreach( $row as $key => $value ) {
			if ( 0 !== strpos( $key, 'resource_' ) ) {
				continue;
			}

			$data = explode( ';', $value );

			// make sure we have a title and that this Item doesn't already have a resource with the same name
			if ( empty( $data[0] ) || empty( $data[1] ) || $this->item_has_resource( $data[0], $item->origin_id ) ) {
				continue;
			}

			if ( ! $resource = $this->save_resource( $data, $item->origin_id, $count ++ ) ) {
				continue;
			}

			if ( ! empty( $options[ 'sideload_resources' ] ) ) {
				$url = $importer->sideload_media_and_get_url( $resource->origin_id, $resource->get_meta_value( 'resource_url' ) );

				if ( $url === $resource->get_meta_value( 'resource_url' ) ) {
					error_log( 'Could not import the resource media: ' . $url );
				} else {
					$resource->update_url( $url );
				}
			}
		}
	}

	public function save_resource( $data, $object_id, $order = 0 ) {
		$resource = [];

		foreach( $data as $index => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			switch ( $index ) {
				case 0:
					$resource['title'] = trim( $data[ $index ] );
					break;
				case 1:
					$resource['url'] = esc_url( trim( $data[ $index ] ) );
					break;
				case 2:
					$resource['visibility'] = trim( $data[ $index ] );

					if ( false !== strpos( strtolower( $resource['visibility'] ), 'hide' ) ) {
						$resource['visibility'] = 'on';
					}

					break;
				case 3:
					$resource['type'] = trim( $data[ $index ] );
					break;
				case 4:
					$resource['topic'] = trim( $data[ $index ] );
					break;
			}
		}

		try {
			return Resource::create( $resource, $object_id, $order );
		} catch ( Exception $e ) {
			error_log( $e );
		}

		return false;
	}

	/**
	 * Determine if the resource already has this title. Don't want duplicates.
	 *
	 * @since  1.1.0
	 *
	 * @param $title
	 * @param $object_id
	 *
	 * @return bool
	 * @author Tanner Moushey, 5/26/23
	 */
	public function item_has_resource( $title, $object_id ) {
		$resources = wp_list_pluck( Resource::get_all_resources( $object_id ), 'title' );

		foreach ( $resources as $resource_title ) {
			if ( sanitize_title( $title ) == sanitize_title( $resource_title ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle resource output for sermon page when in Series mode
	 *
	 * @since  1.1.0
	 *
	 * @param $content
	 *
	 * @return mixed|string
	 * @author Tanner Moushey, 6/19/23
	 */
	public function output_resources_hotwire( $check_object ) {
		if ( get_query_var( 'type-item' ) ) {
			return false;
		}

		return $check_object;
	}
}
