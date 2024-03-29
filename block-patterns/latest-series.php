<?php
/**
 * Latest Series block pattern
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Series', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'cpl_item_type' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":0,"query":{"perPage":"1","pages":0,"offset":0,"postType":"cpl_item_type","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"displayLayout":{"type":"list","columns":3},"layout":{"type":"constrained"}} -->
<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"32px"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:cp-library/item-graphic {"isLink":true,"aspectRatio":"auto"} -->
<div class="wp-block-cp-library-item-graphic"></div>
<!-- /wp:cp-library/item-graphic --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:cp-library/item-title {"level":3,"isLink":true} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|40","margin":{"top":"var:preset|spacing|40"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/item-date {"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:cp-library/item-description {"moreText":"Read More","excerptLength":25,"style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0","top":"var:preset|spacing|40"}}}} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-scripture {"style":{"border":{"color":"#e5e8ef","width":"1px"},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"fontSize":"small"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:cp-library/sermon-template --></div>
<!-- /wp:cp-library/query -->',
);
