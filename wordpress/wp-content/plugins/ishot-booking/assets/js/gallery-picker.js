/**
 * Project "Gallery" field: opens the WordPress media library, lets staff pick and order images,
 * and writes their ids (comma-separated) back into the field. wp-admin only; no front-end code.
 */
( function () {
	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.ishot-gallery__pick' );
		if ( ! button || ! window.wp || ! window.wp.media ) {
			return;
		}
		event.preventDefault();
		const input = document.getElementById( button.dataset.target );
		const ids = input.value.split( ',' ).map( Number ).filter( Boolean );

		const frame = window.wp.media( {
			title: button.textContent,
			multiple: 'add',
			library: { type: 'image' },
			button: { text: button.textContent },
		} );

		frame.on( 'open', function () {
			const selection = frame.state().get( 'selection' );
			ids.forEach( function ( id ) {
				const attachment = window.wp.media.attachment( id );
				attachment.fetch();
				selection.add( attachment );
			} );
		} );

		frame.on( 'select', function () {
			const chosen = frame.state().get( 'selection' ).toJSON();
			input.value = chosen.map( function ( a ) { return a.id; } ).join( ',' );
			const preview = input.parentNode.querySelector( '.ishot-gallery__preview' );
			preview.replaceChildren(
				...chosen.map( function ( a ) {
					const li = document.createElement( 'li' );
					const img = document.createElement( 'img' );
					img.src = ( a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail : a ).url;
					img.alt = a.alt || '';
					img.width = 80;
					li.appendChild( img );
					return li;
				} )
			);
		} );

		frame.open();
	} );
} )();
