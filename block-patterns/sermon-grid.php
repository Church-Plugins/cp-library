<?php
/**
 * Sermon Grid block pattern
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Sermons - Grid View', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'cpl_item' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":2,"query":{"perPage":"6","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":[],"cpl_service_types":null,"parents":[]},"displayLayout":{"type":"flex","columns":2},"layout":{"type":"constrained"}} -->
<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
<!-- wp:group {"style":{"spacing":{"padding":{"left":"24px","right":"24px","top":"24px","bottom":"24px"}},"color":{"background":"#f0f0f0"},"dimensions":{"minHeight":"100%"},"border":{"radius":"8px"}},"layout":{"inherit":true,"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:8px;background-color:#f0f0f0;min-height:100%;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:cp-library/item-graphic {"aspectRatio":"16/9","style":{"color":{}}} -->
<div class="wp-block-cp-library-item-graphic"></div>
<!-- /wp:cp-library/item-graphic -->

<!-- wp:cp-library/item-title {"level":3,"isLink":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}},"fontSize":"medium"} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"16px","margin":{"top":"var:preset|spacing|20","bottom":"0px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--20);margin-bottom:0px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-speaker {"style":{"spacing":{"margin":{"top":"0px"},"padding":{"top":"6px","bottom":"6px"}}},"fontSize":"small"} /-->

<!-- wp:cp-library/sermon-series {"style":{"spacing":{"padding":{"top":"6px","bottom":"6px"}}},"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:cp-library/sermon-actions {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} /--></div>
<!-- /wp:group -->
<!-- /wp:cp-library/sermon-template --></div>
<!-- /wp:cp-library/query -->',
);
