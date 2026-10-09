<?php
/**
 * Tests for single-item reads and the shortcode item check.
 *
 * A single item is returned when it is publicly viewable and not
 * password-protected, or when the current user can read or edit it.
 * Anything else is a not-found error. The [cpl_item] / [cp-sermon]
 * shortcode uses the same public check and only makes an exception for
 * a user who can edit the item.
 *
 * @package CP_Library
 */

namespace {
	if ( ! class_exists( 'WP_REST_Controller' ) ) {
		class WP_REST_Controller {}
	}

	if ( ! function_exists( 'cp_library' ) ) {
		/**
		 * Minimal stand-in so the shortcode can build its not-found message.
		 */
		function cp_library() {
			$item = (object) [
				'post_type'    => 'cpl_item',
				'plural_label' => 'Messages',
			];

			return (object) [
				'setup' => (object) [
					'post_types' => (object) [
						'item' => $item,
					],
				],
			];
		}
	}
}

namespace CP_Library\Tests\Unit {

	use Brain\Monkey;
	use Brain\Monkey\Functions;
	use CP_Library\API\Items;
	use CP_Library\Controllers\Item;
	use CP_Library\Setup\Shortcode;
	use PHPUnit\Framework\TestCase;
	use ReflectionClass;

	/**
	 * Request double with the bits the item routes read.
	 */
	class ItemReadRequest {
		/** @var array */
		public $params = [];

		/** @var string */
		public $method = 'GET';

		/** @var string */
		public $route = '/cpl/v1/items/15';

		public function get_param( $key ) {
			return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
		}

		public function get_method() {
			return $this->method;
		}

		public function get_route() {
			return $this->route;
		}
	}

	/**
	 * @covers \CP_Library\Controllers\Item::item_is_readable
	 * @covers \CP_Library\Controllers\Item::item_is_viewable_or_editable
	 * @covers \CP_Library\API\Items::get_item
	 * @covers \CP_Library\API\Items::get_permissions_check
	 * @covers \CP_Library\Setup\Shortcode::render_item
	 */
	class ItemVisibilityTest extends TestCase {

		/** @var array */
		private $posts = [];

		/** @var bool */
		private $publicly_viewable = false;

		/** @var bool */
		private $password_required = false;

		/** @var array<string,bool> */
		private $caps = [];

		/** @var Items */
		private $api;

		protected function setUp(): void {
			parent::setUp();
			Monkey\setUp();

			$test = $this;

			Functions\when( 'get_post' )->alias(
				static function ( $post_id ) use ( $test ) {
					$id = (int) $post_id;

					return isset( $test->posts[ $id ] ) ? $test->posts[ $id ] : null;
				}
			);

			Functions\when( 'get_page_by_path' )->alias(
				static function ( $path ) use ( $test ) {
					return isset( $test->posts[ $path ] ) ? $test->posts[ $path ] : null;
				}
			);

			Functions\when( 'is_post_publicly_viewable' )->alias(
				static function ( $post_id ) use ( $test ) {
					$id = is_object( $post_id ) ? $post_id->ID : $post_id;

					return $test->publiclyViewable() && 15 === (int) $id;
				}
			);

			Functions\when( 'post_password_required' )->alias(
				static function () use ( $test ) {
					return $test->passwordRequired();
				}
			);

			Functions\when( 'current_user_can' )->alias(
				static function ( $cap, $post_id = 0 ) use ( $test ) {
					return ! empty( $test->caps[ $cap ] ) && 15 === (int) $post_id;
				}
			);

			Functions\when( 'shortcode_atts' )->alias(
				static function ( $pairs, $atts ) {
					return array_merge( $pairs, (array) $atts );
				}
			);

			$this->api            = ( new ReflectionClass( Items::class ) )->newInstanceWithoutConstructor();
			$this->api->post_type = 'cpl_item';
		}

		protected function tearDown(): void {
			Monkey\tearDown();
			parent::tearDown();
		}

		public function publiclyViewable() {
			return $this->publicly_viewable;
		}

		public function passwordRequired() {
			return $this->password_required;
		}

		public function test_single_item_read_is_not_found_for_a_draft() {
			$this->posts[15] = (object) [
				'ID'          => 15,
				'post_type'   => 'cpl_item',
				'post_status' => 'draft',
			];

			$error = $this->api->get_item( $this->request( [ 'item_id' => 15 ] ) );

			$this->assertNotFound( $error );
			$this->assertNotFound( $this->api->get_permissions_check( $this->request( [ 'item_id' => 15 ] ) ) );
		}

