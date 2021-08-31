<?php

namespace CP_Library;

class Exception extends \Exception {

	public function __construct( $message = null, $code = 0 ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $message ) {
			error_log( $message );
		}

		parent::__construct( $message, $code );
	}

}
