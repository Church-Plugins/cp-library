<?php
namespace CP_Library\Setup;

/**
 * Variation controller class
 */
class Variations {

	/**
	 * Singleton instance
	 *
	 * @var Variations
	 */
	protected static $_instance;

	/**
	 * Enforce singleton instantiation
	 *
	 * @return Variations
	 */
	public static function get_instance() {
		if( !self::$_instance instanceof Variations ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
	}

	/**
	 * Whether Variations are enabled
	 *
	 * @since  1.1.0
	 *
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/5/23
	 */
	public function is_enabled() {
		$enabled = (bool) \CP_Library\Admin\Settings::get_item( 'variations_enabled', false );
		return apply_filters( 'cpl_enable_variations', $enabled );
	}

	/**
	 * Get items for the active source
	 *
	 * @param $id return only the ids
	 * @since  1.1.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/5/23
	 */
	public function get_source_items( $id = false ) {
		$source = $this->get_source();

		// None saves an empty source. Don't build a filter name from it.
		if ( '' === $source ) {
			return [];
		}

		$items = apply_filters( 'cpl_variations_source_items_' . $source, [] );

		if ( $id ) {
			$items = array_keys( $items );
		}

		return $items;
	}

	/**
	 * Get variation source
	 *
	 * @since  1.1.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/5/23
	 */
	public function get_source() {
		$source = \CP_Library\Admin\Settings::get_item( 'variation_source', '' );
		$source = apply_filters( 'cpl_variations_source', $source );

		if ( ! is_string( $source ) ) {
			return '';
		}

		return $source;
	}

	/**
	 * Service Types are the variation source only while Variations are on and
	 * the saved source is the service type. An empty source (None) is not.
	 *
	 * @return bool
	 */
	public function service_type_is_source() {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$source = $this->get_source();
		if ( '' === $source ) {
			return false;
		}

		$service_type = cp_library()->setup->post_types->service_type;

		return is_object( $service_type ) && $source === $service_type->post_type;
	}

	/**
	 * Label for the active variation source.
	 *
	 * get_source() already filters the key. The label is the name from
	 * get_sources(), so that key filter cannot replace it with the slug.
	 *
	 * @since  1.1.0
	 *
	 * @return string
	 */
	public function get_source_label() {
		$key = $this->get_source();

		if ( '' === $key ) {
			return __( 'None', 'cp-library' );
		}

		$sources = $this->get_sources();

		if ( isset( $sources[ $key ] ) ) {
			return $sources[ $key ];
		}

		return __( 'Source could not be found', 'cp-library' );
	}

	/**
	 * Return a list of possible variation sources
	 *
	 * @since  1.1.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/5/23
	 */
	public function get_sources() {
		return apply_filters( 'cpl_variations_sources', [] );
	}


}
