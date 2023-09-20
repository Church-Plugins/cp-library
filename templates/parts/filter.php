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

$is_item_archive = is_post_type_archive( cp_library()->setup->post_types->item->post_type );
$display = apply_filters( 'cpl_filters_display', $display );
?>
<div class="cpl-filter">

	<form method="get" class="cpl-filter--form">

		<div class="cpl-filter--toggle">
			<a href="#" class="cpl-filter--toggle--button cpl-button"><span><?php esc_html_e( 'Filter', 'cp-library' ); ?></span> <?php echo Helpers::get_icon( 'filter' ); ?></a>
		</div>

		<?php foreach ( $taxonomies as $facet ) : ?>
			<div class="cpl-filter--<?php echo esc_attr( $facet->taxonomy ); ?> cpl-filter--has-dropdown" <?php echo $display; // phpcs:ignore ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo esc_html( $facet->plural_label ); ?></a>
				<div class="cpl-filter--dropdown cpl-ajax-facet" data-facet-type="<?php echo esc_attr( $facet->taxonomy ); ?>"></div>
			</div>
		<?php endforeach; ?>

		<?php if ( $is_item_archive && cp_library()->setup->post_types->speaker_enabled() ) : ?>
			<div class="cpl-filter--speaker cpl-filter--has-dropdown" <?php echo $display; // phpcs:ignore ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo esc_html( cp_library()->setup->post_types->speaker->plural_label ); ?></a>
				<div class="cpl-filter--dropdown cpl-ajax-facet" data-facet-type="speaker"></div>
			</div>
		<?php endif; ?>

		<?php if ( $is_item_archive && cp_library()->setup->post_types->service_type_enabled() ) : ?>
			<div class="cpl-filter--service_type cpl-filter--has-dropdown" <?php echo $display; // phpcs:ignore ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo esc_html( cp_library()->setup->post_types->service_type->plural_label ); ?></a>
				<div class="cpl-filter--dropdown cpl-ajax-facet" data-facet-type="service_type"></div>
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
