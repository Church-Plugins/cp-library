<?php

namespace CP_Library\Setup\Blocks;

use Exception;

abstract class Block {
  /**
   * The directory for the block's bundled resources
   * 
   * @var string
   */
  protected $block_dir;

  /**
   * The URL of the block's bundled resources
   * 
   * @var string
   */
  protected $block_url;

  /**
   * The block namespace.
   * 
   * @var string
   */
  protected $_namespace = 'cp-library/';

  /**
   * The block name
   * 
   * @var string
   */
  public $name;

  /**
   * The full namespaced block name, e.g. cp-library/spacer
   * 
   * @var string
   */
  public $block_name;

  /**
   * Specifies whether a block is dynamic or not
   * 
   * @var boolean
   */
  public $is_dynamic;

  /**
   * Single instance
   * 
   * @var Block
   */
  protected static $_instance;

  /**
   * Class constructor
   */
  public function __construct() {
    if( !$this->name ) {
      throw new Exception( "Block must have a name" );
    }

    $this->block_name = $this->_namespace . $this->name;
    $this->block_dir = CP_LIBRARY_PLUGIN_DIR . 'dist/blocks/' . $this->name;
    $this->block_url = CP_LIBRARY_PLUGIN_URL . 'dist/blocks/' . $this->name;

    if( ! file_exists( $this->block_dir ) ) {
      throw new Exception( "Invalid block configuration. No build directory found for " . $this->block_name );
    }

    add_action( 'init', [ $this, 'register_block' ] );
  }

  /**
   * Initialize block
   */
  public static function init() {
    $class = get_called_class();
    if( ! self::$_instance instanceof Block ) {
      self::$_instance = new $class();
    }
    return self::$_instance;
  }

  /**
   * Registers a Gutenberg block
   */
  public function register_block() {
    if( $this->is_dynamic ) {
      $block_args = apply_filters( "wp-block-cp-library-{$this->name}_block_args", array( 'render_callback' => [ $this, 'render' ] ) ); 
      register_block_type_from_metadata( $this->block_dir, $block_args );
    }
    else {
      register_block_type( $this->block_dir );
    }
  }

  /** 
   * Gets a block's metadata as JSON
   */
  public function get_metadata() {  
    return wp_json_file_decode( $this->block_dir . '/block.json' );
  }
}