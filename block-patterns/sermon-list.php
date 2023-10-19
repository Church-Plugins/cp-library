<?php
/**
 * Latest Sermons - List View
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Sermons - List View', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'cpl_item' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":0,"query":{"perPage":"10","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"showUpcoming":true,"layout":{"type":"constrained"}} -->
<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|40","margin":{"top":"0","bottom":"var:preset|spacing|40"}},"color":{"background":"#ebeced6e"},"border":{"radius":"2px"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group has-background" style="border-radius:2px;background-color:#ebeced6e;margin-top:0;margin-bottom:var(--wp--preset--spacing--40);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:cp-library/item-graphic {"isLink":true,"aspectRatio":"16/9","width":"17%","style":{"layout":{"selfStretch":"fit","flexSize":null}}} -->
<div class="wp-block-cp-library-item-graphic"></div>
<!-- /wp:cp-library/item-graphic -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"inherit":true,"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/item-title {"level":3,"isLink":true,"fontSize":"medium"} /-->

<!-- wp:cp-library/sermon-actions /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|40","margin":{"top":"var:preset|spacing|20"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-speaker {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"width":"0px","style":"none"}},"fontSize":"small"} /-->

<!-- wp:cp-library/item-date {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|40","margin":{"top":"var:preset|spacing|40","bottom":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-topics {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"small"} /-->

<!-- wp:cp-library/sermon-series {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"small"} /-->

<!-- wp:cp-library/sermon-scripture {"fontSize":"small"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:cp-library/sermon-template --></div>
<!-- /wp:cp-library/query -->',
);
