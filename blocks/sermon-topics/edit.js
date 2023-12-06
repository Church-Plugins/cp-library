/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function SermonTopicsEdit({
	context: { item, postType }
}) {
	const blockProps = useBlockProps({})

	if( postType !== 'cpl_item' ) {
		return (
			<div {...blockProps}>{ __( 'This block is not compatible with this post type', 'cp-library' ) }</div>
		)
	}

	const topics = item?.topics ? Object.values(item?.topics) : [{ slug: 'none', name: __( 'No Topics', 'cp-library' ) }]

	return (
		<>
			<div {...blockProps}>
				<span className='material-icons-outlined'>sell</span>
				{
					topics.map((topic, index) => (
						<a className='cpl-topic-link' key={topic.slug}>{topic.name}{index < topics.length - 1 && ','}</a>
					))
				}
			</div>
		</>
	)
}