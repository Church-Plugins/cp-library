<?php
/**
 * Filter option titles are read straight from storage and then shown as text.
 *
 * Term names go through WordPress's pre_term_name filter before they are
 * saved. That filter encodes "&" as "&amp;" and does not encode apostrophes
 * or double quotes. The options query returns wp_terms.name as stored, so a
 * topic saved as "Faith & Works" arrives here as "Faith &amp; Works".
 *
 * Speaker titles are copied from the post title. Saving a post only trims
 * that field, so "&", apostrophes, and double quotes are stored as the
 * characters themselves and the options query returns them that way.
 *
 * Term titles are decoded once before they are returned. Speaker titles are
 * left as stored, including a raw ampersand, apostrophe, or double quote,
 * and including the characters "&amp;" if that is what was stored.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Filters\AbstractFacet;
use CP_Library\Filters\Facets\SourceFacet;
use CP_Library\Filters\Facets\TaxonomyFacet;
use PHPUnit\Framework\TestCase;

/**
 * Exposes formatted options without registering a taxonomy.
 */
class ExposableFacet extends TaxonomyFacet {

	public function __construct() {
		// Deliberately does not call parent::__construct().
	}

	public function formatted( array $options ) {
		return $this->decode_term_titles( $this->format_options( $options ) );
	}
}

/**
 * Exposes speaker options, which must not be decoded.
 */
class ExposableSourceFacet extends SourceFacet {

	public function __construct() {
		// Deliberately does not call parent::__construct().
	}

	public function formatted( array $options ) {
		return $this->format_options( $options );
	}
}

/**
 * @covers \CP_Library\Filters\AbstractFacet::decode_option_title
 * @covers \CP_Library\Filters\AbstractFacet::format_options
 */
class FilterOptionTitleTest extends TestCase {

	/**
	 * Titles as the filter options query returns them, and the text to show.
	 *
	 * @return array
	 */
	public function stored_titles() {
		return array(
			'term ampersand'          => array( 'Anxiety &amp; Peace', 'Anxiety & Peace' ),
			'term numeric ampersand'  => array( 'Faith &#038; Works', 'Faith & Works' ),
			'plain apostrophe'        => array( "O'Brien", "O'Brien" ),
			'encoded apostrophe'      => array( 'O&#039;Brien', "O'Brien" ),
			'plain double quote'      => array( 'Say "Hello"', 'Say "Hello"' ),
			'encoded double quote'    => array( 'Say &quot;Hello&quot;', 'Say "Hello"' ),
			'speaker plain ampersand' => array( 'Faith & Works', 'Faith & Works' ),
			'ordinary name'           => array( 'Jane Doe', 'Jane Doe' ),
		);
	}

	/**
	 * @dataProvider stored_titles
	 *
	 * @param string $stored
	 * @param string $shown
	 */
	public function test_decode_option_title( $stored, $shown ) {
		$this->assertSame( $shown, AbstractFacet::decode_option_title( $stored ) );
	}

	/**
	 * @dataProvider stored_titles
	 *
	 * @param string $stored
	 * @param string $shown
	 */
	public function test_formatted_options_decode_titles( $stored, $shown ) {
		$facet   = new ExposableFacet();
		$options = $facet->formatted(
			array(
				(object) array(
					'id'    => 4,
					'value' => 'faith-works',
					'title' => $stored,
					'count' => 2,
				),
			)
		);

		$this->assertSame( $shown, $options[0]->title );
		$this->assertSame( 'faith-works', $options[0]->value );
		$this->assertSame( 2, $options[0]->count );
	}

	/**
	 * Speaker names arrive as characters. Decoding them would change a name
	 * that was stored with an entity written out.
	 */
	public function test_speaker_titles_are_not_decoded() {
		$facet = new ExposableSourceFacet();
		$stored = array(
			'Anxiety & Peace',
			"O'Brien",
			'Say "Hello"',
			'Anxiety &amp; Peace',
		);

		$options = array();
		foreach ( $stored as $index => $title ) {
			$options[] = (object) array(
				'id'    => $index + 1,
				'value' => (string) ( $index + 1 ),
				'title' => $title,
				'count' => 1,
			);
		}

		$formatted = $facet->formatted( $options );

		foreach ( $stored as $index => $title ) {
			$this->assertSame( $title, $formatted[ $index ]->title );
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wordpress_decode_branch_matches() {
		if ( ! function_exists( 'wp_specialchars_decode' ) ) {
			/**
			 * Entity map used by WordPress wp_specialchars_decode() for ENT_QUOTES.
			 *
			 * @param string     $text
			 * @param string|int $quote_style
			 * @return string
			 */
			function wp_specialchars_decode( $text, $quote_style = ENT_NOQUOTES ) {
				unset( $quote_style );

				return strtr(
					(string) $text,
					array(
						'&#039;' => "'",
						'&#x27;' => "'",
						'&quot;' => '"',
						'&#034;' => '"',
						'&amp;'  => '&',
						'&#038;' => '&',
					)
				);
			}
		}

		$this->assertSame( 'Faith & Works', AbstractFacet::decode_option_title( 'Faith &amp; Works' ) );
		$this->assertSame( "O'Brien", AbstractFacet::decode_option_title( 'O&#039;Brien' ) );
		$this->assertSame( 'Say "Hello"', AbstractFacet::decode_option_title( 'Say &quot;Hello&quot;' ) );
		$this->assertSame( 'Faith & Works', AbstractFacet::decode_option_title( 'Faith & Works' ) );
	}
}
