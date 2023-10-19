<?php
/**
 * Latest Sermon block pattern
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Sermon', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'cpl_item' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":0,"query":{"perPage":"1","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"displayLayout":{"type":"list","columns":3},"layout":{"type":"constrained"}} -->
<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"32px"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:cp-library/item-graphic {"aspectRatio":"16/9","overlayColor":"black","dimRatio":20,"style":{"border":{"radius":"6px"}}} -->
<div class="wp-block-cp-library-item-graphic"></div>
<!-- /wp:cp-library/item-graphic --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:cp-library/item-title {"level":3,"isLink":true,"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|40","margin":{"top":"var:preset|spacing|20"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-speaker {"fontSize":"small"} /-->

<!-- wp:cp-library/item-date {"fontSize":"small"} /-->

<!-- wp:cp-library/sermon-series {"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:cp-library/item-description {"moreText":"","showMoreOnNewLine":false,"excerptLength":25,"style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0","top":"var:preset|spacing|40"}}}} /-->

<!-- wp:cp-library/sermon-actions {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:cp-library/sermon-template --></div>
<!-- /wp:cp-library/query -->',
);
