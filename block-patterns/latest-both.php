<?php
/**
 * Latest Sermon + Series block pattern
 *
 * @package CP_Library
 */

return array(
	'title'      => esc_html__( 'Latest Sermon + Series', 'cp-library' ),
	'blockTypes' => array( 'cp-library/query' ),
	'categories' => array( 'cpl_item', 'cpl_item_type' ),
	'content'    => '<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"},"color":{"background":"#f9f9f9"}}} -->
<div class="wp-block-column has-background" style="background-color:#f9f9f9;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"24px"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<h3 class="wp-block-heading" style="margin-top:0;margin-bottom:24px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;text-transform:uppercase">Latest Sermon</h3>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"inherit":true,"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/query {"queryId":1,"query":{"perPage":"1","pages":0,"offset":0,"postType":"cpl_item","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"layout":{"type":"constrained","justifyContent":"center"}} -->
<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
<!-- wp:cp-library/item-graphic {"aspectRatio":"16/9"} -->
<div class="wp-block-cp-library-item-graphic"></div>
<!-- /wp:cp-library/item-graphic -->

<!-- wp:cp-library/item-title {"level":3,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"bottom":"0","left":"0","right":"0","top":"var:preset|spacing|40"}}}} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0"},"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
<div class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-speaker {"style":{"spacing":{"padding":{"left":"var:preset|spacing|30","right":"var:preset|spacing|30","top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"small"} /-->

<!-- wp:cp-library/sermon-topics {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"small"} /-->

<!-- wp:cp-library/sermon-scripture {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"0px"}}},"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:cp-library/sermon-actions {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"var:preset|spacing|50","bottom":"0","left":"0","right":"0"}}}} /-->
<!-- /wp:cp-library/sermon-template --></div>
<!-- /wp:cp-library/query --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"color":{"background":"#313e48"}},"textColor":"white"} -->
<div class="wp-block-column has-white-color has-text-color has-background" style="background-color:#313e48;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"right":"0","left":"0","top":"0","bottom":"24px"}},"typography":{"textTransform":"uppercase"}},"textColor":"white"} -->
<h3 class="wp-block-heading has-white-color has-text-color" style="margin-top:0;margin-right:0;margin-bottom:24px;margin-left:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;text-transform:uppercase">Latest Series</h3>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"inherit":true,"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/query {"queryId":1,"query":{"perPage":"1","pages":0,"offset":0,"postType":"cpl_item_type","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"include":[],"sticky":"","inherit":false,"taxQuery":null,"cpl_speakers":null,"cpl_service_types":null,"parents":[]},"layout":{"type":"constrained"}} -->
<div class="wp-block-cp-library-query"><!-- wp:cp-library/sermon-template -->
<!-- wp:cp-library/item-graphic {"aspectRatio":"16/9"} -->
<div class="wp-block-cp-library-item-graphic"></div>
<!-- /wp:cp-library/item-graphic -->

<!-- wp:cp-library/item-title {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"0","left":"0","right":"0"}}},"textColor":"ast-global-color-4"} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:cp-library/sermon-scripture {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"0","left":"0","right":"0"},"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"color":"#535353","width":"1px"},"elements":{"link":{"color":{"text":"var:preset|color|ast-global-color-4"}}}},"textColor":"ast-global-color-4","fontSize":"small"} /--></div>
<!-- /wp:group -->
<!-- /wp:cp-library/sermon-template --></div>
<!-- /wp:cp-library/query --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->',
);
