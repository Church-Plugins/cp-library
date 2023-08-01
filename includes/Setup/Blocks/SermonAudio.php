<?php

namespace CP_Library\Setup\Blocks;

class SermonAudio extends Block {
    public $name = 'sermon-audio';
    public $is_dynamic = true;

    public function __construct() {
      parent::__construct();
    }
    /**
     * Renders the `cp-library/sermon-audio` block on the server.
     *
     * @param array    $attributes Block attributes.
     * @param string   $content    Block default content.
     * @param \WP_Block $block      Block instance.
     * @return string Returns the HTML for the sermon audio button.
     */
    public function render( $attributes, $content, $block ) {
      $wrapper_attributes = get_block_wrapper_attributes( array(
        'class' => 'cpl-button cpl-button--outlined is-outlined cpl-button--rectangle'
      ) );

      $icon = '<i data-feather="volume-2"></i>';
      $block_content = '<span>' . esc_html__( 'Listen', 'cp-library' ) . '</span>';

      return sprintf(
        '<button %1$s>%2$s%3$s</button>',
        $wrapper_attributes,
        $icon,
        $block_content
      );
    }
}