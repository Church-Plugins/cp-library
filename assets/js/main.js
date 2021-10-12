window.$ = jQuery;
(
	function ($) {
		var readyList = [];

// Store a reference to the original ready method.
		var originalReadyMethod = jQuery.fn.ready;

// Override jQuery.fn.ready
		jQuery.fn.ready = function () {
			if (arguments.length && arguments.length > 0 && typeof arguments[0] === 'function' && !readyList.includes(arguments[0])) {
				readyList.push(arguments[0]);
			}

// Execute the original method.
			originalReadyMethod.apply(this, arguments);
		};

// Used to trigger all ready events
		$.triggerReady = function () {
			$(readyList).each(function () {this($);});
		};
	}
)(jQuery);

jQuery(document).ready(function () {
	alert('document.ready is fired!');
});
