/**
 * CP Library Filter Error Handler
 * 
 * Provides client-side error handling for the filter system.
 */

/**
 * Error codes that match server-side ErrorCodes class
 */
export const ERROR_CODES = {
  // General errors
  GENERAL_ERROR: 'general_error',
  INVALID_REQUEST: 'invalid_request',
  MISSING_PARAMETER: 'missing_parameter',
  INVALID_PARAMETER: 'invalid_parameter',
  UNAUTHORIZED: 'unauthorized',
  FORBIDDEN: 'forbidden',
  NOT_FOUND: 'not_found',
  INTERNAL_ERROR: 'internal_error',
  
  // Filter manager errors
  MANAGER_NOT_FOUND: 'filter_manager_not_found',
  INVALID_POST_TYPE: 'invalid_post_type',
  FILTER_REGISTRATION: 'filter_registration_error',
  
  // Facet errors
  FACET_NOT_FOUND: 'facet_not_found',
  INVALID_FACET: 'invalid_facet',
  FACET_INCOMPATIBLE: 'incompatible_facet',
  
  // AJAX errors
  INVALID_AJAX_ACTION: 'invalid_ajax_action',
  AJAX_RESPONSE_ERROR: 'ajax_response_error',
  AJAX_REQUEST_ERROR: 'ajax_request_error',
  
  // Query errors
  QUERY_ERROR: 'query_error',
  INVALID_QUERY_VAR: 'invalid_query_var',
  
  // Validation errors
  VALIDATION_ERROR: 'validation_error',
  INVALID_CONTEXT: 'invalid_context',
  
  // Cache errors
  CACHE_ERROR: 'cache_error',
  
  // Client-specific errors
  NETWORK_ERROR: 'network_error',
  TIMEOUT_ERROR: 'timeout_error',
  PARSE_ERROR: 'parse_error',
};

/**
 * Default user-friendly error messages
 */
const DEFAULT_MESSAGES = {
  [ERROR_CODES.GENERAL_ERROR]: 'An error occurred. Please try again.',
  [ERROR_CODES.INVALID_REQUEST]: 'Invalid request. Please try again.',
  [ERROR_CODES.MISSING_PARAMETER]: 'Missing required information.',
  [ERROR_CODES.INVALID_PARAMETER]: 'Invalid information provided.',
  [ERROR_CODES.UNAUTHORIZED]: 'You are not authorized to perform this action.',
  [ERROR_CODES.FORBIDDEN]: 'Access to this resource is forbidden.',
  [ERROR_CODES.NOT_FOUND]: 'The requested resource was not found.',
  [ERROR_CODES.INTERNAL_ERROR]: 'An internal server error occurred. Please try again later.',
  
  [ERROR_CODES.MANAGER_NOT_FOUND]: 'Filter system configuration error.',
  [ERROR_CODES.INVALID_POST_TYPE]: 'Invalid content type for this filter.',
  [ERROR_CODES.FILTER_REGISTRATION]: 'Filter configuration error.',
  
  [ERROR_CODES.FACET_NOT_FOUND]: 'The requested filter option does not exist.',
  [ERROR_CODES.INVALID_FACET]: 'Invalid filter option.',
  [ERROR_CODES.FACET_INCOMPATIBLE]: 'This filter is not compatible with the current content type.',
  
  [ERROR_CODES.INVALID_AJAX_ACTION]: 'Invalid action requested.',
  [ERROR_CODES.AJAX_RESPONSE_ERROR]: 'Error processing the server response.',
  [ERROR_CODES.AJAX_REQUEST_ERROR]: 'Error sending the request to the server.',
  
  [ERROR_CODES.QUERY_ERROR]: 'Error processing your filter request.',
  [ERROR_CODES.INVALID_QUERY_VAR]: 'Invalid filter parameter.',
  
  [ERROR_CODES.VALIDATION_ERROR]: 'The information provided is invalid.',
  [ERROR_CODES.INVALID_CONTEXT]: 'Invalid filter context.',
  
  [ERROR_CODES.CACHE_ERROR]: 'Error with the filter cache.',
  
  [ERROR_CODES.NETWORK_ERROR]: 'Network error. Please check your internet connection and try again.',
  [ERROR_CODES.TIMEOUT_ERROR]: 'The request timed out. Please try again.',
  [ERROR_CODES.PARSE_ERROR]: 'Error processing the response from the server.',
};

/**
 * Report an error to the server for logging
 * 
 * @param {string} code Error code
 * @param {string} message Error message
 * @param {Object} data Additional error data
 */
export function reportError(code, message, data = {}) {
  // Only report errors if AJAX URL is available
  if (!window.cplVars || !window.cplVars.ajax_url) {
    console.error('CP Library Error:', code, message, data);
    return;
  }
  
  // Prepare request data
  const requestData = {
    action: 'cpl_filter_error',
    code: code || ERROR_CODES.GENERAL_ERROR,
    message: message || 'Unknown error',
    data: data,
    url: window.location.href,
    nonce: window.cplVars.nonce || ''
  };
  
  // Send error report via AJAX
  jQuery.ajax({
    url: window.cplVars.ajax_url,
    type: 'POST',
    data: requestData,
    error: (xhr, status, error) => {
      console.error('Failed to report error:', error);
    }
  });
}

