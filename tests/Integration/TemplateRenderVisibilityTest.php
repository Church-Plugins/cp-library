<?php
/**
 * The [cpl_template] shortcode only prints template posts the viewer may see.
 *
 * It used to render any post id, so a contributor could put
 * [cpl_template id=N] in a draft, preview it, and read a private or
 * password-protected post or page. A draft template still renders for a user
 * who can edit it, which the editor preview relies on.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Setup\PostTypes\Template;

/**
 * @covers \CP_Library\Setup\PostTypes\Template::render_content
 * @covers \CP_Library\Setup\PostTypes\Template::template_is_renderable
 */
class TemplateRenderVisibilityTest extends TestCase {

	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_private_post_is_not_rendered_for_a_contributor() {
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'private',
				'post_content' => 'Private board minutes',
			)
		);
		$this->set_role( 'contributor' );

		$output = do_shortcode( '[cpl_template id=' . $post_id . ']' );

		$this->assertStringNotContainsString( 'Private board minutes', $output );
		$this->assertStringContainsString( 'Template not found', $output );
	}

	public function test_password_protected_page_is_not_rendered() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_password' => 'secret',
				'post_content'  => 'Members only',
			)
		);

		$this->assertStringNotContainsString( 'Members only', Template::render_content( $page_id ) );
	}

	public function test_published_template_renders_for_a_visitor() {
		$template_id = $this->make_template( 'publish', 'Welcome block' );

		$this->assertStringContainsString( 'Welcome block', do_shortcode( '[cpl_template id=' . $template_id . ']' ) );
	}

	public function test_draft_template_is_hidden_from_a_visitor() {
		$template_id = $this->make_template( 'draft', 'Unfinished layout' );

		$this->assertStringNotContainsString( 'Unfinished layout', Template::render_content( $template_id ) );
	}

	public function test_draft_template_renders_for_an_editor() {
		$template_id = $this->make_template( 'draft', 'Unfinished layout' );
		$this->set_role( 'editor' );

		$this->assertStringContainsString( 'Unfinished layout', Template::render_content( $template_id ) );
	}

	/**
	 * @param string $status  Post status.
	 * @param string $content Post content.
	 * @return int
	 */
	private function make_template( $status, $content ) {
		return self::factory()->post->create(
			array(
				'post_type'    => CP_LIBRARY_UPREFIX . '_template',
				'post_status'  => $status,
				'post_content' => $content,
			)
		);
	}

	/**
	 * @param string $role Role name.
	 */
	private function set_role( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}
}
