<?php

namespace CP_Library\Setup\Blocks;
use CP_Library\Setup\Blocks\Block;

class Query extends Block {
    public $name = 'query';
    public $is_dynamic = false;

    public function __construct() {
      parent::__construct();
    }
}