import { store, getContext } from '@wordpress/interactivity';

const root = document.documentElement;

store( 'ishot/theme', {
	actions: {
		toggle() {
			const context = getContext();
			const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
			root.dataset.theme = next;
			try {
				window.localStorage.setItem( context.storageKey, next );
			} catch ( e ) {
				// Storage blocked: the choice still applies for this visit.
			}
			context.pressed = next === 'dark';
		},
	},
	callbacks: {
		sync() {
			getContext().pressed = root.dataset.theme === 'dark';
		},
	},
} );
