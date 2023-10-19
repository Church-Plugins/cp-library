<?php
/**
 * Latest Series - Grid View
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Series - Grid View', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'cpl_item_type' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":0,"query":{"perPage":"6","pages":0,"offset":0,"postType":"cpl_item_type","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"displayLayout":{"type":"flex","columns":2},"layout":{"type":"constrained"}} -->
<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
<!-- wp:group {"style":{"spacing":{"padding":{"left":"var:preset|spacing|50","right":"var:preset|spacing|50","top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}},"color":{"background":"#f0f0f0"},"border":{"radius":"8px"},"dimensions":{"minHeight":"100%"}},"layout":{"inherit":true,"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:8px;background-color:#f0f0f0;min-height:100%;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:cp-library/item-graphic {"isLink":true,"aspectRatio":"16/9"} -->
<div class="wp-block-cp-library-item-graphic"></div>
<!-- /wp:cp-library/item-graphic -->

<!-- wp:cp-library/item-title {"level":3,"isLink":true,"style":{"layout":{"selfStretch":"fit","flexSize":null},"spacing":{"margin":{"top":"var:preset|spacing|40"}}},"fontSize":"medium"} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|40","margin":{"top":"var:preset|spacing|40"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/item-date {"textAlign":"left","fontSize":"small"} /-->

<!-- wp:cp-library/sermon-scripture {"fontSize":"small"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:cp-library/sermon-template --></div>
<!-- /wp:cp-library/query -->',
);
