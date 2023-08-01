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

import { ToggleControl, TextControl, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect, dispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch'
import { Volume1 } from 'react-feather';

export default function SermonAudioEdit({
	context: { postId, postType, queryId }
}) {
	const blockProps = useBlockProps({
		className: 'cpl-button cpl-button--outlined is-outlined cpl-button--rectangle'
	})

	return (
		<div {...blockProps}>
			<Volume1 style={{ fill: 'none', stroke: 'currentcolor' }} strokeWidth={2} stroke="currentColor" />
			<span>{ __( 'Listen', 'cp-library' ) }</span>
		</div>
	);
}