<?php
/**
 * Tests for which posts a template render will output.
 *
 * Template::render_content() used to render whatever post id it was given.
 * The [cpl_template] shortcode, the template block, the page builder modules
 * and the editor preview (any user with edit_posts) all call it, so a
 * contributor could print the content of a private, draft or
 * password-protected post or page by id. Only template posts render now, and
 * a template that is not published, or has a password, renders only for a
 * user who can edit it.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Setup\PostTypes\Template;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Setup\PostTypes\Template::template_is_renderable
 */
class TemplateRenderVisibilityTest extends TestCase {

	/** @var bool */
	private $can_edit = false;

	/** @var bool */
	private $password_required = false;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'CP_LIBRARY_UPREFIX' ) ) {
			define( 'CP_LIBRARY_UPREFIX', 'cpl' );
		}

		$this->can_edit          = false;
		$this->password_required = false;

		Functions\when( 'current_user_can' )->alias(
			function ( $cap ) {
				return 'edit_post' === $cap && $this->can_edit;
			}
		);
		Functions\when( 'post_password_required' )->alias(
			function () {
				return $this->password_required;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_published_template_renders_for_anyone() {
		$this->assertTrue( Template::template_is_renderable( $this->post( 'cpl_template', 'publish' ) ) );
	}

	public function test_private_post_of_another_type_does_not_render() {
		$this->assertFalse( Template::template_is_renderable( $this->post( 'post', 'private' ) ) );
	}

	public function test_published_post_of_another_type_does_not_render() {
		$this->assertFalse( Template::template_is_renderable( $this->post( 'page', 'publish' ) ) );
	}

	public function test_other_post_types_do_not_render_even_for_an_editor() {
		$this->can_edit = true;

		$this->assertFalse( Template::template_is_renderable( $this->post( 'post', 'draft' ) ) );
	}

	public function test_draft_template_does_not_render_for_a_user_who_cannot_edit_it() {
		$this->assertFalse( Template::template_is_renderable( $this->post( 'cpl_template', 'draft' ) ) );
	}

	public function test_draft_template_renders_for_a_user_who_can_edit_it() {
		$this->can_edit = true;

		$this->assertTrue( Template::template_is_renderable( $this->post( 'cpl_template', 'draft' ) ) );
	}

	public function test_password_protected_template_does_not_render_for_a_user_who_cannot_edit_it() {
		$this->password_required = true;

		$this->assertFalse( Template::template_is_renderable( $this->post( 'cpl_template', 'publish' ) ) );
	}

	/**
	 * @param string $type   Post type.
	 * @param string $status Post status.
	 * @return object
	 */
	private function post( $type, $status ) {
		return (object) array(
			'ID'          => 42,
			'post_type'   => $type,
			'post_status' => $status,
		);
	}
}
