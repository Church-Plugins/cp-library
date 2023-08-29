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
	context: { postType, item }
}) {

	const blockProps = useBlockProps({})

	if( postType !== 'cpl_item' ) {
		return (
			<div {...blockProps}>{ __( 'This block is not compatible with this post type', 'cp-library' ) }</div>
		)
	}

	const speakers = item?.speakers || []


	return (
		<>
			<div {...blockProps}>
			<span className='material-icons-outlined'>person</span>
			
			{
				speakers.length ?
				speakers.map((speaker, index) => (
					<a className='cpl-speaker-link' key={speaker.id}>{speaker.title}{index < speakers.length - 1 && ','}</a>
				)) :
				__( 'No speakers found', 'cp-library' )
			}
			</div>
		</>
	)
}