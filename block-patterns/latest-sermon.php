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
	'content'    => '<!-- wp:cp-library/query {"queryId":0,"query":{"perPage":"1","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"displayLayout":{"type":"list","columns":3},"layout":{"type":"constrained"}} -->
	<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
	<!-- wp:columns {"verticalAlignment":null,"style":{"spacing":{"blockGap":{"left":"32px"}}}} -->
	<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
	<div class="wp-block-column is-vertically-aligned-center"><!-- wp:cp-library/item-graphic {"isLink":true,"aspectRatio":"auto","style":{"border":{"radius":"6px"}}} -->
	<div class="wp-block-cp-library-item-graphic"></div>
	<!-- /wp:cp-library/item-graphic --></div>
	<!-- /wp:column -->
	
	<!-- wp:column {"verticalAlignment":"center"} -->
	<div class="wp-block-column is-vertically-aligned-center"><!-- wp:cp-library/item-title {"level":3,"isLink":true} /-->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"16px","margin":{"top":"8px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group" style="margin-top:8px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-speaker /-->
	
	<!-- wp:cp-library/item-date /--></div>
	<!-- /wp:group -->
	
	<!-- wp:cp-library/item-description {"moreText":"Read More","excerptLength":25,"style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0","top":"16px"}}}} /-->
	
	<!-- wp:cp-library/sermon-actions {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"16px","bottom":"0","left":"0","right":"0"}}}} /--></div>
	<!-- /wp:column --></div>
	<!-- /wp:columns -->
	<!-- /wp:cp-library/sermon-template --></div>
	<!-- /wp:cp-library/query -->
	
	<!-- wp:paragraph -->
	<p></p>
	<!-- /wp:paragraph -->',
);
