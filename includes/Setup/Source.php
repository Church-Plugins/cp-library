<?php

namespace CP_Library\Setup;

/**
 * Source DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Source Class
 *
 * @since 1.0
 */
class Source extends Asset  {

	/**
	 * The metadata type.
	 *
	 * @since  1.0
	 * @var string
	 */
	public $meta_type = 'source';

	/**
	 * The name of the date column.
	 *
	 * @since  1.0
	 * @var string
	 */
	public $date_key = 'published';

	/**
	 * The name of the cache group.
	 *
	 * @since  1.0
	 * @var string
	 */
	public $cache_group = 'source';

	/**
	 * Get things started
	 *
	 * @since  1.0
	*/
	public function __construct() {
		global $wpdb;
		$this->table_name  = $wpdb->prefix . CPL_APP_PREFIX . '_source';
		parent::__construct();
	}

}
