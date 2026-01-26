<?php
/**
 * Filter System Exception Class
 *
 * Specialized exception class for filter system errors.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

use CP_Library\Exception;

/**
 * FilterException class - Specialized exception for filter system.
 *
 * This class extends the base plugin exception to provide additional
 * functionality for the filter system, including error code handling
 * and integration with the ErrorCodes class.
 *
 * @since 1.6.0
 */
class FilterException extends Exception {

    /**
     * Error code from ErrorCodes class
     *
     * @var string
     */
    protected $error_code = '';

    /**
     * Optional extra data for the error
     *
     * @var array
     */
    protected $error_data = [];

    /**
     * HTTP status code
     * 
     * @var int
     */
    protected $status_code = 400;

    /**
     * Constructor
     *
     * @param string $message    Error message
     * @param string $error_code Error code from ErrorCodes class
     * @param array  $error_data Additional error data
     * @param int    $status_code Optional HTTP status code
     */
    public function __construct($message = '', $error_code = '', $error_data = [], $status_code = null) {
        // Use default message from ErrorCodes if not provided
        if (empty($message) && !empty($error_code)) {
            $message = ErrorCodes::get_message($error_code);
        }

        // Set error code - use general error if not provided
        $this->error_code = !empty($error_code) ? $error_code : ErrorCodes::GENERAL_ERROR;
        
        // Set error data
        $this->error_data = $error_data;
        
        // Set status code - get from ErrorCodes if not provided
        $this->status_code = !is_null($status_code) ? $status_code : ErrorCodes::get_status_code($this->error_code);

        // Log error if in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[FilterException] Code: %s, Message: %s, Data: %s',
                $this->error_code,
                $message,
                json_encode($this->error_data)
            ));
        }

        // Call parent constructor with message and PHP code (0)
        parent::__construct($message, 0);
    }

    /**
     * Get the error code
     *
     * @return string
     */
    public function get_error_code() {
        return $this->error_code;
    }

    /**
     * Get the error data
     *
     * @return array
     */
    public function get_error_data() {
        return $this->error_data;
    }

    /**
     * Get the HTTP status code
     *
     * @return int
     */
    public function get_status_code() {
        return $this->status_code;
    }

    /**
     * Get a formatted error response array suitable for wp_send_json_error()
     *
     * @return array
     */
    public function get_error_response() {
        return ErrorCodes::get_error_response(
            $this->error_code,
            $this->getMessage(),
            $this->error_data
        );
    }

    /**
     * Send a JSON error response and terminate execution
     */
    public function send_json_error() {
        wp_send_json_error($this->get_error_response());
    }
}