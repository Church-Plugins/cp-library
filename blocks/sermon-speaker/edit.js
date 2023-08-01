/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import {
	AlignmentControl,
	BlockControls,
	InspectorControls,
	useBlockProps,
	PlainText
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';


export default function SermonSpeakerEdit({
	context: { item }
}) {
	const speakers = item?.speakers || []

	const blockProps = useBlockProps({})

	return (
		<>
			<div {...blockProps}>
			<span className='material-icons-outlined'>person</span>
			{
				speakers.length ?
				speakers.map((speaker, index) => (
					<span className='cpl-speaker-link' key={speaker.id}>{speaker.title}{index < speakers.length - 1 && ','}</span>
				)) :
				__( 'No speakers found', 'cp-library' )
			}
			</div>
		</>
	)
}