<?php
/**
 * Normalization used to tell a SermonAudio sermon from one that already exists.
 *
 * CSV imports and SermonAudio disagree on curly quotes, dashes, HTML
 * entities, tags, casing, punctuation, whitespace, and a trailing ellipsis
 * on displayTitle. Two titles that are the same sermon after those are
 * flattened must compare equal. fullTitle is the title when it has any
 * non-whitespace characters; otherwise displayTitle is.
 *
 * @package CP_SermonAudio_Match
 */

namespace CP_SermonAudio_Match\Tests\Unit;

use CP_SermonAudio_Match\TitleNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_SermonAudio_Match\TitleNormalizer
 */
class TitleNormalizerTest extends TestCase {

	public function test_entities_are_decoded_before_tags_are_stripped() {
		$this->assertSame( 'hi', TitleNormalizer::normalize( '&lt;em&gt;Hi&lt;/em&gt;' ) );
		$this->assertSame( 'its fine', TitleNormalizer::normalize( 'It&#8217;s Fine' ) );
		$this->assertSame( 'a b', TitleNormalizer::normalize( 'A &amp;amp; B' ) );
	}

	public function test_curly_quotes_and_dashes_match_plain_ones() {
		$this->assertSame(
			TitleNormalizer::normalize( '"Quoted"' ),
			TitleNormalizer::normalize( "\u{201C}Quoted\u{201D}" )
		);
		$this->assertSame(
			TitleNormalizer::normalize( "It's fine" ),
			TitleNormalizer::normalize( "It\u{2019}s fine" )
		);
		$this->assertSame(
			TitleNormalizer::normalize( 'Well-known' ),
			TitleNormalizer::normalize( "Well\u{2014}known" )
		);
		$this->assertSame(
			TitleNormalizer::normalize( 'Well-known' ),
			TitleNormalizer::normalize( "Well\u{2013}known" )
		);
	}

	public function test_tags_case_punctuation_and_whitespace_are_flattened() {
		$this->assertSame( 'hello', TitleNormalizer::normalize( '<strong>Hello</strong>' ) );
		$this->assertSame( 'hello', TitleNormalizer::normalize( 'HELLO' ) );
		$this->assertSame( 'hello', TitleNormalizer::normalize( 'Hello!' ) );
		$this->assertSame( 'hello world', TitleNormalizer::normalize( "Hello \n\t World" ) );
	}

	public function test_a_trailing_ellipsis_is_dropped() {
		$this->assertSame( 'hello', TitleNormalizer::normalize( 'Hello...' ) );
		$this->assertSame( 'hello', TitleNormalizer::normalize( 'Hello…' ) );
		$this->assertSame( 'hello', TitleNormalizer::normalize( 'Hello ... ' ) );
		$this->assertSame( 'hello world', TitleNormalizer::normalize( 'Hello World...' ) );
		$this->assertNotSame(
			TitleNormalizer::normalize( 'Hello World' ),
			TitleNormalizer::normalize( 'Hello...' )
		);
	}

	public function test_full_title_is_used_when_it_has_characters() {
		$this->assertSame(
			'Example Sermon About Gathering Together, Part 1',
			TitleNormalizer::source_title(
				'Example Sermon About Gathering Together, Part 1',
				'Example Sermon About Gather...'
			)
		);
	}

	public function test_display_title_is_used_when_the_full_title_is_blank() {
		$this->assertSame(
			'Morning Grace',
			TitleNormalizer::source_title( '   ', '  Morning Grace  ' )
		);
		$this->assertSame(
			'Morning Grace',
			TitleNormalizer::source_title( null, 'Morning Grace' )
		);
		$this->assertSame( '', TitleNormalizer::source_title( null, '   ' ) );
		$this->assertSame( '', TitleNormalizer::source_title( null, null ) );
	}

	public function test_a_non_string_normalizes_to_empty() {
		$this->assertSame( '', TitleNormalizer::normalize( null ) );
		$this->assertSame( '', TitleNormalizer::normalize( 12 ) );
	}
}
