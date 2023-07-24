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
		$source = \CP_Library\Admin\Settings::get_item( 'variation_source' );
		return apply_filters( 'cpl_variations_source', $source );
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
		$sources = $this->get_sources();
		$source  = __( 'Source could not be found', 'cp-library' );

		if ( isset( $sources[ $this->get_source() ] ) ) {
			$source = $sources[ $this->get_source() ];
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
