<?php
use ChurchPlugins\Helpers;

$taxonomies = cp_library()->setup->taxonomies->get_objects();
$uri = explode( '?', $_SERVER['REQUEST_URI'] )[0];
$get = $_GET;
?>
<div class="cpl-filter--filters">
	<?php if ( ! empty( $_GET['s'] ) ) : unset( $get[ 's' ] ); ?>
		<a href="<?php echo esc_url( add_query_arg( $get, $uri ) ); ?>" class="cpl-filter--filters--filter"><?php echo __( 'Search:' ) . ' ' . Helpers::get_request('s' ); ?></a>
	<?php endif; ?>

	<?php foreach ( $taxonomies as $tax ) : if ( empty( $_GET[ $tax->taxonomy ] ) ) continue; ?>
		<?php foreach( $_GET[ $tax->taxonomy ] as $slug ) :
			if ( ! $term = get_term_by( 'slug', $slug, $tax->taxonomy ) ) {
				continue;
			}

			$get = $_GET;
			unset( $get[ $tax->taxonomy ][ array_search( $slug, $get[ $tax->taxonomy ] ) ] );
			?>
			<a href="<?php echo esc_url( add_query_arg( $get, $uri ) ); ?>" class="cpl-filter--filters--filter"><?php echo $term->name; ?></a>
		<?php endforeach; ?>
	<?php endforeach; ?>

	<?php if ( ! empty( $_GET[ 'speaker' ] ) ) : ?>
		<?php foreach( $_GET[ 'speaker' ] as $id ) :
			try {
				$value = \CP_Library\Models\Speaker::get_instance( $id );
			} catch ( \Exception $e ) {
				continue;
			}
			$get = $_GET;
			unset( $get[ 'speaker' ][ array_search( $id, $get[ 'speaker' ] ) ] );
			?>
			<a href="<?php echo esc_url( add_query_arg( $get, $uri ) ); ?>" class="cpl-filter--filters--filter"><?php echo $value->title; ?></a>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( ! empty( $_GET[ 'service-type' ] ) ) : ?>
		<?php foreach( $_GET[ 'service-type' ] as $id ) :
			try {
				$value = \CP_Library\Models\ServiceType::get_instance( $id );
			} catch ( \Exception $e ) {
				continue;
			}

			$get = $_GET;
			unset( $get[ 'service-type' ][ array_search( $id, $get[ 'service-type' ] ) ] );
			?>
			<a href="<?php echo esc_url( add_query_arg( $get, $uri ) ); ?>" class="cpl-filter--filters--filter"><?php echo $value->title; ?></a>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
