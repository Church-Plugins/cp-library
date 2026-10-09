<?php
/**
 * Tests for shortcode parameters copied into the inline script.
 *
 * Attribute text is written into a script block the front end reads back as
 * cplParams. A value can contain a closing script tag, quotes, and newlines,
 * and those have to stay inside the assignment. A key that is not an
 * identifier is left out.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Setup\Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers \CP_Library\Setup\Shortcode::staticScript
 */
class ShortcodeParamsTest extends TestCase {

	const FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

	/**
	 * @param array $args
	 * @return string
	 */
	private function script( $args ) {
		$method = new ReflectionMethod( Shortcode::class, 'staticScript' );
		$method->setAccessible( true );

		return $method->invoke( null, $args );
	}

	/**
	 * The assignment lines, without the script element's closing tag.
	 *
	 * @param string $output
	 * @return string
	 */
	private function assignments( $output ) {
		if ( ! preg_match( '/cplParams = cplParams \|\| \{\};(.*)<\/script>/s', $output, $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * Read one cplParams assignment back to the string a script would see.
	 *
	 * @param string $output
	 * @param string $key
	 * @return string|null
	 */
	private function read_param( $output, $key ) {
		$encoded_key = wp_json_encode( $key, self::FLAGS );
		$pattern     = '/cplParams\[' . preg_quote( $encoded_key, '/' ) . '\] = (.*);/';

		if ( ! preg_match( $pattern, $output, $matches ) ) {
			return null;
		}

		return json_decode( $matches[1] );
	}

	public function test_attribute_value_with_script_quotes_and_newlines_stays_encoded() {
		$value  = "</script><script>alert(1)</script>\nit's \"quoted\"";
		$output = $this->script( [ 'note' => $value ] );
		$assignments = $this->assignments( $output );

		$this->assertStringNotContainsString( '</script', $assignments );
		$this->assertStringNotContainsString( "'", $output );
		$this->assertStringNotContainsString( '"quoted"', $output );
		$this->assertStringContainsString( '\u0022', $assignments );
		$this->assertStringContainsString( '\u0027', $assignments );
		$this->assertStringNotContainsString( "\n'it'", $assignments );
		$this->assertSame( $value, $this->read_param( $output, 'note' ) );
	}

	public function test_non_identifier_key_is_dropped() {
		$output = $this->script(
			[
				'bad key'     => 'dropped-space',
				'</script>'   => 'dropped-tag',
				'9lives'      => 'dropped-digit',
				'service-type' => 'kept-hyphen',
				'_private'    => 'kept-underscore',
			]
		);

		$this->assertNull( $this->read_param( $output, 'bad key' ) );
		$this->assertNull( $this->read_param( $output, '</script>' ) );
		$this->assertNull( $this->read_param( $output, '9lives' ) );
		$assignments = $this->assignments( $output );

		$this->assertStringNotContainsString( 'dropped-space', $output );
		$this->assertStringNotContainsString( 'dropped-tag', $output );
		$this->assertStringNotContainsString( 'dropped-digit', $output );
		$this->assertStringNotContainsString( '</script', $assignments );
		$this->assertSame( 'kept-hyphen', $this->read_param( $output, 'service-type' ) );
		$this->assertSame( 'kept-underscore', $this->read_param( $output, '_private' ) );
	}
}