/**
 * Create a formatted error object
 * 
 * @param {string} code Error code
 * @param {string} message Custom error message (optional)
 * @param {Object} data Additional error data (optional)
 * @returns {Object} Formatted error object
 */
export function createError(code, message = '', data = {}) {
  const errorCode = code || ERROR_CODES.GENERAL_ERROR;
  
  return {
    code: errorCode,
    message: message || DEFAULT_MESSAGES[errorCode] || 'Unknown error',
    timestamp: new Date().toISOString(),
    data: data
  };
}

/**
 * Get a user-friendly error message
 * 
 * @param {string|Object} error Error code or error object
 * @param {string} fallback Fallback message if no suitable message is found
 * @returns {string} User-friendly error message
 */
export function getUserMessage(error, fallback = 'An error occurred. Please try again.') {
  if (!error) {
    return fallback;
  }
  
  // Handle string error codes
  if (typeof error === 'string') {
    return DEFAULT_MESSAGES[error] || fallback;
  }
  
  // Handle error objects from server
  if (error.code) {
    // Use provided message if available
    if (error.message) {
      return error.message;
    }
    
    // Fall back to default message for this code
    return DEFAULT_MESSAGES[error.code] || fallback;
  }
  
  // Handle WordPress AJAX response errors
  if (error.responseJSON && error.responseJSON.data) {
    return error.responseJSON.data.message || fallback;
  }
  
  // Handle generic jQuery AJAX errors
  if (error.statusText) {
    return `${error.statusText} (${error.status})`;
  }
  
  // Generic error handling
  return fallback;
}

/**
 * Display an error message to the user
 * 
 * @param {Element} container Container element to display the error in
 * @param {string|Object} error Error code, message, or object
 * @param {boolean} isAnnouncement Whether to announce the error to screen readers
 */
export function displayError(container, error, isAnnouncement = true) {
  if (!container) {
    console.error('No container provided for error display');
    return;
  }
  
  // Get user-friendly message
  const message = getUserMessage(error);
  
  // Create error element
  const errorElement = document.createElement('div');
  errorElement.className = 'cpl-filter-error';
  errorElement.setAttribute('role', 'alert');
  errorElement.innerHTML = `<p>${message}</p>`;
  
  // Add to container
  container.appendChild(errorElement);
  
  // Announce to screen readers if requested
  if (isAnnouncement && typeof window.announcementToScreenReader === 'function') {
    window.announcementToScreenReader(`Error: ${message}`);
  }
  
  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (errorElement.parentNode === container) {
      container.removeChild(errorElement);
    }
  }, 5000);
}

/**
 * Handle AJAX errors
 * 
 * @param {Object} jqXHR jQuery XHR object
 * @param {string} textStatus Text status
 * @param {string} errorThrown Error thrown
 * @param {Element} container Container element for error display (optional)
 * @returns {Object} Formatted error object
 */
export function handleAjaxError(jqXHR, textStatus, errorThrown, container = null) {
  let errorCode = ERROR_CODES.AJAX_REQUEST_ERROR;
  let errorMessage = '';
  let errorData = {};
  
  // Parse response if possible
  if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
    const responseData = jqXHR.responseJSON.data;
    
    // Use server-provided error code and message if available
    errorCode = responseData.code || errorCode;
    errorMessage = responseData.message || '';
    errorData = { ...responseData };
    
    // Delete redundant properties from data
    delete errorData.code;
    delete errorData.message;
  } else {
    // Handle various error types
    switch (textStatus) {
      case 'timeout':
        errorCode = ERROR_CODES.TIMEOUT_ERROR;
        break;
      case 'parsererror':
        errorCode = ERROR_CODES.PARSE_ERROR;
        break;
      case 'error':
        if (!navigator.onLine) {
          errorCode = ERROR_CODES.NETWORK_ERROR;
        }
        break;
    }
    
    errorData = {
      status: jqXHR.status,
      statusText: jqXHR.statusText,
      errorThrown: errorThrown
    };
  }
  
  // Create formatted error
  const formattedError = createError(errorCode, errorMessage, errorData);
  
  // Report error
  reportError(formattedError.code, formattedError.message, formattedError.data);
  
  // Display error if container provided
  if (container) {
    displayError(container, formattedError);
  }
  
  // Log error
  console.error('CP Library Filter Error:', formattedError);
  
  return formattedError;
}

/**
 * Handle errors from a basic try/catch block
 * 
 * @param {Error} error JavaScript Error object
 * @param {Element} container Container element for error display (optional)
 * @returns {Object} Formatted error object
 */
export function handleCaughtError(error, container = null) {
  const formattedError = createError(
    ERROR_CODES.GENERAL_ERROR,
    error.message,
    {
      name: error.name,
      stack: error.stack
    }
  );
  
  // Report error
  reportError(formattedError.code, formattedError.message, formattedError.data);
  
  // Display error if container provided
  if (container) {
    displayError(container, formattedError);
  }
  
  // Log error
  console.error('CP Library Filter Error:', formattedError);
  
  return formattedError;
}

export default {
  ERROR_CODES,
  reportError,
  createError,
  getUserMessage,
  displayError,
  handleAjaxError,
  handleCaughtError
};