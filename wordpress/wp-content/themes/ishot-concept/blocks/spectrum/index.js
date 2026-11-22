/**
 * Editor registration: the canvas shows the same four bars the server prints (render.php), so the
 * Site Editor previews the footer instead of showing HTML source. The markup is static, so it is
 * rendered here directly rather than through ServerSideRender.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: () => (
		<span { ...useBlockProps( { className: 'fo-spectrum' } ) }>
			<span aria-hidden="true">
				<i />
				<i />
				<i />
				<i />
			</span>
		</span>
	),
	save: () => null,
} );
