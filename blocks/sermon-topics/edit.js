/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function SermonTopicsEdit({
	context: { item }
}) {
	const topics = Object.values(item?.topics || {})

	const blockProps = useBlockProps({})

	return (
		<>
			<div {...blockProps}>
			<span className='material-icons-outlined'>sell</span>
			{
				topics.length ?
				topics.map((topic, index) => (
					<span className='cpl-topic-link' key={topic.slug}>{topic.name}{index < topics.length - 1 && ','}</span>
				)) :
				__( 'No Topics', 'cp-library' )
			}
			</div>
		</>
	)
}