<?php
/**
 * Latest Series - Grid View
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Series - Grid View', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'posts' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":2,"query":{"perPage":"6","pages":0,"offset":0,"postType":"cpl_item_type","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"displayLayout":{"type":"flex","columns":2},"layout":{"type":"constrained"}} -->
	<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
	<!-- wp:group {"style":{"spacing":{"padding":{"left":"24px","right":"24px","top":"24px","bottom":"24px"}},"color":{"background":"#f0f0f0"},"dimensions":{"minHeight":"100%"},"border":{"radius":"8px"}},"layout":{"inherit":true,"type":"constrained"}} -->
	<div class="wp-block-group has-background" style="border-radius:8px;background-color:#f0f0f0;min-height:100%;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:cp-library/item-graphic {"isLink":true,"aspectRatio":"16/9","style":{"color":{}}} -->
	<div class="wp-block-cp-library-item-graphic"></div>
	<!-- /wp:cp-library/item-graphic -->
	
	<!-- wp:cp-library/item-title {"level":3,"isLink":true,"style":{"layout":{"selfStretch":"fit","flexSize":null},"spacing":{"margin":{"top":"16px"}},"typography":{"fontSize":"1.4em"}}} /-->
	
	<!-- wp:cp-library/item-date {"textAlign":"left","style":{"layout":{"selfStretch":"fit","flexSize":null},"spacing":{"padding":{"right":"0","left":"0","top":"8px","bottom":"8px"}}}} /-->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"16px","bottom":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group" style="margin-top:16px;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-scripture {"style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"8px","right":"8px"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"color":"#e5e8ef"}}} /--></div>
	<!-- /wp:group --></div>
	<!-- /wp:group -->
	<!-- /wp:cp-library/sermon-template --></div>
	<!-- /wp:cp-library/query -->',
);
