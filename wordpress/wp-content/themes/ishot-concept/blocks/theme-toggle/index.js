/**
 * Editor registration: the block is rendered on the server (render.php), so the editor shows the
 * same markup through ServerSideRender. Read-only in the canvas (links and buttons are disabled).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes } ) => (
		<div { ...useBlockProps() }>
			<Disabled>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</Disabled>
		</div>
	),
	save: () => null,
} );
