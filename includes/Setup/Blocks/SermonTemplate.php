<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Registering and server-side rendering of the `cp-library/sermon-template` block.
 *
 * @package CP_Library
 */

namespace CP_Library\Setup\Blocks;

use CP_Library\Controllers\Item;
use CP_Library\Controllers\ItemType;
use CP_Library\Setup\Blocks\Block;
use CP_Library\Templates;
use WP_Query;
use WP_Block;

/**
 * Sermon Template class
 *
 * @since 1.3.0
 */
class SermonTemplate extends Block {

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->name       = 'sermon-template';
		$this->is_dynamic = true;

		parent::__construct();

		add_filter( "wp-block-cp-library-{$this->name}_block_args", array( $this, 'block_args' ) );
		add_filter( 'query_loop_block_query_vars', array( $this, 'custom_query_args' ), 10, 3 );
	}

	/**
	 * Renders the `cp-library/sermon-template` block on the server.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block default content.
	 * @param WP_Block $block      Block instance.
	 *
	 * @return string Returns the output of the query, structured using the layout defined by the block's inner blocks.
	 */
	public function render( $attributes, $content, $block ) {
		$page_key = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
		$page     = empty( $_GET[ $page_key ] ) ? 1 : (int) $_GET[ $page_key ];

		// Use global query if needed.
		$use_global_query = ( isset( $block->context['query']['inherit'] ) && $block->context['query']['inherit'] );
		if ( $use_global_query ) {
			global $wp_query;
			/** @var WP_Query $query */
			$query = clone $wp_query;
		} else {
			$query_args = build_query_vars_from_query_block( $block, $page );
			/** @var WP_Query $query */
			$query = new WP_Query( $query_args );
		}

		if ( ! $query->have_posts() ) {
			return '';
		}

		if ( $this->uses_featured_image( $block->inner_blocks ) ) {
			update_post_thumbnail_cache( $query );
		}

		$classnames = '';
		if ( isset( $block->context['displayLayout'] ) && isset( $block->context['query'] ) ) {
			if ( isset( $block->context['displayLayout']['type'] ) && 'flex' === $block->context['displayLayout']['type'] ) {
				$classnames = "is-flex-container columns-{$block->context['displayLayout']['columns']}";
			}
		}
		if ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) {
			$classnames .= ' has-link-color';
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => trim( $classnames ) ) );

		$content = '';
		while ( $query->have_posts() ) {
			$query->the_post();

			// Get an instance of the current Post Template block.
			$block_instance = $block->parsed_block;

			// Set the block name to one that does not correspond to an existing registered block.
			// This ensures that for the inner instances of the Post Template block, we do not render any block supports.
			$block_instance['blockName'] = 'core/null';

			// Get the CP Library model for the current post.
			switch ( $query->post->post_type ) {
				case cp_library()->setup->post_types->item->post_type:
					$item_class = Item::class;
					break;
				case cp_library()->setup->post_types->item_type->post_type:
					$item_class = ItemType::class;
					break;
				default:
					throw new \ChurchPlugins\Exception( 'Invalid post type for CP Library Sermon Template block.' );
			}

			try {
				$item = new $item_class( $query->post->ID, true );
				$item = $item->get_api_data();
			} catch ( \ChurchPlugins\Exception | \Exception $e ) {
				continue;
			}

			// Render the inner blocks of the Post Template block with `dynamic` set to `false` to prevent calling
			// `render_callback` and ensure that no wrapper markup is included.
			$block_content = (
				new WP_Block(
					$block_instance,
					array(
						'postType'        => get_post_type(),
						'postId'          => get_the_ID(),
						'item'            => $item,
					)
				)
			)->render( array( 'dynamic' => false ) );

			// Wrap the render inner blocks in a `li` element with the appropriate post classes.
			$post_classes = implode( ' ', get_post_class( 'wp-block-post' ) );
			$content     .= '<li class="' . esc_attr( $post_classes ) . '">' . $block_content . '</li>';
		}

		/*
		* Use this function to restore the context of the template tags
		* from a secondary query loop back to the main query loop.
		* Since we use two custom loops, it's safest to always restore.
		*/
		wp_reset_postdata();

		$output = '';

		if ( isset( $block->context['showFilters'] ) && $block->context['showFilters'] ) {
			ob_start();
			Templates::get_template_part( 'parts/filter' );
			$output .= ob_get_clean();
		}

		$output .= sprintf( '<ul %1$s>%2$s</ul>', $wrapper_attributes, $content );

		return $output;
	}

	/**
	 * Determines whether a block list contains a block that uses the featured image.
	 *
	 * @param WP_Block_List $inner_blocks Inner block instance.
	 *
	 * @return bool Whether the block list contains a block that uses the featured image.
	 */
	public function uses_featured_image( $inner_blocks ) {
		foreach ( $inner_blocks as $block ) {
			if ( 'core/post-featured-image' === $block->name ) {
				return true;
			}
			if (
				'core/cover' === $block->name &&
				! empty( $block->attributes['useFeaturedImage'] )
			) {
				return true;
			}
			if ( $block->inner_blocks && $this->uses_featured_image( $block->inner_blocks ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns custom block args
	 *
	 * @param array $args existing arguments for registering a block type.
	 * @return array the updated block arguments.
	 */
	public function block_args( $args ) {
		return array_merge( $args, array( 'skip_inner_blocks' => true ) );
	}

	/**
	 * Adds custom query args to the query loop block
	 *
	 * @param array     $query the existing query args.
	 * @param \WP_Block $block the block instance.
	 * @param int       $page the page.
	 * @return array the updated query args.
	 */
	public function custom_query_args( $query, $block, $page ) {
		if ( 'cp-library/sermon-template' !== $block->name ) {
			return $query;
		}

		$include = isset( $block->context['query']['include'] ) ? $block->context['query']['include'] : null;

		if ( $include && is_array( $include ) && count( $include ) > 0 ) {
			$query['post__in'] = $include;
		}

		if ( isset( $block->context['showUpcoming'] ) && false === $block->context['showUpcoming'] && cp_library()->setup->post_types->item->post_type === $query['post_type'] ) {
			$query['cpl_hide_upcoming'] = true;
		}

		if ( isset( $block->context['query']['cpl_speakers'] ) ) {
			$query['cpl_speakers'] = $block->context['query']['cpl_speakers'];
		}

		if ( isset( $block->context['query']['cpl_service_types'] ) ) {
			$query['cpl_service_types'] = $block->context['query']['cpl_service_types'];
		}

		return $query;
	}
}