		public function test_single_item_read_is_not_found_when_missing() {
			$this->assertNotFound( $this->api->get_item( $this->request( [ 'item_id' => 99 ] ) ) );
			$this->assertNotFound( $this->api->get_item( $this->request( [ 'item_id' => 'missing-slug' ] ) ) );
		}

		public function test_single_item_read_is_not_found_for_another_post_type() {
			$this->posts[15] = (object) [
				'ID'        => 15,
				'post_type' => 'page',
			];

			$this->assertNotFound( $this->api->get_item( $this->request( [ 'item_id' => '15' ] ) ) );
		}

		public function test_password_protected_item_is_not_found_without_access() {
			$this->posts[15]           = (object) [
				'ID'        => 15,
				'post_type' => 'cpl_item',
			];
			$this->publicly_viewable   = true;
			$this->password_required   = true;

			$this->assertNotFound( $this->api->get_item( $this->request( [ 'item_id' => 15 ] ) ) );
			$this->assertFalse( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_public_item_is_readable() {
			$this->posts[15]         = (object) [
				'ID'        => 15,
				'post_type' => 'cpl_item',
			];
			$this->publicly_viewable = true;

			$this->assertTrue( $this->api->get_permissions_check( $this->request( [ 'item_id' => 15 ] ) ) );
			$this->assertTrue( Item::item_is_readable( 15 ) );
			$this->assertTrue( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_reader_can_read_a_non_public_item() {
			$this->posts[15]       = (object) [
				'ID'          => 15,
				'post_type'   => 'cpl_item',
				'post_status' => 'private',
			];
			$this->caps['read_post'] = true;

			$this->assertTrue( $this->api->get_permissions_check( $this->request( [ 'item_id' => 15 ] ) ) );
			$this->assertTrue( Item::item_is_readable( 15 ) );
			$this->assertFalse( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_editor_can_read_a_non_public_item() {
			$this->posts[15] = (object) [
				'ID'          => 15,
				'post_type'   => 'cpl_item',
				'post_status' => 'draft',
			];
			$this->caps['edit_post'] = true;

			$this->assertTrue( $this->api->get_permissions_check( $this->request( [ 'item_id' => 15 ] ) ) );
			$this->assertTrue( Item::item_is_readable( 15 ) );
			$this->assertTrue( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_slug_lookup_uses_the_same_not_found_result() {
			$this->posts['draft-sermon'] = (object) [
				'ID'          => 15,
				'post_type'   => 'cpl_item',
				'post_status' => 'draft',
			];

			$this->assertNotFound( $this->api->get_item( $this->request( [ 'item_id' => 'draft-sermon' ] ) ) );
		}

		public function test_collection_and_log_routes_stay_open() {
			$collection = $this->request( [] );
			$collection->route = '/cpl/v1/items';

			$log          = $this->request( [ 'item_id' => 15 ] );
			$log->method  = 'POST';
			$log->route   = '/cpl/v1/items/15/log';

			$this->assertTrue( $this->api->get_permissions_check( $collection ) );
			$this->assertTrue( $this->api->get_permissions_check( $log ) );
		}

		public function test_shortcode_renders_not_found_for_a_non_public_item() {
			$this->posts[15] = (object) [
				'ID'          => 15,
				'post_type'   => 'cpl_item',
				'post_status' => 'draft',
			];

			$shortcode = ( new ReflectionClass( Shortcode::class ) )->newInstanceWithoutConstructor();

			$this->assertSame( 'No Messages found.', $shortcode->render_item( [ 'id' => 15 ] ) );
		}

		public function test_shortcode_allows_an_editor_to_view_a_non_public_item() {
			$this->caps['edit_post'] = true;

			$this->assertTrue( Item::item_is_viewable_or_editable( 15 ) );
		}

		/**
		 * @param array $params
		 * @return ItemReadRequest
		 */
		private function request( $params ) {
			$request         = new ItemReadRequest();
			$request->params = $params;

			return $request;
		}

		/**
		 * @param mixed $result
		 */
		private function assertNotFound( $result ) {
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 404, $result->data['status'] );
			$this->assertSame( 'Could not find the requested item', $result->get_error_message() );
		}
	}
}
