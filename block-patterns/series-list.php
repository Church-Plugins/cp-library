<?php
/**
 * Series List
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Series - List View', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'cpl_item_type' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":0,"query":{"perPage":"10","pages":0,"offset":0,"postType":"cpl_item_type","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"layout":{"type":"constrained"}} -->
	<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"32px","right":"32px"},"blockGap":"32px","margin":{"top":"0","bottom":"32px"}},"color":{"background":"#ebeced6e"},"border":{"radius":"2px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group has-background" style="border-radius:2px;background-color:#ebeced6e;margin-top:0;margin-bottom:32px;padding-top:32px;padding-right:32px;padding-bottom:32px;padding-left:32px"><!-- wp:cp-library/item-graphic {"isLink":true,"aspectRatio":"16/9","width":"200px"} -->
	<div class="wp-block-cp-library-item-graphic"></div>
	<!-- /wp:cp-library/item-graphic -->

	<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/item-title {"level":3,"isLink":true,"style":{"typography":{"fontSize":"1.5rem","fontStyle":"normal","fontWeight":"400","textTransform":"uppercase"}}} /--></div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"16px","margin":{"top":"8px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group" style="margin-top:8px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/item-date {"style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"0px","right":"0px"},"margin":{"bottom":"0","left":"0","right":"0","top":"0px"}}}} /--></div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"0","margin":{"top":"16px","bottom":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group" style="margin-top:16px;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-scripture {"style":{"border":{"color":"#ebeced","width":"1px"},"spacing":{"padding":{"top":"4px","bottom":"4px","left":"8px","right":"8px"}}}} /--></div>
	<!-- /wp:group --></div>
	<!-- /wp:group --></div>
	<!-- /wp:group -->
	<!-- /wp:cp-library/sermon-template --></div>
	<!-- /wp:cp-library/query -->',
);
