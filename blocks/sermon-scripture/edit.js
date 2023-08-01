/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

import { __ } from '@wordpress/i18n';

export default function SermonScriptureEdit({
	context: { postId, postType, queryId, item }
}) {
	const scriptures = Object.values(item?.scripture || {})

	const blockProps = useBlockProps({})

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