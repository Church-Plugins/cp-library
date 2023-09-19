<?php
use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;

$taxonomies = cp_library()->setup->taxonomies->get_objects();
$uri = explode( '?', $_SERVER['REQUEST_URI'] )[0];
$get = $_GET;
$display = '';

if ( empty( $get ) ) {
	$display = 'style="display: none;"';
}

$is_item_archive = is_post_type_archive( cp_library()->setup->post_types->item->post_type );
$display = apply_filters( 'cpl_filters_display', $display );
?>
<div class="cpl-filter">

	<form method="get" class="cpl-filter--form">

		<div class="cpl-filter--toggle">
			<a href="#" class="cpl-filter--toggle--button cpl-button"><span><?php _e( 'Filter', 'cp-library' ); ?></span> <?php echo Helpers::get_icon( 'filter' ); ?></a>
		</div>

		<?php
		foreach ( $taxonomies as $tax ) : ?>
			<div class="cpl-ajax-facet" data-facet-type="<?php echo esc_attr( $tax->taxonomy ); ?>" data-label="<?php echo esc_attr( $tax->plural_label ); ?>" <?php echo $display; ?>></div>
		<?php endforeach; ?>

		<?php if ( $is_item_archive && cp_library()->setup->post_types->speaker_enabled() ) : ?>
			<div class="cpl-ajax-facet" data-facet-type="speaker" data-label="<?php echo esc_attr( cp_library()->setup->post_types->speaker->plural_label ); ?>"></div>
		<?php endif; ?>

		<?php if ( $is_item_archive && cp_library()->setup->post_types->service_type_enabled() ) : ?>
			<div class="cpl-ajax-facet" data-facet-type="service_type" data-label="<?php echo esc_attr( cp_library()->setup->post_types->service_type->plural_label ); ?>"></div>
		<?php endif; ?>

		<div class="cpl-filter--search">
			<div class="cpl-filter--search--box">
				<button type="submit"><span class="material-icons-outlined">search</span></button>
				<input type="text" name="s" value="<?php echo Helpers::get_param( $_GET, 's' ); ?>" placeholder="<?php _e( 'Search', 'cp-library' ); ?>"/>
			</div>
		</div>

	</form>
</div>
