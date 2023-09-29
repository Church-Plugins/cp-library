<?php
/**
 * Latest Sermon + Series block pattern
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Sermon + Series', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'posts' ),
	'content'    => '<!-- wp:columns -->
	<div class="wp-block-columns"><!-- wp:column {"style":{"spacing":{"padding":{"top":"0px","bottom":"0px","left":"0px","right":"0px"}}}} -->
	<div class="wp-block-column" style="padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px"><!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"24px"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
	<h3 class="wp-block-heading" style="margin-top:0;margin-bottom:24px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;text-transform:uppercase">Latest Sermon</h3>
	<!-- /wp:heading -->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"32px","right":"32px"}},"border":{"radius":"12px"},"color":{"background":"#f9f9f9"}},"layout":{"inherit":true}} -->
	<div class="wp-block-group has-background" style="border-radius:12px;background-color:#f9f9f9;padding-top:32px;padding-right:32px;padding-bottom:32px;padding-left:32px"><!-- wp:cp-library/query {"queryId":1,"query":{"perPage":"1","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"layout":{"type":"constrained","justifyContent":"center"}} -->
	<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
	<!-- wp:cp-library/item-graphic {"aspectRatio":"16/9"} -->
	<div class="wp-block-cp-library-item-graphic"></div>
	<!-- /wp:cp-library/item-graphic -->
	
	<!-- wp:cp-library/item-title {"level":3,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"bottom":"0","left":"0","right":"0","top":"16px"}}}} /-->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"16px","bottom":"0"},"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
	<div class="wp-block-group" style="margin-top:16px;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-speaker {"style":{"spacing":{"padding":{"left":"8px","right":"8px","top":"4px","bottom":"4px"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"color":"#f0f0f0"}}} /-->
	
	<!-- wp:cp-library/sermon-topics {"style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"8px","right":"8px"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"color":"#f0f0f0"}}} /-->
	
	<!-- wp:cp-library/sermon-scripture {"style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"8px","right":"8px"},"margin":{"top":"0px"}},"border":{"color":"#f0f0f0"}}} /-->
	
	<!-- wp:cp-library/sermon-actions {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"16px","bottom":"0","left":"0","right":"0"}}}} /--></div>
	<!-- /wp:group -->
	<!-- /wp:cp-library/sermon-template --></div>
	<!-- /wp:cp-library/query --></div>
	<!-- /wp:group --></div>
	<!-- /wp:column -->
	
	<!-- wp:column {"style":{"spacing":{"padding":{"top":"0px","bottom":"0px","left":"0px","right":"0px"}}}} -->
	<div class="wp-block-column" style="padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px"><!-- wp:heading {"level":3,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"right":"0","left":"0","top":"0","bottom":"24px"}},"typography":{"textTransform":"uppercase"}}} -->
	<h3 class="wp-block-heading" style="margin-top:0;margin-right:0;margin-bottom:24px;margin-left:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;text-transform:uppercase">Latest Series</h3>
	<!-- /wp:heading -->
	
	<!-- wp:group {"style":{"border":{"radius":"8px"},"spacing":{"padding":{"top":"32px","bottom":"32px","left":"32px","right":"32px"}},"color":{"background":"#313e48"}},"layout":{"inherit":true}} -->
	<div class="wp-block-group has-background" style="border-radius:8px;background-color:#313e48;padding-top:32px;padding-right:32px;padding-bottom:32px;padding-left:32px"><!-- wp:cp-library/query {"queryId":1,"query":{"perPage":"1","pages":0,"offset":0,"postType":"cpl_item_type","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"layout":{"type":"constrained"}} -->
	<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
	<!-- wp:cp-library/item-graphic {"aspectRatio":"16/9"} -->
	<div class="wp-block-cp-library-item-graphic"></div>
	<!-- /wp:cp-library/item-graphic -->
	
	<!-- wp:cp-library/item-title {"level":3,"style":{"spacing":{"margin":{"top":"16px","bottom":"0","left":"0","right":"0"}}},"textColor":"ast-global-color-4"} /-->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-scripture {"style":{"spacing":{"margin":{"top":"16px","bottom":"0","left":"0","right":"0"},"padding":{"top":"4px","bottom":"4px","left":"8px","right":"8px"}},"border":{"color":"#535353","width":"1px"},"elements":{"link":{"color":{"text":"var:preset|color|ast-global-color-4"}}}},"textColor":"ast-global-color-4"} /--></div>
	<!-- /wp:group -->
	<!-- /wp:cp-library/sermon-template --></div>
	<!-- /wp:cp-library/query --></div>
	<!-- /wp:group --></div>
	<!-- /wp:column --></div>
	<!-- /wp:columns -->
	
	<!-- wp:paragraph -->
	<p></p>',
);
