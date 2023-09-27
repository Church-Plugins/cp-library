<?php
/**
 * Latest Sermon block pattern
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Sermon', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'posts' ),
	'content'    => '<!-- wp:cp-library/query {"queryId":0,"query":{"perPage":"1","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"displayLayout":{"type":"list","columns":3}} -->
	<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
	<!-- wp:group {"style":{"spacing":{"blockGap":"2rem"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"left"}} -->
	<div class="wp-block-group"><!-- wp:cp-library/item-graphic {"width":"50%","style":{"color":{},"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"radius":"0%","width":"0px","style":"none"},"layout":{"selfStretch":"fit","flexSize":null}}} -->
	<div class="wp-block-cp-library-item-graphic" style="margin-top:0;margin-right:0;margin-bottom:0;margin-left:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"></div>
	<!-- /wp:cp-library/item-graphic -->
	
	<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group"><!-- wp:cp-library/item-title {"isLink":true,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}},"layout":{"selfStretch":"fit","flexSize":null}}} /-->
	
	<!-- wp:cp-library/item-description /-->
	
	<!-- wp:cp-library/sermon-actions /-->
	
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group"><!-- wp:cp-library/sermon-series /-->
	
	<!-- wp:cp-library/sermon-speaker /--></div>
	<!-- /wp:group --></div>
	<!-- /wp:group --></div>
	<!-- /wp:group -->
	<!-- /wp:cp-library/sermon-template --></div>
	<!-- /wp:cp-library/query -->',
);
