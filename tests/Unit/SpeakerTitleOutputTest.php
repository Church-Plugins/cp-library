<?php
/**
 * Speaker titles are stored as characters, including "&". The single, info,
 * variation, and podcast templates have to escape them on the way out so a
 * name still shows the ampersand, and an ampersand is encoded only once.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Admin\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SpeakerTitleOutputTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__ ) . '/fixtures/speaker-template-stubs.php';
		Settings::$permalinks = false;
		$GLOBALS['post']      = (object) array( 'ID' => 1 );
	}

	public function test_meta_escapes_speaker_names_once() {
		$html = $this->render(
			'templates/parts/item-single/meta.php',
			array( 'item' => $this->item() )
		);

		$this->assertStringContainsString( 'Faith &amp; Works, O&#039;Brien', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	public function test_info_escapes_speaker_names_once() {
		$html = $this->render(
			'templates/parts/item-single/info.php',
			array( 'item' => $this->item() )
		);

		$this->assertStringContainsString( 'Faith &amp; Works, O&#039;Brien', $html );
		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	public function test_info_permalink_escapes_the_speaker_name_inside_the_link() {
		Settings::$permalinks = true;

		$html = $this->render(
			'templates/parts/item-single/info.php',
			array( 'item' => $this->item() )
		);

		$this->assertStringContainsString(
			'<a href="https://example.test/speaker/9">Faith &amp; Works</a>',
			$html
		);
		$this->assertStringContainsString( 'O&#039;Brien', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	public function test_variation_widget_escapes_speaker_names_once() {
		$html = $this->render(
			'templates/widgets/item-single--alt.php',
			array(
				'item'    => $this->item(),
				'details' => true,
				'player'  => false,
			)
		);

		$this->assertStringContainsString( 'Speaker: Faith &amp; Works, O&#039;Brien', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	public function test_podcast_item_escapes_speaker_names_for_the_feed() {
		$html = $this->render( 'templates/parts/podcast-item.php', array() );

		$this->assertStringContainsString( '<dc:creator>Faith &amp; Works, O&#039;Brien</dc:creator>', $html );
		$this->assertStringContainsString( '<itunes:author>Faith &amp; Works, O&#039;Brien</itunes:author>', $html );
		$this->assertStringContainsString( '<googleplay:author>Faith &amp; Works, O&#039;Brien</googleplay:author>', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	/**
	 * @param string $relative
	 * @param array  $args
	 * @return string
	 */
	private function render( $relative, array $args ) {
		ob_start();
		include dirname( __DIR__, 2 ) . '/' . $relative;
		return ob_get_clean();
	}

	/**
	 * @return array
	 */
	private function item() {
		return array(
			'originID' => 5,
			'speakers' => array(
				array(
					'title'     => 'Faith & Works',
					'origin_id' => 9,
				),
				array(
					'title'     => "O'Brien",
					'origin_id' => 10,
				),
			),
		);
	}
}
