/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

import { __ } from '@wordpress/i18n';
import { Play, Volume1 } from 'react-feather';

export default function SermonAudioEdit({
	context: { item }
}) {
	const blockProps = useBlockProps({ })

	const disabledStyle = { pointerEvents: 'none', opacity: '0.3' }
	const videoButtonStyle = item?.video?.value ? {} : disabledStyle
	const audioButtonStyle = item?.audio ? {} : disabledStyle

	return (
		<div {...blockProps}>
			<div>
				{
					<button style={videoButtonStyle} className='cpl-button cpl-button--primary is-primary cpl-button--rectangle'>
						<Play style={{ fill: 'none', stroke: 'currentcolor' }} strokeWidth={2} stroke="currentColor" />
						<span>{ __( 'Watch', 'cp-library' ) }</span>
					</button>
				}			

				{
					<button style={audioButtonStyle} className='cpl-button cpl-button--outlined is-outlined cpl-button--rectangle'>
						<Volume1 style={{ fill: 'none', stroke: 'currentcolor' }} strokeWidth={2} stroke="currentColor" />
						<span>{ __( 'Listen', 'cp-library' ) }</span>
					</button>
				}
			</div>
		</div>
	);
}