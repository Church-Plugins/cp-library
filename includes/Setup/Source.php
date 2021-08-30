<?php

namespace SC_Library\Setup;

/**
 * Source DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_DB_Customers Class
 *
 * @since 2.1
 */
class Source extends Object  {

//	/**
//	 * The metadata type.
//	 *
//	 * @since  2.8
//	 * @var string
//	 */
//	public $meta_type = 'customer';

	/**
	 * The name of the date column.
	 *
	 * @since  2.8
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
		$this->table_name  = $wpdb->prefix . SCL_APP_PREFIX . '_source';
		parent::__construct();
	}

}
