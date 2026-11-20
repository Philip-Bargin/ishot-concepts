/**
 * Project "Gallery" field: opens the WordPress media library, lets staff pick and order images,
 * and writes their ids (comma-separated) back into the field. wp-admin only; no front-end code.
 *
 * Order = the order images are ticked. To remove an image, untick it (or delete its id from the
 * text field). One media frame is created per field and reused, so repeated opens don't stack
 * hidden dialogs in the page.
 */
( function () {
	const frames = {};

	function idsOf( input ) {
		return input.value.split( ',' ).map( Number ).filter( Boolean );
	}

	function renderPreview( input, attachments ) {
		const preview = input.parentNode.querySelector( '.ishot-gallery__preview' );
		preview.replaceChildren(
			...attachments.map( function ( a ) {
				const li = document.createElement( 'li' );
				const img = document.createElement( 'img' );
				img.src = ( a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail : a ).url;
				img.alt = a.alt || '';
				img.width = 80;
				li.appendChild( img );
				return li;
			} )
		);
	}

	function frameFor( button, input ) {
		if ( frames[ input.id ] ) {
			return frames[ input.id ];
		}
		const frame = window.wp.media( {
			title: button.textContent,
			multiple: 'add',
			library: { type: 'image' },
			button: { text: button.textContent },
		} );

		frame.on( 'open', function () {
			// Show the library (where the current picks are ticked), not the upload tab that
			// WordPress picks while the library is still loading.
			frame.content.mode( 'browse' );
			const selection = frame.state().get( 'selection' );
			selection.reset(
				idsOf( input ).map( function ( id ) {
					const attachment = window.wp.media.attachment( id );
					attachment.fetch();
					return attachment;
				} )
			);
		} );

		frame.on( 'select', function () {
			const chosen = frame.state().get( 'selection' ).toJSON();
			input.value = chosen.map( function ( a ) { return a.id; } ).join( ',' );
			renderPreview( input, chosen );
		} );

		frames[ input.id ] = frame;
		return frame;
	}

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.ishot-gallery__pick' );
		if ( ! button || ! window.wp || ! window.wp.media ) {
			return;
		}
		event.preventDefault();
		frameFor( button, document.getElementById( button.dataset.target ) ).open();
	} );
} )();
