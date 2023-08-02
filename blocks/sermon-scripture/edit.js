/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

import { __ } from '@wordpress/i18n';

export default function SermonScriptureEdit({
	context: { postType, item }
}) {
	const blockProps = useBlockProps({})

	if( postType !== 'cpl_item' ) {
		return (
			<div {...blockProps}>{ __( 'This block is not compatible with this post type', 'cp-library' ) }</div>
		)
	}

	const scriptures = Object.values(item?.scripture || {})

	return (
		<>
			<div {...blockProps}>
			<span className='material-icons-outlined'>menu_book</span>
			{
				scriptures.length ?
				scriptures.map((scripture, index) => (
					<span className='cpl-scripture-link' key={scripture.slug}>{scripture.name}{index < scriptures.length - 1 && ','}</span>
				)) :
				__( 'No Scriptures', 'cp-library' )
			}
			</div>
		</>
	)
}