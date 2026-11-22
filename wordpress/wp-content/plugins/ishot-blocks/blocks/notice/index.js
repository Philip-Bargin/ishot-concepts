/**
 * Editor registration: the block is rendered on the server (render.php), so the editor shows the
 * same markup through ServerSideRender, for the post being edited (block context postId). In a
 * template there is no post, so the canvas shows a short description instead of an empty box.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

const Empty = () => <p className="fo-muted small">{ __( 'Sample content notice: shown while this content is a placeholder.', 'ishot-blocks' ) }</p>;

registerBlockType( metadata.name, {
	edit: ( { attributes, context } ) => (
		<div { ...useBlockProps() }>
			<Disabled>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					urlQueryArgs={ context.postId ? { post_id: context.postId } : {} }
					EmptyResponsePlaceholder={ Empty }
				/>
			</Disabled>
		</div>
	),
	save: () => null,
} );
