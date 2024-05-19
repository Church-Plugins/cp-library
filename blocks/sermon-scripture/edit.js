/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

import { __ } from '@wordpress/i18n';

export default function SermonScriptureEdit({
	context: { postType, item }
}) {
	const blockProps = useBlockProps({})

	if( postType !== 'cpl_item' && postType !== 'cpl_item_type' ) {
		return (
			<div {...blockProps}>{ __( 'This block is not compatible with this post type', 'cp-library' ) }</div>
		)
	}

	const scriptures = item?.scripture ? Object.values(item.scripture) : [{ slug: 'none', name: 'No Scriptures' }]

	return (
		<>
			<div {...blockProps}>
			<span className='material-icons-outlined'>menu_book</span>
			{
				scriptures.map((scripture, index) => (
					<a className='cpl-scripture-link' key={scripture.slug}>{scripture.name}{index < scriptures.length - 1 && ', '}</a>
				))
			}
			</div>
		</>
	)
}