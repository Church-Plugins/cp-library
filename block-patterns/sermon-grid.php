<?php
/**
 * Sermon Grid block pattern
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Sermon Grid', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'posts' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":2,"query":{"perPage":"6","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"displayLayout":{"type":"flex","columns":2}} -->
	<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"16px","bottom":"16px","left":"16px","right":"16px"}},"color":{"background":"#f0f0f0"},"dimensions":{"minHeight":"100%"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group has-background" style="background-color:#f0f0f0;min-height:100%;padding-top:16px;padding-right:16px;padding-bottom:16px;padding-left:16px"><!-- wp:cp-library/item-graphic {"aspectRatio":"16/9","style":{"color":{}}} -->
	<div class="wp-block-cp-library-item-graphic"></div>
	<!-- /wp:cp-library/item-graphic -->
	
	<!-- wp:cp-library/item-title {"level":3,"style":{"spacing":{"margin":{"top":"16px"}}},"fontSize":"medium"} /-->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"16px","margin":{"top":"16px","bottom":"0px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group" style="margin-top:16px;margin-bottom:0px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-speaker {"style":{"spacing":{"margin":{"top":"0px"},"padding":{"top":"6px","bottom":"6px"}}}} /-->
	
	<!-- wp:cp-library/sermon-series {"style":{"spacing":{"padding":{"top":"6px","bottom":"6px"}}}} /--></div>
	<!-- /wp:group -->
	
	<!-- wp:cp-library/sermon-actions {"style":{"spacing":{"margin":{"top":"16px"}}}} /--></div>
	<!-- /wp:group -->
	<!-- /wp:cp-library/sermon-template --></div>
	<!-- /wp:cp-library/query -->',
);
