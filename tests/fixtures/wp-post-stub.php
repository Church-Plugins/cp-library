<?php
/**
 * Minimal WP_Post for unit tests that branch on instanceof \WP_Post.
 *
 * The unit suite does not boot WordPress. This is only the property bag the
 * SermonAudio save path reads. It is not a stand-in for post behavior.
 *
 * @package CP_Library
 */

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * @package CP_Library
	 */
	class WP_Post {
		/** @var int */
		public $ID = 0;

		/** @var string */
		public $post_content = '';

		/** @var string */
		public $post_status = 'publish';

		/** @var string */
		public $post_name = 'john-smith';

		/** @var string */
		public $post_type = '';

		/** @var string */
		public $post_title = '';

		/** @var string */
		public $post_date = '';

		/** @var string */
		public $post_date_gmt = '';
	}
}
