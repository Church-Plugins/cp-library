
(function($) {

	let transcripts = function() {
		let SELF = this;

		SELF.init = function() {
			SELF.$body = $('body');
			SELF.$transcript = $('.cpl-item--transcript');

			if ( !SELF.$transcript.length ) {
				return;
			}

			if ( SELF.$transcript.outerHeight() < 200 ) {
				return;
			}

			SELF.$transcript.addClass( 'cpl-transcript--collapsed' );
			SELF.$transcript.find( '.cpl-transcript--toggle' ).on( 'click', SELF.toggleTranscript );
		};

		SELF.toggleTranscript = function( e ) {
			e.preventDefault();
			SELF.$transcript.removeClass( 'cpl-transcript--collapsed' );
		}

		SELF.init();

	}

	let persistentPlayer = function() {
		let SELF = this;

		SELF.init = function() {
			SELF.$body = $('body');
			SELF.$iframe = false;
			SELF.isIframe = (window !== window.parent);
			SELF.messageAction = 'cpl_iframe_link';

			SELF.$body.on('click', 'a', SELF.handleLinkClick);
			window.addEventListener("message", SELF.iframeMessage);

			if(window.navigation) [
				window.navigation.addEventListener('navigate', event => {
					if( SELF.$iframe ) {
						SELF.$iframe.attr('src', event.destination.url);
					}
				})
			]
		};

		/**
		 * Indicates whether the persistent player component has been mounted or not.
		 * @returns boolean
		 */
		SELF.isActive = function() {
			return SELF.$body.hasClass('cpl-persistent-player');
		};

		/**
		 * Invoked whenever a link (<a />) is clicked anywhere in the body. If it's a first-party link,
		 * we hijack the event.
		 * @param {MouseEvent} e
		 * @returns false
		 */
		SELF.handleLinkClick = function(e) {
			SELF.url = e.currentTarget.href;

			// make sure this is a local link
			if (!SELF.url.includes(window.location.hostname) || '_blank' === e.currentTarget.target) {
				return;
			}

			// don't include admin links
			if (SELF.url.includes('/wp-admin/')) {
				return;
			}

			return SELF.isIframe ? SELF.handleIframeClick() : SELF.handleClick();
		};

		/**
		 * Invoked when we're in an iframe. Sends a message to the top-most iframe.
		 * @returns false
		 */
		SELF.handleIframeClick = function() {
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
			if ( !SELF.isActive() ) {
				return true;
			}

			// Remove the margin-top that the admin bar adds
			SELF.$body.prepend('<iframe id="cpl_persistent_player_iframe" style="z-index:5000;background:transparent;width:100%;height:100%;position:fixed;border:none;"></iframe>');
			SELF.$iframe = $('#cpl_persistent_player_iframe');
			SELF.$iframe.on('load', SELF.iframeLoaded);
			SELF.$iframe.attr('src', SELF.url);
			window.history.pushState({}, '', url);

			return false;
		};

		/**
		 * Invoked when the newly-attached iframe loaded. Remove everything outside the iframe but the
		 * persistent player. This makes it look like the page has successfully navigated while the
		 * player persist. A SPA-like experience as far the end-user is concerned.
		 */
		SELF.iframeLoaded = function() {
			if ( $('body').hasClass('admin-bar') ) {
				document.querySelector('html').style.setProperty('margin-top', '0px', 'important')
			}

			$('body > *').each(function () {
				var $this = $(this);

				if ($this.attr('id') === 'cpl_persistent_player' || $this.attr('id') === 'cpl_persistent_player_iframe') {
					return;
				}

				$(this).remove();
			});
		};

		/**
		 * Invoked whenever someone post a message to this window.
		 * @param {MessageEvent} e
		 * @returns undefined
		 */
		SELF.iframeMessage = function(e) {
			// Filter out anything that we don't care about
			if (SELF.messageAction !== e.data.action) {
				return;
			}

			if (SELF.isActive()) {
				SELF.$iframe.attr('src', e.data.url);
				window.history.pushState({}, '', e.data.url);
			} else {
				window.location.href = e.data.url;
			}
		};

		SELF.init();
	};

	$(document).on('ready', function() {
		persistentPlayer();
		transcripts();
	});

})(jQuery);
