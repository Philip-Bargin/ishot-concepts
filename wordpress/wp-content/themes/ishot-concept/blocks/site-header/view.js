import { store, getElement } from '@wordpress/interactivity';

// The prototype's useFocusTrap (src/lib/hooks.js): the same selector and behaviour.
const FOCUSABLE =
	'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const { state, actions } = store( 'ishot/site-header', {
	state: {
		isOpen: false,
	},
	actions: {
		open() {
			state.isOpen = true;
		},
		close() {
			state.isOpen = false;
		},
	},
	callbacks: {
		/**
		 * Runs on the sheet whenever `isOpen` changes. While open: focus moves inside (to
		 * [data-autofocus] or the first focusable element), Tab and Shift+Tab wrap, Escape closes and
		 * the body cannot scroll. The cleanup (on close) undoes that and returns focus to the opener.
		 */
		trap() {
			if ( ! state.isOpen ) {
				return undefined;
			}
			const { ref: root } = getElement();
			if ( ! root ) {
				return undefined;
			}
			const opener = root.ownerDocument.activeElement;
			const items = () =>
				[ ...root.querySelectorAll( FOCUSABLE ) ].filter(
					( el ) => el.getClientRects().length > 0
				);

			// When the sheet is opened before its region has hydrated, this callback can run while the
			// sheet still carries `hidden` (the bind directive updates it a frame later), and focus() on a
			// hidden element does nothing. Wait for the sheet to render (at most ~30 frames).
			let frame = 0;
			const focusIn = ( tries = 30 ) => {
				if ( root.hidden && tries > 0 ) {
					frame = root.ownerDocument.defaultView.requestAnimationFrame( () => focusIn( tries - 1 ) );
					return;
				}
				( root.querySelector( '[data-autofocus]' ) ?? items()[ 0 ] )?.focus();
			};
			focusIn();

			const onKey = ( e ) => {
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					actions.close();
					return;
				}
				if ( e.key !== 'Tab' ) {
					return;
				}
				const list = items();
				if ( ! list.length ) {
					return;
				}
				const first = list[ 0 ];
				const last = list[ list.length - 1 ];
				const active = root.ownerDocument.activeElement;
				if ( e.shiftKey && active === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && active === last ) {
					e.preventDefault();
					first.focus();
				}
			};

			const { body } = root.ownerDocument;
			const prevOverflow = body.style.overflow;
			body.style.overflow = 'hidden';
			root.ownerDocument.addEventListener( 'keydown', onKey );
			return () => {
				root.ownerDocument.defaultView.cancelAnimationFrame( frame );
				root.ownerDocument.removeEventListener( 'keydown', onKey );
				body.style.overflow = prevOverflow;
				if (
					opener instanceof HTMLElement &&
					opener.isConnected
				) {
					opener.focus();
				}
			};
		},
	},
} );
