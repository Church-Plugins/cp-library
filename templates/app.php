<?php
have_posts();
the_post();

$item_id = is_singular( cp_library()->setup->post_types->item->post_type ) ? ' data-item-id="' . get_the_ID() . '"' : '';
?>

<div id="cpl_root" <?php echo $item_id; ?>></div>
