
(function($) {

	let persistentPlayer = function() {
		let SELF = this;

		SELF.init = function() {
			console.log("persistentPlayer.js initializing");
			SELF.$body = $('body');
			SELF.$iframe = false;
			SELF.isIframe = (window !== window.parent);
			SELF.messageAction = 'cpl_iframe_link';

			SELF.$body.on('click', 'a', SELF.handleLinkClick);
			window.addEventListener("message", SELF.iframeMessage);
			console.log("persistentPlayer.js initialized", {
				body: SELF.$body,
				iframe: SELF.$iframe,
				isIframe: SELF.isIframe,
			});
		};

		/**
		 * Indicates whether the persistent player component has been mounted or not.
		 * @returns boolean
		 */
		SELF.isActive = function() {
			console.log("persistentPlayer.js isActive:", SELF.$body.hasClass('cpl-persistent-player'))
			return SELF.$body.hasClass('cpl-persistent-player');
		};

		/**
		 * Invoked whenever a link (<a />) is clicked anywhere in the body. If it's a first-party link,
		 * we hijack the event.
		 * @param {MouseEvent} e 
		 * @returns false
		 */
		SELF.handleLinkClick = function(e) {
			console.log("persistentPlayer.js handle link click", { e })
			SELF.url = e.currentTarget.href;

			// make sure this is a local link
			if (!SELF.url.includes(window.location.hostname)) {
				console.log("persistentPlayer.js not a local link", {
					url: SELF.url,
					hostname: window.location.hostname,
				})
				return;
			}

			return SELF.isIframe ? SELF.handleIframeClick() : SELF.handleClick();
		};

		/**
		 * Invoked when we're in an iframe. Sends a message to the top-most iframe.
		 * @returns false
		 */
		SELF.handleIframeClick = function() {
			console.log("persistentPlayer.js handling iframe click", {
				'action': SELF.messageAction,
				'url'   : SELF.url,
			});
			window.top.postMessage({
				'action': SELF.messageAction,
				'url'   : SELF.url,
			}, '*');

			return false;
		};

		/**
		 * Invoked when we're the top-level window. Attach a new iframe to the body whose `src` is the
		 * destination URL.
		 * @returns boolean
		 */
		SELF.handleClick = function() {
			console.log("persistentPlayer.js handling click");
			if ( !SELF.isActive() ) {
				console.log("persistentPlayer.js is not active");
				return true;
			}

			SELF.$body.prepend('<iframe id="cpl_persistent_player_iframe" style="z-index:5000;background:transparent;width:100%;height:100%;position:fixed;border:none;"></iframe>');
			SELF.$iframe = $('#cpl_persistent_player_iframe');

			SELF.$iframe.on('load', SELF.iframeLoaded);
			SELF.$iframe.attr('src', SELF.url);
			console.log("persistentPlayer.js iframe attached")

			return false;
		};

		/**
		 * Invoked when the newly-attached iframe loaded. Remove everything outside the iframe but the
		 * persistent player. This makes it look like the page has successfully navigated while the
		 * player persist. A SPA-like experience as far the end-user is concerned.
		 */
		SELF.iframeLoaded = function() {
			console.log("persistentPlayer.js iframe loaded");

			window.history.pushState({}, '', url);

			$('body > *').each(function () {
				var $this = $(this);

				if ($this.attr('id') === 'cpl_persistent_player' || $this.attr('id') === 'cpl_persistent_player_iframe') {
					return;
				}

				$(this).remove();
			});
			console.log("persistentPlayer.js stuf removed");
		};

		/**
		 * Invoked whenever someone post a message to this window.
		 * @param {MessageEvent} e 
		 * @returns undefined
		 */
		SELF.iframeMessage = function(e) {
			// console.log("persistentPlayer.js message received", {e})
			// Filter out anything that we don't care about
			if (SELF.messageAction !== e.data.action) {
				console.log("persistentPlayer.js message ignored", e.data);
				return;
			}

			if (SELF.isActive()) {
				console.log("persistentPlayer.js changing iframe src", e.data.url)
				SELF.$iframe.attr('src', e.data.url);
			} else {
				console.log("persistentPlayer.js changing URL", e.data.url);
				window.location.href = e.data.url;
			}
		};

		SELF.init();
	};

	$(document).on('ready', function() {
		console.log("invoking persistentPlayer.js")
		persistentPlayer();
	});

})(jQuery);
