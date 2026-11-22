/**
 * Editor side of the theme's `ishot-concept/site` block-bindings source (inc/footer.php). Without
 * getValues, WordPress 6.7 shows the source label in place of a bound paragraph, so the footer's
 * legal line read "iShot Concept site" in the Site Editor. Same keys and text as the PHP source.
 * Built as a second editorScript of ishot/theme-toggle, the legal band's block, because the theme's
 * build compiles block.json entries only.
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { __, sprintf } from '@wordpress/i18n';

const value = ( key ) => {
	const year = String( new Date().getFullYear() );
	if ( key === 'year' ) {
		return year;
	}
	if ( key === 'copyright' ) {
		/* translators: %s: the current year */
		return sprintf( __( '© %s iShot Concept · Photography, creative direction, visual storytelling', 'ishot-concept' ), year );
	}
	return undefined;
};

registerBlockBindingsSource( {
	name: 'ishot-concept/site',
	label: __( 'iShot Concept site', 'ishot-concept' ),
	getValues( { bindings } ) {
		const values = {};
		for ( const [ attribute, binding ] of Object.entries( bindings ) ) {
			const found = value( binding?.args?.key );
			if ( found !== undefined ) {
				values[ attribute ] = found;
			}
		}
		return values;
	},
} );
