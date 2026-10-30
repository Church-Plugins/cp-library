<?php
/**
 * Title comparison for SermonAudio match-existing.
 *
 * @package CP_SermonAudio_Match
 */

namespace CP_SermonAudio_Match;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a sermon title into the string we compare.
 *
 * SermonAudio sends fullTitle and, when that is missing or blank, displayTitle.
 * displayTitle is often cut off with a trailing ellipsis. CSV imports and the
 * SermonAudio payload also disagree on curly quotes, dashes, entities, and
 * casing. The steps below are the whole comparison, in this order:
 * decode entities, plain quotes and dashes, strip tags, lowercase, strip
 * punctuation, collapse whitespace, drop a trailing ellipsis.
 */
class TitleNormalizer {

	/**
	 * Title to compare: trimmed fullTitle, or trimmed displayTitle when the
	 * full title is missing or whitespace only.
	 *
	 * This is the same choice SermonAudio::resolve_sermon_title() makes
	 * before the formatted item is filtered. The match filter reads that
	 * already-chosen post_title.
	 *
	 * @param mixed $full_title    SermonAudio fullTitle.
	 * @param mixed $display_title SermonAudio displayTitle.
	 * @return string
	 */
	public static function source_title( $full_title, $display_title ) {
		if ( is_string( $full_title ) && '' !== trim( $full_title ) ) {
			return trim( $full_title );
		}

		if ( is_string( $display_title ) && '' !== trim( $display_title ) ) {
			return trim( $display_title );
		}

		return '';
	}

	/**
	 * Comparison form of a title.
	 *
	 * A non-string is empty. Invalid UTF-8 is empty so a regex warning cannot
	 * turn one bad title into a match.
	 *
	 * @param mixed $title Raw title.
	 * @return string
	 */
	public static function normalize( $title ) {
		if ( ! is_string( $title ) || '' === $title ) {
			return '';
		}

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $title, 'UTF-8' ) ) {
			return '';
		}

		$title = self::decode_entities( $title );
		$title = self::plain_quotes_and_dashes( $title );
		$title = strip_tags( $title );
		$title = self::lowercase( $title );
		$title = self::strip_punctuation( $title );
		$title = self::collapse_whitespace( $title );
		$title = self::drop_trailing_ellipsis( $title );

		return $title;
	}

	/**
	 * Decode entities, including a value that was encoded twice.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private static function decode_entities( $title ) {
		$previous = null;
		$current  = $title;
		$passes   = 0;

		while ( $current !== $previous && $passes < 5 ) {
			$previous = $current;
			$current  = html_entity_decode( $current, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			++$passes;
		}

		return $current;
	}

	/**
	 * Curly quotes become straight quotes. Dashes become a hyphen. A single
	 * ellipsis character becomes three dots so the trailing-ellipsis step
	 * can see it.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private static function plain_quotes_and_dashes( $title ) {
		$plain = array(
			"\u{2018}" => "'",
			"\u{2019}" => "'",
			"\u{201A}" => "'",
			"\u{201B}" => "'",
			"\u{2032}" => "'",
			"\u{201C}" => '"',
			"\u{201D}" => '"',
			"\u{201E}" => '"',
			"\u{201F}" => '"',
			"\u{2033}" => '"',
			"\u{2010}" => '-',
			"\u{2011}" => '-',
			"\u{2012}" => '-',
			"\u{2013}" => '-',
			"\u{2014}" => '-',
			"\u{2015}" => '-',
			"\u{2212}" => '-',
			"\u{2026}" => '...',
		);

		return strtr( $title, $plain );
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	private static function lowercase( $title ) {
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $title, 'UTF-8' );
		}

		return strtolower( $title );
	}

	/**
	 * Remove Unicode punctuation. Letters, numbers, and whitespace stay.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private static function strip_punctuation( $title ) {
		$stripped = preg_replace( '/[^\p{L}\p{N}\s]+/u', '', $title );

		return is_string( $stripped ) ? $stripped : $title;
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	private static function collapse_whitespace( $title ) {
		$collapsed = preg_replace( '/\s+/u', ' ', $title );

		if ( ! is_string( $collapsed ) ) {
			$collapsed = $title;
		}

		return trim( $collapsed );
	}

	/**
	 * Drop a trailing "..." left after the other steps.
	 *
	 * Punctuation stripping already removes periods. This still runs so a
	 * trailing ellipsis that survived (the character itself, if it was not
	 * mapped) does not keep two otherwise identical titles apart.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private static function drop_trailing_ellipsis( $title ) {
		$dropped = preg_replace( '/(?:\.\.\.|…)+$/u', '', $title );

		if ( ! is_string( $dropped ) ) {
			return $title;
		}

		return rtrim( $dropped );
	}
}
