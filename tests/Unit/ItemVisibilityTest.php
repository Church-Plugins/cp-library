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
						'item'         => $item,
						'service_type' => (object) [
							'post_type' => 'cpl_service_type',
						],
					],
					'variations' => new class() {
						public function get_source() {
							return isset( $GLOBALS['cpl_test_variation_source'] ) ? $GLOBALS['cpl_test_variation_source'] : '';
						}

						public function is_enabled() {
							return true;
						}
					},
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
	use CP_Library\Setup\PostTypes\Item as ItemPostType;
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

		/** @var array<int,bool> */
		private $public_ids = [];

		/** @var array<int,bool> */
		private $password_ids = [];

		/** @var array<int,int> */
		private $parents = [];

		/** @var array<int,array<string,bool>> */
		private $caps_by_post = [];

		/** @var int */
		public $lookups = 0;

		/** @var Items */
		private $api;

		protected function setUp(): void {
			parent::setUp();
			Monkey\setUp();

			$test = $this;

			Functions\when( 'get_post' )->alias(
				static function ( $post_id ) use ( $test ) {
					$test->lookups++;
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
					$id = is_object( $post_id ) ? (int) $post_id->ID : (int) $post_id;

					if ( array_key_exists( $id, $test->publicIds() ) ) {
						return (bool) $test->publicIds()[ $id ];
					}

					return $test->publiclyViewable() && 15 === $id;
				}
			);

			Functions\when( 'post_password_required' )->alias(
				static function ( $post_id = 0 ) use ( $test ) {
					$id = is_object( $post_id ) ? (int) $post_id->ID : (int) $post_id;

					if ( array_key_exists( $id, $test->passwordIds() ) ) {
						return (bool) $test->passwordIds()[ $id ];
					}

					return $test->passwordRequired();
				}
			);

			Functions\when( 'current_user_can' )->alias(
				static function ( $cap, $post_id = 0 ) use ( $test ) {
					$id      = (int) $post_id;
					$by_post = $test->capsByPost();

					if ( array_key_exists( $id, $by_post ) ) {
						return ! empty( $by_post[ $id ][ $cap ] );
					}

					return ! empty( $test->caps[ $cap ] ) && 15 === $id;
				}
			);

			Functions\when( 'wp_get_post_parent_id' )->alias(
				static function ( $post_id ) use ( $test ) {
					$id = (int) $post_id;

					return isset( $test->parents()[ $id ] ) ? (int) $test->parents()[ $id ] : 0;
				}
			);

			Functions\when( '__' )->returnArg();

			Functions\when( 'apply_filters' )->alias(
				static function ( $tag, $value ) {
					return $value;
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
			unset( $_GET['show-child-items'], $_GET['speaker'], $_GET['service-type'], $GLOBALS['cpl_test_variation_source'], $GLOBALS['wpdb'] );
			Monkey\tearDown();
			parent::tearDown();
		}

		public function publicIds() {
			return $this->public_ids;
		}

		public function passwordIds() {
			return $this->password_ids;
		}

		public function parents() {
			return $this->parents;
		}

		public function capsByPost() {
			return $this->caps_by_post;
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

			$this->assertSame( 1, $this->lookups );
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

		public function test_password_protected_item_is_not_found_for_a_subscriber() {
			$this->posts[15]         = (object) [
				'ID'        => 15,
				'post_type' => 'cpl_item',
			];
			$this->publicly_viewable = true;
			$this->password_required = true;
			$this->caps['read_post'] = true;

			$this->assertNotFound( $this->api->get_item( $this->request( [ 'item_id' => 15 ] ) ) );
			$this->assertFalse( Item::item_is_readable( 15 ) );
			$this->assertFalse( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_editor_can_read_a_password_protected_item() {
			$this->posts[15]         = (object) [
				'ID'        => 15,
				'post_type' => 'cpl_item',
			];
			$this->publicly_viewable = true;
			$this->password_required = true;
			$this->caps['edit_post'] = true;

			$this->assertTrue( $this->api->get_permissions_check( $this->request( [ 'item_id' => 15 ] ) ) );
			$this->assertTrue( Item::item_is_readable( 15 ) );
			$this->assertTrue( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_item_is_readable_when_password_is_no_longer_required() {
			$this->posts[15]         = (object) [
				'ID'        => 15,
				'post_type' => 'cpl_item',
			];
			$this->publicly_viewable = true;
			$this->password_required = false;

			$this->assertTrue( Item::item_is_readable( 15 ) );
			$this->assertTrue( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_child_of_a_non_public_parent_is_not_readable() {
			$this->public_ids = [
				15 => true,
				20 => false,
			];
			$this->parents    = [ 15 => 20 ];

			$this->assertFalse( Item::item_is_readable( 15 ) );
			$this->assertFalse( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_child_is_readable_when_the_parent_can_be_read() {
			$this->public_ids   = [
				15 => true,
				20 => false,
			];
			$this->parents      = [ 15 => 20 ];
			$this->caps_by_post = [
				20 => [ 'read_post' => true ],
			];

			$this->assertTrue( Item::item_is_readable( 15 ) );
			$this->assertFalse( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_child_of_a_password_protected_parent_is_not_readable_with_plain_read() {
			$this->public_ids   = [
				15 => true,
				20 => true,
			];
			$this->password_ids = [ 20 => true ];
			$this->parents      = [ 15 => 20 ];
			$this->caps_by_post = [
				20 => [ 'read_post' => true ],
			];

			$this->assertFalse( Item::item_is_readable( 15 ) );
			$this->assertFalse( Item::item_is_viewable_or_editable( 15 ) );
		}

		public function test_child_list_hides_children_of_non_public_parents() {
			$this->public_ids = [
				15 => true,
				20 => false,
				30 => true,
				31 => true,
			];
			$this->parents = [
				15 => 20,
				30 => 31,
			];

			$visible = Item::visible_child_list_posts(
				[
					(object) [
						'ID'          => 15,
						'post_parent' => 20,
					],
					(object) [
						'ID'          => 30,
						'post_parent' => 31,
					],
					(object) [
						'ID'          => 40,
						'post_parent' => 0,
					],
				]
			);

			$this->assertSame( [ 30, 40 ], $this->idsOf( $visible ) );
		}

		public function test_editor_can_see_a_child_of_a_non_public_parent() {
			$this->public_ids   = [
				15 => true,
				20 => false,
			];
			$this->parents      = [ 15 => 20 ];
			$this->caps_by_post = [
				20 => [ 'edit_post' => true ],
			];

			$this->assertTrue( Item::item_is_viewable_or_editable( 15 ) );

			$visible = Item::visible_child_list_posts(
				[
					(object) [
						'ID'          => 15,
						'post_parent' => 20,
					],
				]
			);

			$this->assertSame( [ 15 ], $this->idsOf( $visible ) );
		}

		public function test_show_child_items_marks_the_list_for_a_visibility_check() {
			$_GET['show-child-items'] = '1';

			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';
			$query           = $this->query( [ 'post_type' => 'cpl_item' ] );

			$type->item_variation_query( $query );

			$this->assertTrue( (bool) $query->get( 'cpl_limit_child_visibility' ) );
			$this->assertNull( $query->get( 'post_parent' ) );
		}

		public function test_mixed_post_type_query_keeps_other_children_and_limits_item_children() {
			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';
			$query           = $this->query(
				array(
					'post_type' => array( 'post', 'page', 'cpl_item' ),
				)
			);

			$type->item_variation_query( $query );

			$this->assertNull( $query->get( 'post_parent' ) );
			$this->assertTrue( (bool) $query->get( 'cpl_limit_child_visibility' ) );

			$this->public_ids = array(
				15 => true,
				20 => false,
				30 => true,
				31 => true,
			);
			$this->parents    = array(
				15 => 20,
				30 => 31,
			);

			$visible = Item::visible_child_list_posts(
				array(
					(object) array(
						'ID'          => 15,
						'post_type'   => 'cpl_item',
						'post_parent' => 20,
					),
					(object) array(
						'ID'          => 30,
						'post_type'   => 'cpl_item',
						'post_parent' => 31,
					),
					(object) array(
						'ID'          => 40,
						'post_type'   => 'page',
						'post_parent' => 8,
					),
					(object) array(
						'ID'          => 41,
						'post_type'   => 'post',
						'post_parent' => 9,
					),
				)
			);

			$this->assertSame( array( 30, 40, 41 ), $this->idsOf( $visible ) );

			$sql = ItemPostType::child_list_sql( 'wp_posts', 'cpl_item', 'public', 0 );
			$this->assertStringContainsString( "wp_posts.post_type != 'cpl_item'", $sql );
			$this->assertStringContainsString( 'wp_posts.post_parent = 0', $sql );
		}

		public function test_child_visibility_limit_matches_an_array_of_post_types() {
			$_GET['show-child-items'] = '1';

			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';
			$query           = $this->query(
				array(
					'post_type' => array( 'post', 'cpl_item' ),
				)
			);

			$type->item_variation_query( $query );

			$this->assertTrue( (bool) $query->get( 'cpl_limit_child_visibility' ) );
			$this->assertTrue( $type->query_includes_item_type( $query ) );
			$this->assertTrue( $type->query_limits_child_visibility( $query ) );

			$other = $this->query( array( 'post_type' => 'page' ) );
			$type->item_variation_query( $other );
			$this->assertNull( $other->get( 'cpl_limit_child_visibility' ) );
			$this->assertFalse( $type->query_includes_item_type( $other ) );
		}

		public function test_speaker_filter_still_limits_child_visibility() {
			$_GET['speaker'] = '12';

			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';
			$query           = $this->query( [ 'post_type' => 'cpl_item' ] );

			$type->item_variation_query( $query );

			$this->assertTrue( (bool) $query->get( 'cpl_limit_child_visibility' ) );
			$this->assertNull( $query->get( 'post_parent' ) );
		}

		public function test_service_type_filter_still_limits_child_visibility() {
			$GLOBALS['cpl_test_variation_source'] = 'cpl_service_type';
			$_GET['service-type']                 = '3';

			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';
			$query           = $this->query( [ 'post_type' => 'cpl_item' ] );

			$type->item_variation_query( $query );

			$this->assertTrue( (bool) $query->get( 'cpl_limit_child_visibility' ) );
			$this->assertNull( $query->get( 'post_parent' ) );
		}

		public function test_child_visibility_scope_follows_the_user() {
			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';

			$this->assertSame( 'public', $type->child_visibility_scope() );

			$this->caps_by_post = array(
				0 => array( 'edit_posts' => true ),
			);
			$this->assertSame( 'own', $type->child_visibility_scope() );

			$this->caps_by_post = array(
				0 => array(
					'edit_posts'        => true,
					'edit_others_posts' => true,
				),
			);
			$this->assertSame( 'all', $type->child_visibility_scope() );
		}

		public function test_child_list_sql_keeps_public_parents_for_a_subscriber() {
			$sql = ItemPostType::child_list_sql( 'wp_posts', 'cpl_item', 'public', 0 );

			$this->assertStringContainsString( 'wp_posts.post_parent = 0', $sql );
			$this->assertStringContainsString( "post_status = 'publish'", $sql );
			$this->assertStringContainsString( "post_password = ''", $sql );
			$this->assertStringNotContainsString( 'post_author', $sql );
		}

		public function test_child_list_sql_includes_every_parent_for_an_editor() {
			$sql = ItemPostType::child_list_sql( 'wp_posts', 'cpl_item', 'all', 4 );

			$this->assertStringContainsString( 'draft', $sql );
			$this->assertStringContainsString( 'private', $sql );
			$this->assertStringNotContainsString( 'post_password', $sql );
		}

		public function test_child_list_where_uses_the_public_clause() {
			$GLOBALS['wpdb'] = (object) array( 'posts' => 'wp_posts' );

			Functions\when( 'get_current_user_id' )->justReturn( 0 );

			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';
			$query           = $this->query(
				array(
					'post_type'                   => 'cpl_item',
					'cpl_limit_child_visibility'  => true,
				)
			);

			$where = $type->limit_child_visibility_where( ' AND 1=1', $query );

			$this->assertStringContainsString( 'wp_posts.post_parent = 0', $where );
			$this->assertStringContainsString( "post_password = ''", $where );
			$this->assertSame( ' AND 1=1', $type->limit_child_visibility_where( ' AND 1=1', $this->query( array( 'post_type' => 'cpl_item' ) ) ) );
		}

		public function test_child_list_primes_parent_posts_before_filtering() {
			$primed = array();

			Functions\when( '_prime_post_caches' )->alias(
				static function ( $ids ) use ( &$primed ) {
					$primed = $ids;
				}
			);

			$this->public_ids = array(
				15 => true,
				20 => true,
			);
			$this->parents    = array( 15 => 20 );

			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';
			$query           = $this->query(
				array(
					'post_type'                  => 'cpl_item',
					'cpl_limit_child_visibility' => true,
				)
			);

			$result = $type->limit_child_visibility(
				array(
					(object) array(
						'ID'          => 15,
						'post_parent' => 20,
					),
				),
				$query
			);

			$this->assertSame( array( 20 ), $primed );
			$this->assertSame( array( 15 ), $this->idsOf( $result ) );
		}

		public function test_child_items_stay_hidden_unless_the_list_asks_for_them() {
			$type            = ( new ReflectionClass( ItemPostType::class ) )->newInstanceWithoutConstructor();
			$type->post_type = 'cpl_item';
			$query           = $this->query( [ 'post_type' => 'cpl_item' ] );

			$type->item_variation_query( $query );

			$this->assertSame( 0, $query->get( 'post_parent' ) );
			$this->assertNull( $query->get( 'cpl_limit_child_visibility' ) );
		}

		public function test_shortcode_without_an_id_skips_password_protected_items() {
			$seen = null;

			Functions\when( 'get_posts' )->alias(
				static function ( $args ) use ( &$seen ) {
					$seen = $args;

					return [];
				}
			);

			$shortcode = ( new ReflectionClass( Shortcode::class ) )->newInstanceWithoutConstructor();

			$this->assertSame( 'No Messages found.', $shortcode->render_item( [ 'id' => 'false' ] ) );
			$this->assertArrayHasKey( 'has_password', $seen );
			$this->assertFalse( $seen['has_password'] );
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
		 * @param array $vars
		 * @return object
		 */
		private function query( $vars ) {
			return new class( $vars ) {
				/** @var array */
				public $vars;

				public function __construct( $vars ) {
					$this->vars = $vars;
				}

				public function get( $key ) {
					return array_key_exists( $key, $this->vars ) ? $this->vars[ $key ] : null;
				}

				public function set( $key, $value ) {
					$this->vars[ $key ] = $value;
				}
			};
		}

		/**
		 * @param array $posts
		 * @return array
		 */
		private function idsOf( $posts ) {
			return array_map(
				static function ( $post ) {
					return $post->ID;
				},
				$posts
			);
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
