<?php
use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;


$taxonomies = cp_library()->setup->taxonomies->get_objects();

$uri = explode( '?', $_SERVER['REQUEST_URI'] ?? '?' )[0];
$get = $_GET;
$display = '';

if ( empty( $get ) ) {
	$display = 'style="display: none;"';
}
$dropdown_class = 'cpl-filter--dropdown cpl-ajax-facet';
$is_item_archive = is_post_type_archive( cp_library()->setup->post_types->item->post_type );
$display = apply_filters( 'cpl_filters_display', $display );
$disabled_filters = Settings::get_advanced( 'disable_filters', array() );
?>
<div class="cpl-filter">

	<form method="get" class="cpl-filter--form">

		<div class="cpl-filter--toggle">
			<a href="#" class="cpl-filter--toggle--button cpl-button"><span><?php esc_html_e( 'Filter', 'cp-library' ); ?></span> <?php echo Helpers::get_icon( 'filter' ); ?></a>
		</div>

		<?php foreach ( $taxonomies as $tax ) :
			if ( in_array( $tax->taxonomy, $disabled_filters ) ) {
				continue;
			} ?>
			<div class="cpl-filter--<?php echo esc_attr( $tax->taxonomy ); ?> cpl-filter--has-dropdown" <?php echo $display; // phpcs:ignore ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo esc_html( $tax->single_label ); ?></a>
				<div class="<?php echo $dropdown_class; ?>" data-facet-type="<?php echo esc_attr( $tax->taxonomy ); ?>">
				</div>
			</div>
		<?php endforeach; ?>

		<?php
		if ( $is_item_archive && cp_library()->setup->post_types->speaker_enabled() && ! in_array( 'speaker', $disabled_filters ) ) : ?>
			<div class="cpl-filter--speaker cpl-filter--has-dropdown" <?php echo $display; // phpcs:ignore ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo esc_html( cp_library()->setup->post_types->speaker->single_label ); ?></a>
				<div class="<?php echo $dropdown_class; ?>" data-facet-type="speaker">
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $is_item_archive && cp_library()->setup->post_types->service_type_enabled() && ! in_array( 'service_type', $disabled_filters ) ) : ?>
			<div class="cpl-filter--service_type cpl-filter--has-dropdown" <?php echo $display; // phpcs:ignore ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo esc_html( cp_library()->setup->post_types->service_type->single_label ); ?></a>
				<div class="<?php echo $dropdown_class; ?>" data-facet-type="service-type">
				</div>
			</div>
		<?php endif; ?>

		<div class="cpl-filter--search">
			<div class="cpl-filter--search--box">
				<button type="submit"><span class="material-icons-outlined">search</span></button>
				<input type="text" name="s" value="<?php echo esc_attr( Helpers::get_param( $_GET, 's' ) ); ?>" placeholder="<?php esc_html_e( 'Search', 'cp-library' ); ?>"/>
			</div>
		</div>

	</form>

</div>
