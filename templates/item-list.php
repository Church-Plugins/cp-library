<?php

$items = [
	[
		'thumb' => 'https://i.vimeocdn.com/video/1239653387?mw=1100&mh=618&q=70',
		'title' => 'For Love or Money',
		'desc'  => 'A brief description for this talk.',
		'date'  => date( 'r', time() - rand(100, 23988) ),
		'category' => [ 'cat 1', 'cat 2' ],
		'video'    => 'https://vimeo.com/embed-redirect/603403673?embedded=true&source=vimeo_logo&owner=11698061',
		'audio'    => 'https://ret.sfo2.cdn.digitaloceanspaces.com/wp-content/uploads/2021/09/re20210915.mp3',
	],
	[
		'thumb' => 'https://i.vimeocdn.com/video/1239653387?mw=1100&mh=618&q=70',
		'title' => 'Out of Love',
		'desc'  => 'A different description for this talk.',
		'date'  => date( 'r', time() - rand(100, 23988) ),
		'category' => [ 'cat 1', 'cat 2' ],
		'video'    => 'https://vimeo.com/embed-redirect/603403673?embedded=true&source=vimeo_logo&owner=11698061',
		'audio'    => 'https://ret.sfo2.cdn.digitaloceanspaces.com/wp-content/uploads/2021/09/re20210915.mp3',
	],
];

?>

<div class="cpl-item-list">

	<?php foreach ( $items as $item ) : ?>

		<div class="cpl-item-list--item">

			<div class="cpl-item-list--item--thumb">
				<?php if ( $item['thumb'] ) : ?>
					<a class="cpl-item-list--item--thumb--img" href="#" style="background-url: url(<?php echo esc_url( $item['thumb'] ); ?>);"></a>
				<?php endif; ?>
			</div>

			<div class="cpl-item-list--item--details">
				<div class="cpl-item-list--item--title"><?php echo $item['title']; ?></div>

				<div class="cpl-item-list--item--desc"><?php echo $item['desc']; ?></div>

				<div class="cpl-item-list--item--meta">
					<div class="cpl-item-list--item--date"><?php echo $item['date']; ?></div>
					<div class="cpl-item-list--item--category"><?php echo implode( ', ', $item['category'] ); ?></div>
				</div>
			</div>

			<div class="cpl-item-list--item--actions">
				<?php if ( $item['video'] ) : ?>
					<div class="cpl-item-list--item--actions--video"></div>
				<?php endif; ?>

				<?php if ( $item['audio'] ) : ?>
					<div class="cpl-item-list--item--actions--audio"></div>
				<?php endif; ?>
			</div>

		</div>

	<?php endforeach; ?>

</div>
