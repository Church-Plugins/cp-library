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
		$enabled = \CP_Library\Admin\Settings::get_item( 'variations_enabled', false );

		// The Disable radio is stored as the string "0". (bool) "0" is true, so a
		// saved "off" would otherwise leave Variations enabled.
		if ( '0' === $enabled ) {
			$enabled = false;
		} else {
			$enabled = (bool) $enabled;
		}

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
	 * Get variation source
	 *
	 * @since  1.1.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 5/5/23
	 */
	public function get_source_label() {
		$key = $this->get_source();

		if ( '' === $key ) {
			$source = __( 'None', 'cp-library' );
		} else {
			$sources = $this->get_sources();
			$source  = __( 'Source could not be found', 'cp-library' );

			if ( isset( $sources[ $key ] ) ) {
				$source = $sources[ $key ];
			}
		}

		return apply_filters( 'cpl_variations_source', $source );
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
