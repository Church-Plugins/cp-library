
(function($) {

	let persistentPlayer = function() {
		let SELF = this;

		SELF.init = function() {
			SELF.$body = $('body');
			SELF.$iframe = false;
			SELF.isIframe = (window !== window.parent);
			SELF.messageAction = 'cpl_iframe_link';

			SELF.$body.on('click', 'a', SELF.handleLinkClick);
			window.addEventListener("message", SELF.iframeMessage);
		};

		SELF.isActive = function() {
			return SELF.$body.hasClass('cpl-persistent-player');
		};

		SELF.handleLinkClick = function(e) {
			SELF.url = e.currentTarget.href;

			// make sure this is a local link
			if (!SELF.url.includes(window.location.hostname)) {
				return;
			}

			return SELF.isIframe ? SELF.handleIframeClick() : SELF.handleClick();
		};

		SELF.handleIframeClick = function() {
			window.top.postMessage({
				'action': SELF.messageAction,
				'url'   : SELF.url,
			}, '*');

			return false;
		};

		SELF.handleClick = function() {
			if ( !SELF.isActive() ) {
				return true;
			}

			SELF.$body.prepend('<iframe id="cpl_persistent_player_iframe" style="z-index:5000;background:transparent;width:100%;height:100%;position:fixed;border:none;"></iframe>');
			SELF.$iframe = $('#cpl_persistent_player_iframe');

			SELF.$iframe.on('load', SELF.iframeLoaded);
			SELF.$iframe.attr('src', SELF.url);

			return false;
		};

		SELF.iframeLoaded = function() {
			window.history.pushState({}, '', url);

			$('body > *').each(function () {
				var $this = $(this);

				if ($this.attr('id') === 'cpl_persistent_player' || $this.attr('id') === 'cpl_persistent_player_iframe') {
					return;
				}

				$(this).remove();
			});
		};

		SELF.iframeMessage = function(e) {
			if (SELF.messageAction !== e.data.action) {
				return;
			}

			if (SELF.isActive()) {
				SELF.$iframe.attr('src', e.data.url);
			} else {
				window.location.href = e.data.url;
			}
		};

		SELF.init();
	};

	$(document).on('ready', function() {
		persistentPlayer();
	});

})(jQuery);
