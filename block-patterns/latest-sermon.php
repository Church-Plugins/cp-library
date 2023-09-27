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
	'content'    => '<!-- wp:cp-library/query {"queryId":0,"query":{"perPage":"6","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"displayLayout":{"type":"flex","columns":3}} -->
	<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
	<!-- wp:cp-library/item-title {"isLink":true,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}}} /-->
	
	<!-- wp:cp-library/item-graphic {"aspectRatio":"16/9","style":{"color":{"duotone":"var:preset|duotone|purple-yellow"},"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"radius":"0%","width":"0px","style":"none"}}} -->
	<div class="wp-block-cp-library-item-graphic" style="margin-top:0;margin-right:0;margin-bottom:0;margin-left:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-actions {"align":"center"} /--></div>
	<!-- /wp:cp-library/item-graphic -->
	<!-- /wp:cp-library/sermon-template --></div>
	<!-- /wp:cp-library/query -->',
);
